<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $superAdmin = Role::findOrCreate('super-admin', 'web');
        $admin = Role::findOrCreate('admin', 'web');

        User::query()
            ->orderBy('id')
            ->get()
            ->each(function (User $user, int $index) use ($superAdmin, $admin) {
                $user->syncRoles([$index === 0 ? $superAdmin : $admin]);
            });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        User::query()
            ->orderBy('id')
            ->get()
            ->each(function (User $user) {
                $user->syncRoles([]);
            });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
