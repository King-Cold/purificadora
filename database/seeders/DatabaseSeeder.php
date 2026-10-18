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
    }
}
