<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Roles y Permisos de Spatie
        $this->call(RolesAndPermissionsSeeder::class);

        // 2. Cuentas únicas de personal
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@purificadora.com'],
            [
                'name' => 'Administrador',
                'password' => bcrypt('password'),
                'status' => 'active',
            ]
        );
        if (!$adminUser->hasRole('Administrador') && !$adminUser->hasRole('Super Admin')) {
            $adminUser->assignRole('Administrador');
        }

        $repartidorUser = User::firstOrCreate(
            ['email' => 'repartidor@purificadora.com'],
            [
                'name' => 'Repartidor',
                'password' => bcrypt('password'),
                'status' => 'active',
            ]
        );
        if (!$repartidorUser->hasRole('Repartidor')) {
            $repartidorUser->assignRole('Repartidor');
        }

        $llenadorUser = User::firstOrCreate(
            ['email' => 'planta@purificadora.com'],
            [
                'name' => 'Llenador',
                'password' => bcrypt('password'),
                'status' => 'active',
            ]
        );
        if (!$llenadorUser->hasRole('Operador Planta')) {
            $llenadorUser->assignRole('Operador Planta');
        }

        // 3. Catálogo oficial de presentaciones de garrafones (preserva precios editados sin sobrescribir)
        $pres20 = \App\Models\BottlePresentation::firstOrCreate(
            ['name' => 'Garrafón 20 Litros'],
            [
                'capacity' => 20.00,
                'unit' => 'Litros',
                'refill_price' => 15.00,
                'store_price' => 10.00,
                'new_bottle_price' => 95.00,
                'image_path' => '/images/garrafon-20l.png',
                'is_available_for_delivery' => true,
                'is_active' => true,
            ]
        );
        if (!$pres20->wasRecentlyCreated && !$pres20->is_available_for_delivery) {
            $pres20->is_available_for_delivery = true;
            $pres20->save();
        }

        $pres10 = \App\Models\BottlePresentation::firstOrCreate(
            ['name' => 'Garrafón 10 Litros'],
            [
                'capacity' => 10.00,
                'unit' => 'Litros',
                'refill_price' => null, // Exclusivo de sucursal
                'store_price' => 5.00,
                'new_bottle_price' => 65.00,
                'image_path' => '/images/garrafon-10l.png',
                'is_available_for_delivery' => false,
                'is_active' => true,
            ]
        );
        if (!$pres10->wasRecentlyCreated && $pres10->is_available_for_delivery) {
            $pres10->is_available_for_delivery = false;
            $pres10->save();
        }
    }
}
