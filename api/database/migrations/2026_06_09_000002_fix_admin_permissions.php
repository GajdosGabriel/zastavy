<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const ADMIN_PERMISSIONS = [
        'orders.viewAny', 'orders.view', 'orders.create', 'orders.update', 'orders.delete', 'orders.storno',
        'orderProducts.manage',
        'shippings.manage', 'shippings.notices',
        'users.viewAny', 'users.view', 'users.update',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate('admin', 'web')->syncPermissions(
            Permission::whereIn('name', self::ADMIN_PERMISSIONS)->get()
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $all = Permission::all();
        Role::findOrCreate('admin', 'web')->syncPermissions($all);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
