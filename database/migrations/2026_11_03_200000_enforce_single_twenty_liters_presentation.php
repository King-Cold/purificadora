<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Limpiar presentaciones de otras capacidades no soportadas (ej. 10L) de forma segura
        DB::table('bottle_presentations')
            ->where('capacity', '!=', 20.00)
            ->delete();

        // 2. Si existen duplicados de 20 litros, conservar el principal (menor id o el que tenga precios configurados)
        $twentyLiterRecords = DB::table('bottle_presentations')
            ->where('capacity', 20.00)
            ->orderBy('id', 'asc')
            ->get();

        if ($twentyLiterRecords->count() > 1) {
            $keepId = $twentyLiterRecords->first()->id;
            DB::table('bottle_presentations')
                ->where('capacity', 20.00)
                ->where('id', '!=', $keepId)
                ->delete();
        }

        // 3. Garantizar que exista exactamente el registro oficial del botellón de 20 litros
        $existing = DB::table('bottle_presentations')->first();
        if (!$existing) {
            DB::table('bottle_presentations')->insert([
                'id' => 1,
                'name' => 'Botellón 20 Litros',
                'capacity' => 20.00,
                'unit' => 'Litros',
                'refill_price' => 15.00,
                'store_price' => 10.00,
                'new_bottle_price' => 95.00,
                'image_path' => '/images/garrafon-20l.png',
                'is_available_for_delivery' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('bottle_presentations')
                ->where('id', $existing->id)
                ->update([
                    'capacity' => 20.00,
                    'unit' => 'Litros',
                    'is_active' => true,
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reversible: se mantiene la estructura
    }
};

