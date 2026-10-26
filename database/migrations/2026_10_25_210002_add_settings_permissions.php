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

        // 1. Crear permisos de forma idempotente
        $viewPermission = Permission::firstOrCreate([
            'name' => 'settings.view',
            'guard_name' => 'web',
        ]);

        $managePermission = Permission::firstOrCreate([
            'name' => 'settings.manage',
            'guard_name' => 'web',
        ]);

        // 2. Asignar a Administrador
        $adminRole = Role::where('name', 'Administrador')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo([$viewPermission, $managePermission]);
        }

        // 3. Asignar a Super Admin
        $superAdminRole = Role::where('name', 'Super Admin')->first();
        if ($superAdminRole) {
            $superAdminRole->givePermissionTo([$viewPermission, $managePermission]);
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
            $adminRole->revokePermissionTo(['settings.view', 'settings.manage']);
        }

        $superAdminRole = Role::where('name', 'Super Admin')->first();
        if ($superAdminRole) {
            $superAdminRole->revokePermissionTo(['settings.view', 'settings.manage']);
        }

        Permission::whereIn('name', ['settings.view', 'settings.manage'])->delete();
    }
};
