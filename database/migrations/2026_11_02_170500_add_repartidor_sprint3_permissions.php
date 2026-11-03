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
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Asegurar que los permisos existen
        $permissions = [
            'routes.view',
            'routes.assign',
            'routes.deliver',
            'orders.view',
            'orders.create',
            'orders.dispatch',
            'customers.view',
            'customers.create',
            'finance.payments',
            'bottles.view',
            'bottles.manage',
        ];

        foreach ($permissions as $permName) {
            Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
        }

        // Asignar permisos adicionales para Repartidor de forma idempotente
        $repartidorRole = Role::where('name', 'Repartidor')->first();
        if ($repartidorRole) {
            $repartidorRole->givePermissionTo([
                'customers.view',
                'customers.create',
                'orders.view',
                'orders.create',
                'routes.view',
                'routes.deliver',
                'finance.payments',
                'bottles.view',
                'bottles.manage',
            ]);
        }

        // Asignar permisos al Administrador
        $adminRole = Role::where('name', 'Administrador')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo([
                'routes.view',
                'routes.assign',
                'routes.deliver',
                'orders.dispatch',
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // En migraciones de permisos preservamos la integridad sin remover permisos base
    }
};

