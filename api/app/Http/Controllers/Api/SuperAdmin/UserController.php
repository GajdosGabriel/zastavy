<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Enums\ModelStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserUpdateRequest;
use App\Http\Resources\UserIndexResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::query()
            ->with(['roles', 'customer'])
            ->withCount('orders')
            ->when($request->filled('bySearchInput'), function ($query) use ($request) {
                $search = $request->string('bySearchInput')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('firstName', 'like', '%' . $search . '%')
                        ->orWhere('lastName', 'like', '%' . $search . '%')
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
            ->latest()
            ->paginate();

        return UserIndexResource::collection($users);
    }

    public function show(User $user)
    {
        return (new UserIndexResource($user->load(['roles', 'customer'])->loadCount('orders')))
            ->additional($this->formOptions());
    }

    public function update(UserUpdateRequest $request, User $user)
    {
        $validated = $request->validated();
        $roles = $validated['roles'] ?? null;
        unset($validated['roles']);

        $validated['name'] = trim(($validated['firstName'] ?? '') . ' ' . ($validated['lastName'] ?? ''));
        $validated['username'] = $validated['username'] ?: $validated['name'];
        $validated['slug'] = Str::slug($validated['username']);

        DB::transaction(function () use ($user, $validated, $roles) {
            $user->update($validated);

            if ($request->user()?->hasAnyRole(['admin', 'super-admin']) && is_array($roles)) {
                $user->syncRoles($roles);
            }
        });

        return (new UserIndexResource($user->refresh()->load(['roles', 'customer'])->loadCount('orders')))
            ->additional($this->formOptions());
    }

    private function formOptions(): array
    {
        return [
            'meta' => [
                'roles' => request()->user()?->hasAnyRole(['admin', 'super-admin'])
                    ? Role::query()
                        ->orderBy('name')
                        ->pluck('name')
                        ->map(fn (string $role) => [
                            'value' => $role,
                            'label' => $role,
                        ])
                        ->values()
                    : [],
                'statuses' => ModelStatus::allowedForUser(request()->user()),
            ],
        ];
    }
}
