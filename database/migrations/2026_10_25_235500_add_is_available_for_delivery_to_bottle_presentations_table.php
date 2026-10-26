<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bottle_presentations', function (Blueprint $table) {
            $table->boolean('is_available_for_delivery')->default(false)->after('store_price')->comment('Indica si la presentación está disponible para pedidos a domicilio (exclusivo 20L)');
        });

        // Habilitar inicialmente para la presentación existente de 20 litros y deshabilitar para las demás
        DB::table('bottle_presentations')
            ->where('capacity', 20)
            ->update(['is_available_for_delivery' => true]);

        DB::table('bottle_presentations')
            ->where('capacity', '!=', 20)
            ->update(['is_available_for_delivery' => false]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bottle_presentations', function (Blueprint $table) {
            $table->dropColumn('is_available_for_delivery');
        });
    }
};
