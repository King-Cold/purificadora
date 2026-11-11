<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Elimina cualquier asignación mixta existente en la base de datos para que
     * ningún cliente tenga botellones fijos y de cambio a la vez.
     */
    public function up(): void
    {
        // Si un cliente tiene ambos conteos positivos, se conserva el mayor (o fijos si es igual/mayor) y se pone el otro en cero
        DB::table('customers')
            ->where('fixed_bottles_count', '>', 0)
            ->where('exchange_bottles_count', '>', 0)
            ->whereRaw('fixed_bottles_count >= exchange_bottles_count')
            ->update(['exchange_bottles_count' => 0]);

        DB::table('customers')
            ->where('fixed_bottles_count', '>', 0)
            ->where('exchange_bottles_count', '>', 0)
            ->whereRaw('exchange_bottles_count > fixed_bottles_count')
            ->update(['fixed_bottles_count' => 0]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reversible: los clientes no pueden volver a modalidad mixta
    }
};

