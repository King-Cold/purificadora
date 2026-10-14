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

        // 2. Usuario Administrador Inicial (si no existe)
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@purificadora.com'],
            [
                'name' => 'Administrador General',
                'password' => bcrypt('password'),
            ]
        );

        if (!$adminUser->hasRole('Super Admin')) {
            $adminUser->assignRole('Super Admin');
        }
    }
}
