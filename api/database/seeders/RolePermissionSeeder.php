<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'orders.view',
            'orders.manage',
            'products.view',
            'products.manage',
            'stocks.view',
            'stocks.manage',
            'customers.view',
            'customers.manage',
            'categories.view',
            'categories.manage',
            'settings.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $superAdmin = Role::findOrCreate('super-admin', 'web');
        $admin = Role::findOrCreate('admin', 'web');
        $manager = Role::findOrCreate('manager', 'web');
        $sales = Role::findOrCreate('sales', 'web');
        $warehouse = Role::findOrCreate('warehouse', 'web');
        Role::findOrCreate('customer', 'web');

        $superAdmin->givePermissionTo(Permission::all());
        $admin->givePermissionTo([
            'orders.view',
            'orders.manage',
            'products.view',
            'products.manage',
            'stocks.view',
            'stocks.manage',
            'customers.view',
            'customers.manage',
            'categories.view',
            'categories.manage',
        ]);
        $manager->givePermissionTo([
            'orders.view',
            'orders.manage',
            'products.view',
            'products.manage',
            'stocks.view',
            'customers.view',
            'categories.view',
        ]);
        $sales->givePermissionTo([
            'orders.view',
            'orders.manage',
            'products.view',
            'customers.view',
            'customers.manage',
        ]);
        $warehouse->givePermissionTo([
            'orders.view',
            'stocks.view',
            'stocks.manage',
            'products.view',
        ]);

        User::query()
            ->orderBy('id')
            ->get()
            ->each(function (User $user, int $index) use ($superAdmin, $admin) {
                $user->syncRoles([$index === 0 ? $superAdmin : $admin]);
            });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
