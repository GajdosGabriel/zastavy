<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSIONS = [
        // Objednávky
        'orders.viewAny',
        'orders.view',
        'orders.create',
        'orders.update',
        'orders.delete',
        'orders.storno',

        // Položky objednávok
        'orderProducts.manage',

        // Expedície
        'shippings.manage',
        'shippings.notices',

        // Zákazníci
        'customers.viewAny',
        'customers.view',
        'customers.create',
        'customers.update',
        'customers.delete',

        // Produkty
        'products.viewAny',
        'products.view',
        'products.create',
        'products.update',
        'products.delete',

        // Sklad
        'stocks.viewAny',
        'stocks.create',
        'stocks.update',
        'stocks.delete',

        // Používatelia
        'users.viewAny',
        'users.view',
        'users.update',

        // Oznamy
        'announcements.manage',

        // Kategórie
        'categories.manage',
    ];

    private const ADMIN_PERMISSIONS = [
        'orders.viewAny', 'orders.view', 'orders.create', 'orders.update', 'orders.delete', 'orders.storno',
        'orderProducts.manage',
        'shippings.manage', 'shippings.notices',
        'users.viewAny', 'users.view', 'users.update',
    ];

    private const ROLE_PERMISSIONS = [
        'manager' => [
            'orders.viewAny', 'orders.view', 'orders.create', 'orders.update', 'orders.delete', 'orders.storno',
            'orderProducts.manage',
            'shippings.manage', 'shippings.notices',
            'customers.viewAny', 'customers.view',
            'products.viewAny', 'products.view',
            'stocks.viewAny', 'stocks.create', 'stocks.update', 'stocks.delete',
        ],
        'sales' => [
            'orders.viewAny', 'orders.view', 'orders.create', 'orders.update',
            'orderProducts.manage',
            'customers.viewAny', 'customers.view', 'customers.create', 'customers.update',
            'products.viewAny', 'products.view',
        ],
        'warehouse' => [
            'orders.viewAny', 'orders.view',
            'orderProducts.manage',
            'shippings.manage', 'shippings.notices',
            'stocks.viewAny', 'stocks.create', 'stocks.update', 'stocks.delete',
            'products.viewAny', 'products.view',
        ],
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $allPermissions = Permission::whereIn('name', self::PERMISSIONS)->get();
        $adminPermissions = Permission::whereIn('name', self::ADMIN_PERMISSIONS)->get();

        Role::findOrCreate('super-admin', 'web')->syncPermissions($allPermissions);
        Role::findOrCreate('admin', 'web')->syncPermissions($adminPermissions);

        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            Role::findOrCreate($roleName, 'web')->syncPermissions(
                Permission::whereIn('name', $permissions)->get()
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['super-admin', 'admin', 'manager', 'sales', 'warehouse'] as $roleName) {
            Role::findByName($roleName, 'web')?->syncPermissions([]);
        }

        Permission::whereIn('name', self::PERMISSIONS)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
