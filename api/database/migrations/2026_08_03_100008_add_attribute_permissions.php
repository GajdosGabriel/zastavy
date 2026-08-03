<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSIONS = [
        'attributes.viewAny',
        'attributes.manage',
    ];

    /** Role, ktoré smú taxonómiu čítať (potrebujú ju v editore produktu). */
    private const READ_ROLES = ['manager', 'sales', 'warehouse'];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }

        Role::findOrCreate('super-admin', 'web')
            ->givePermissionTo(Permission::whereIn('name', self::PERMISSIONS)->get());

        Role::findOrCreate('admin', 'web')
            ->givePermissionTo(Permission::whereIn('name', self::PERMISSIONS)->get());

        foreach (self::READ_ROLES as $roleName) {
            Role::findOrCreate($roleName, 'web')
                ->givePermissionTo(Permission::findOrCreate('attributes.viewAny', 'web'));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::whereIn('name', self::PERMISSIONS)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
