<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bottle_presentations', function (Blueprint $table) {
            $table->decimal('store_price', 10, 2)->nullable()->after('refill_price')->comment('Precio de rellenado en sucursal en MXN');
            $table->string('image_path', 255)->nullable()->after('new_bottle_price')->comment('Ruta o URL de la imagen del envase');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bottle_presentations', function (Blueprint $table) {
            $table->dropColumn(['store_price', 'image_path']);
        });
    }
};
