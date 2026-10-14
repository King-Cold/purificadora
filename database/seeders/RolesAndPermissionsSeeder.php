<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Definición de permisos por módulo
        $permissions = [
            // Módulo de Clientes y Domicilios
            'customers.view',
            'customers.create',
            'customers.update',
            'customers.delete',

            // Módulo de Pedidos y Ventas
            'orders.view',
            'orders.create',
            'orders.update',
            'orders.cancel',
            'orders.dispatch',

            // Módulo de Control de Envases y Comodatos
            'bottles.view',
            'bottles.manage',
            'bottles.adjust',

            // Módulo de Inventario y Planta
            'inventory.view',
            'inventory.manage',
            'inventory.adjust',

            // Módulo de Rutas y Reparto
            'routes.view',
            'routes.assign',
            'routes.deliver',

            // Módulo de Finanzas y Cobranza
            'finance.view',
            'finance.manage_credits',
            'finance.payments',

            // Módulo de Reportes
            'reports.sales',
            'reports.inventory',
            'reports.routes',

            // Módulo de Usuarios y Sistema
            'users.view',
            'users.manage',
            'roles.manage',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        // 2. Creación y sincronización de los 4 Roles Oficiales + Super Admin Técnico

        // Super Admin: Exclusivamente técnico, acceso total
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::all());

        // Perfil 1: Administrador (Gestión general del negocio, ventas, pedidos, finanzas, inventario y personal)
        $admin = Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'web']);
        $admin->syncPermissions([
            'customers.view', 'customers.create', 'customers.update', 'customers.delete',
            'orders.view', 'orders.create', 'orders.update', 'orders.cancel', 'orders.dispatch',
            'bottles.view', 'bottles.manage', 'bottles.adjust',
            'inventory.view', 'inventory.manage', 'inventory.adjust',
            'routes.view', 'routes.assign', 'routes.deliver',
            'finance.view', 'finance.manage_credits', 'finance.payments',
            'reports.sales', 'reports.inventory', 'reports.routes',
            'users.view', 'users.manage',
        ]);

        // Perfil 2: Repartidor (Operaciones de reparto y recolección de envases en ruta)
        $driver = Role::firstOrCreate(['name' => 'Repartidor', 'guard_name' => 'web']);
        $driver->syncPermissions([
            'orders.view',
            'routes.view',
            'routes.deliver',
            'bottles.view',
            'bottles.manage',
            'finance.payments',
        ]);

        // Perfil 3: Operador Planta (Actividades de producción, llenado e insumos)
        $plantOperator = Role::firstOrCreate(['name' => 'Operador Planta', 'guard_name' => 'web']);
        $plantOperator->syncPermissions([
            'inventory.view',
            'inventory.manage',
            'bottles.view',
            'bottles.manage',
        ]);

        // Perfil 4: Cliente (Acceso a su propia información comercial y pedidos)
        $customerRole = Role::firstOrCreate(['name' => 'Cliente', 'guard_name' => 'web']);
        $customerRole->syncPermissions([
            'orders.view',
            'orders.create',
        ]);

        // 3. Manejo de 'Vendedor / Mostrador' (retirado del alcance oficial)
        $sellerRole = Role::where('name', 'Vendedor / Mostrador')->first();
        if ($sellerRole) {
            // Verificamos si tiene usuarios asignados antes de cualquier acción
            if ($sellerRole->users()->count() === 0) {
                // Desvinculamos permisos y removemos el rol huérfano de la base de datos
                $sellerRole->syncPermissions([]);
                $sellerRole->delete();
            }
        }
    }
}
