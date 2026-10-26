<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Limpiar caché de permisos
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Crear permiso de forma idempotente
        $confirmPermission = Permission::firstOrCreate([
            'name' => 'orders.confirm',
            'guard_name' => 'web',
        ]);

        // 2. Asignar a Administrador
        $adminRole = Role::where('name', 'Administrador')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo($confirmPermission);
        }

        // 3. Asignar a Super Admin
        $superAdminRole = Role::where('name', 'Super Admin')->first();
        if ($superAdminRole) {
            $superAdminRole->givePermissionTo($confirmPermission);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $adminRole = Role::where('name', 'Administrador')->first();
        if ($adminRole) {
            $adminRole->revokePermissionTo('orders.confirm');
        }

        $superAdminRole = Role::where('name', 'Super Admin')->first();
        if ($superAdminRole) {
            $superAdminRole->revokePermissionTo('orders.confirm');
        }

        Permission::where('name', 'orders.confirm')->delete();
    }
};
