<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Enums\ModelStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Http\Resources\UserIndexResource;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Notifications\UserInvited;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    private const PORTAL_PERMISSIONS = [
        'orders.viewAny' => 'Zobraziť zoznam objednávok',
        'orders.view'    => 'Zobraziť detail objednávky',
        'orders.create'  => 'Vytvárať objednávky',
        'orders.update'  => 'Upravovať objednávky',
        'orders.storno'  => 'Stornovať objednávky',
    ];

    public function index(Request $request)
    {
        Gate::authorize('viewAny', User::class);

        $users = User::query()
            ->with(['roles', 'customer'])
            ->withCount('orders')
            ->when(! $request->user()->hasRole('super-admin'), function ($query) use ($request) {
                $customerIds = Order::where('user_id', $request->user()->id)
                    ->whereNotNull('customer_id')
                    ->pluck('customer_id')
                    ->unique();

                $query->where(function ($query) use ($request, $customerIds) {
                    $query->where(function ($query) use ($customerIds) {
                        $query->whereNotNull('customer_id')
                            ->whereIn('customer_id', $customerIds);
                    })->orWhere('id', $request->user()->id);
                });
            })
            ->when($request->filled('bySearchInput'), function ($query) use ($request) {
                $search = $request->string('bySearchInput')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('firstName', 'like', '%' . $search . '%')
                        ->orWhere('lastName', 'like', '%' . $search . '%')
                        ->orWhere('position', 'like', '%' . $search . '%')
                        ->orWhere('username', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%')
                        ->orWhere('phone', 'like', '%' . $search . '%')
                        ->orWhereHas('customer', function ($query) use ($search) {
                            $query->where('company', 'like', '%' . $search . '%')
                                ->orWhere('city', 'like', '%' . $search . '%')
                                ->orWhere('ico', 'like', '%' . $search . '%');
                        });
                });
            })
            ->when($request->filled('role'), function ($query) use ($request) {
                $query->role($request->string('role')->toString());
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $status = ModelStatus::tryFrom($request->string('status')->toString());

                if ($status) {
                    $query->withStatus($status);
                }
            })
            ->when($request->filled('login'), function ($query) use ($request) {
                match ($request->string('login')->toString()) {
                    'never'    => $query->whereNull('last_login_at'),
                    'logged'   => $query->whereNotNull('last_login_at'),
                    'last30'   => $query->where('last_login_at', '>=', now()->subDays(30)),
                    'over90'   => $query->where('last_login_at', '<', now()->subDays(90)),
                    default    => $query,
                };
            })
            ->latest()
            ->paginate();

        // Zoznam statusov ide v meta, aby ho filter v zozname vedel vyrenderovať.
        return UserIndexResource::collection($users)->additional([
            'meta' => [
                'statuses' => ModelStatus::allowedForUserAccount($request->user()),
            ],
        ]);
    }

    public function create()
    {
        Gate::authorize('create', User::class);

        return response()->json(['meta' => $this->createOptions()]);
    }

    public function store(UserStoreRequest $request)
    {
        Gate::authorize('create', User::class);

        $validated = $request->validated();
        $this->authorizeCustomerScope($request->user(), (int) $validated['customer_id']);

        $roles       = $validated['roles'] ?? [];
        $permissions = $validated['permissions'] ?? [];
        unset($validated['roles'], $validated['permissions']);

        $isSuperAdmin = $request->user()->hasRole('super-admin');

        $plainName = $this->plainName($validated);
        $validated['name']     = $this->displayName($validated);
        $validated['username'] = $plainName ?: $validated['email'];
        $validated['slug']     = Str::slug($validated['username']);
        $validated['uuid']     = (string) Str::uuid();
        // Status vie zvoliť len super-admin; ostatní vytvárajú účet na overenie emailom.
        $validated['status']   = $isSuperAdmin
            ? ($validated['status'] ?? ModelStatus::Active->value)
            : ModelStatus::Draft->value;
        $validated['active']   = $request->boolean('active', true);

        $password = Str::random(12);
        $validated['password'] = bcrypt($password);

        $user = DB::transaction(function () use ($validated, $roles, $permissions, $isSuperAdmin) {
            $user = User::create($validated);

            if ($isSuperAdmin && ! empty($roles)) {
                $user->syncRoles($roles);
            }

            if (! empty($permissions)) {
                $allowed = array_keys(self::PORTAL_PERMISSIONS);
                $toGrant = collect($permissions)->intersect($allowed)->values()->toArray();
                $user->syncPermissions($toGrant);
            }

            return $user;
        });

        // Overovací link posielame vždy, keď účet ostáva v drafte.
        $verificationUrl = $validated['status'] === ModelStatus::Draft->value
            ? route('users.verifyEmail', ['uuid' => $validated['uuid']])
            : null;

        $user->notify(new UserInvited($user, $password, $roles, $verificationUrl));

        return new UserIndexResource($user->load(['roles', 'customer'])->loadCount('orders'));
    }

    public function verifyEmail(string $uuid)
    {
        $user = User::where('uuid', $uuid)->firstOrFail();

        if ($user->status !== ModelStatus::Draft) {
            return redirect(env('FRONTEND_URL', config('app.url')) . '/login?verified=already');
        }

        $user->update([
            'status'            => ModelStatus::Active->value,
            'email_verified_at' => now(),
        ]);

        return redirect(env('FRONTEND_URL', config('app.url')) . '/login?verified=1');
    }

    public function show(User $user)
    {
        Gate::authorize('view', $user);

        return (new UserIndexResource($user->load(['roles', 'customer', 'latestOrder', 'lastUsedToken'])->loadCount('orders')))
            ->additional($this->formOptions($user));
    }

    public function update(UserUpdateRequest $request, User $user)
    {
        Gate::authorize('update', $user);
        $this->authorizeCustomerScope($request->user(), $user->customer_id);

        $validated   = $request->validated();
        $roles       = $validated['roles'] ?? null;
        $permissions = $validated['permissions'] ?? null;
        unset($validated['roles'], $validated['permissions']);

        $validated['name']     = $this->displayName($validated);
        $validated['username'] = $validated['username'] ?: $this->plainName($validated);
        $validated['slug']     = Str::slug($validated['username']);

        DB::transaction(function () use ($user, $validated, $roles, $permissions, $request) {
            $user->update($validated);

            if ($request->user()->hasAnyRole(['admin', 'super-admin']) && is_array($roles)) {
                $user->syncRoles($roles);
            }

            if (is_array($permissions) && $user->customer_id !== null) {
                $allowed = array_keys(self::PORTAL_PERMISSIONS);
                $toGrant = collect($permissions)->intersect($allowed)->values()->toArray();
                $user->syncPermissions($toGrant);
            }
        });

        return (new UserIndexResource($user->refresh()->load(['roles', 'customer'])->loadCount('orders')))
            ->additional($this->formOptions($user));
    }

    /**
     * Meno bez titulov — základ pre username a slug.
     */
    private function plainName(array $validated): string
    {
        return trim(($validated['firstName'] ?? '') . ' ' . ($validated['lastName'] ?? ''));
    }

    /**
     * Zobrazované meno vrátane titulov: "Ing. Ján Novák, PhD."
     */
    private function displayName(array $validated): string
    {
        $prefix = trim((string) ($validated['prefix'] ?? ''));
        $postfix = trim((string) ($validated['postfix'] ?? ''));

        $name = trim($prefix . ' ' . $this->plainName($validated));

        return $postfix !== '' ? $name . ', ' . $postfix : $name;
    }

    private function authorizeCustomerScope(User $authUser, ?int $customerId): void
    {
        if ($authUser->hasRole('super-admin') || $customerId === null) {
            return;
        }

        $customerIds = Order::where('user_id', $authUser->id)
            ->whereNotNull('customer_id')
            ->pluck('customer_id');

        if (! $customerIds->contains($customerId)) {
            abort(403, 'Nemáte oprávnenie pre tohto zákazníka.');
        }
    }

    private function createOptions(): array
    {
        $authUser   = request()->user();
        $isSuperAdmin = $authUser?->hasRole('super-admin');

        $customersQuery = Customer::query()->orderBy('company');

        if (! $isSuperAdmin) {
            $customerIds = Order::where('user_id', $authUser->id)
                ->whereNotNull('customer_id')
                ->pluck('customer_id')
                ->unique();

            $customersQuery->whereIn('id', $customerIds);
        }

        $customers = $customersQuery->get()->map(fn (Customer $c) => [
            'value' => $c->id,
            'label' => $c->company . ($c->city ? " ({$c->city})" : ''),
        ]);

        return [
            'customers' => $customers,
            'roles' => $isSuperAdmin
                ? Role::query()->orderBy('name')->pluck('name')
                    ->map(fn (string $r) => ['value' => $r, 'label' => $r])->values()
                : [],
            'portal_permissions' => collect(self::PORTAL_PERMISSIONS)
                ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
                ->values(),
            'statuses' => ModelStatus::allowedForUserAccount($authUser),
            'locales' => self::locales(),
        ];
    }

    /**
     * Jazyky notifikácií — zoznam drží config, aby sedel s prekladmi v lang/.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private static function locales(): array
    {
        $labels = ['sk' => 'Slovenčina', 'cs' => 'Čeština', 'en' => 'Angličtina'];

        return collect(config('app.supported_locales', []))
            ->map(fn (string $locale) => [
                'value' => $locale,
                'label' => $labels[$locale] ?? strtoupper($locale),
            ])
            ->values()
            ->all();
    }

    private function formOptions(User $user): array
    {
        $authUser     = request()->user();
        $isSuperAdmin = $authUser?->hasRole('super-admin');

        return [
            'meta' => [
                'roles' => $isSuperAdmin
                    ? Role::query()->orderBy('name')->pluck('name')
                        ->map(fn (string $r) => ['value' => $r, 'label' => $r])->values()
                    : [],
                'portal_permissions' => $user->customer_id !== null
                    ? collect(self::PORTAL_PERMISSIONS)
                        ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
                        ->values()
                    : [],
                'statuses' => ModelStatus::allowedForUserAccount($authUser),
                'locales' => self::locales(),
            ],
        ];
    }
}
