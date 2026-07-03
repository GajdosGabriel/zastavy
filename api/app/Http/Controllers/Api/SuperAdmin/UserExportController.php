<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserExportController extends Controller
{
    private const ATTRIBUTES = [
        'id'                => 'ID',
        'firstName'         => 'Meno',
        'lastName'          => 'Priezvisko',
        'username'          => 'Používateľské meno',
        'email'             => 'E-mail',
        'phone'             => 'Telefón',
        'status'            => 'Status',
        'roles'             => 'Role',
        'orders_count'      => 'Počet objednávok',
        'customer_company'  => 'Firma (zákazník)',
        'customer_ico'      => 'IČO (zákazník)',
        'customer_city'     => 'Mesto (zákazník)',
        'email_verified_at' => 'E-mail overený',
        'created_at'        => 'Vytvorený',
    ];

    public function attributes()
    {
        Gate::authorize('viewAny', User::class);

        return response()->json([
            'data' => collect(self::ATTRIBUTES)
                ->map(fn (string $label, string $key) => ['value' => $key, 'label' => $label])
                ->values(),
        ]);
    }

    public function export(Request $request)
    {
        Gate::authorize('viewAny', User::class);

        $validated = $request->validate([
            'attributes'   => ['required', 'array', 'min:1'],
            'attributes.*' => ['string', 'in:' . implode(',', array_keys(self::ATTRIBUTES))],
        ]);

        $attributes = $validated['attributes'];
        $authUser   = $request->user();

        $users = User::query()
            ->with(['roles', 'customer'])
            ->withCount('orders')
            ->when(! $authUser->hasRole('super-admin'), function ($query) use ($authUser) {
                $customerIds = Order::where('user_id', $authUser->id)
                    ->whereNotNull('customer_id')
                    ->pluck('customer_id')
                    ->unique();

                $query->where(function ($query) use ($authUser, $customerIds) {
                    $query->where(function ($query) use ($customerIds) {
                        $query->whereNotNull('customer_id')
                            ->whereIn('customer_id', $customerIds);
                    })->orWhere('id', $authUser->id);
                });
            })
            ->orderBy('id')
            ->get();

        $filename = 'pouzivatelia_' . now()->format('Y-m-d_His') . '.csv';

        $callback = function () use ($users, $attributes) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, array_map(fn ($key) => self::ATTRIBUTES[$key], $attributes), ';');

            foreach ($users as $user) {
                fputcsv($handle, array_map(fn ($key) => $this->resolveValue($user, $key), $attributes), ';');
            }

            fclose($handle);
        };

        return new StreamedResponse($callback, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function resolveValue(User $user, string $key): string
    {
        return match ($key) {
            'id'                => (string) $user->id,
            'firstName'         => (string) $user->firstName,
            'lastName'          => (string) $user->lastName,
            'username'          => (string) $user->username,
            'email'             => (string) $user->email,
            'phone'             => (string) $user->phone,
            'status'            => (string) ($user->status?->value ?? $user->status),
            'roles'             => $user->roles->pluck('name')->implode(', '),
            'orders_count'      => (string) $user->orders_count,
            'customer_company'  => (string) ($user->customer?->company ?? ''),
            'customer_ico'      => (string) ($user->customer?->ico ?? ''),
            'customer_city'     => (string) ($user->customer?->city ?? ''),
            'email_verified_at' => optional($user->email_verified_at)->format('Y-m-d H:i') ?? '',
            'created_at'        => optional($user->created_at)->format('Y-m-d H:i') ?? '',
            default             => '',
        };
    }
}
