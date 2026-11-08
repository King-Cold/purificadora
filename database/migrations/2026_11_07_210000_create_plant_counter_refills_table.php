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
        Schema::create('plant_counter_refills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operator_id')
                ->constrained('users')
                ->cascadeOnDelete()
                ->comment('Operador de planta que registró el llenado de mostrador');
            $table->unsignedInteger('quantity')->default(1)->comment('Cantidad de botellones de 20L llenados');
            $table->decimal('unit_price', 10, 2)->comment('Precio unitario de planta aplicado');
            $table->decimal('total_amount', 10, 2)->comment('Importe total calculado en servidor');
            $table->string('notes', 500)->nullable()->comment('Observaciones opcionales');
            $table->timestamps();

            $table->index(['created_at', 'operator_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plant_counter_refills');
    }
};

