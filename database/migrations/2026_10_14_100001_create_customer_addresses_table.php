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
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();

            $table->string('label', 60)->default('Principal')->comment('Etiqueta o alias (ej. Casa, Local 2, Planta Alta)');
            $table->string('street', 150)->comment('Calle');
            $table->string('exterior_number', 25)->comment('Número exterior');
            $table->string('interior_number', 25)->nullable()->comment('Número interior, departamento o edificio');
            $table->string('neighborhood', 100)->index()->comment('Colonia o barrio');
            $table->string('postal_code', 10)->nullable()->index()->comment('Código postal');
            $table->string('city', 80)->default('Localidad')->comment('Ciudad o municipio');
            $table->string('state', 80)->default('Estado')->comment('Estado o provincia');

            $table->string('between_streets', 200)->nullable()->comment('Calles de referencia cruzada');
            $table->text('reference')->nullable()->comment('Detalles visuales para el repartidor (color fachada, reja, etc.)');

            $table->decimal('latitude', 10, 8)->nullable()->comment('Coordenada GPS de latitud');
            $table->decimal('longitude', 11, 8)->nullable()->comment('Coordenada GPS de longitud');

            $table->string('delivery_zone', 60)->nullable()->index()->comment('Zona o cuadrante de reparto asignado');
            $table->string('preferred_time_window', 80)->nullable()->comment('Ventana u horario preferente de recepción');

            $table->boolean('is_default')->default(false)->index()->comment('Si es el domicilio predeterminado para pedidos');

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};
