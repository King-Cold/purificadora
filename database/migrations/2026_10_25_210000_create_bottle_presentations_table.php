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
        Schema::create('bottle_presentations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('Nombre de la presentación del envase (ej. Garrafón Estándar)');
            $table->decimal('capacity', 8, 2)->comment('Capacidad numérica mayor que cero');
            $table->string('unit', 20)->default('L')->comment('Unidad de medida (ej. L, ml, gal)');
            $table->decimal('refill_price', 10, 2)->nullable()->comment('Precio de rellenado en MXN (opcional)');
            $table->decimal('new_bottle_price', 10, 2)->nullable()->comment('Precio de venta de envase nuevo en MXN (opcional)');
            $table->boolean('is_active')->default(true)->index()->comment('Estado activo o inactivo');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bottle_presentations');
    }
};
