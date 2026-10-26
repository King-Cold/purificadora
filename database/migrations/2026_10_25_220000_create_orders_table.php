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
        // 1. Tabla de pedidos (Cabecera)
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 30)->unique();
            
            // Relaciones con protección de integridad histórica
            $table->foreignId('customer_id')
                ->constrained('customers')
                ->restrictOnDelete();
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('customer_address_id')
                ->nullable()
                ->constrained('customer_addresses')
                ->nullOnDelete();

            // Copia histórica inmutable del domicilio y contacto al momento del pedido
            $table->string('recipient_name', 150);
            $table->string('recipient_phone', 25)->nullable();
            $table->string('delivery_street', 150);
            $table->string('delivery_exterior_number', 20)->nullable();
            $table->string('delivery_interior_number', 20)->nullable();
            $table->string('delivery_neighborhood', 100)->nullable();
            $table->string('delivery_city', 100)->default('Hocabá');
            $table->string('delivery_state', 100)->default('Yucatán');
            $table->string('delivery_postal_code', 10)->nullable();
            $table->string('delivery_between_streets', 200)->nullable();
            $table->text('delivery_reference')->nullable();
            $table->string('delivery_zone', 50)->nullable();
            $table->json('address_snapshot');

            // Estado y observaciones
            $table->string('status', 30)->default('pending')->index();
            $table->text('notes')->nullable();

            $table->timestamps();
        });

        // 2. Tabla de detalles del pedido (por modalidad: fijo / cambio)
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();
            $table->string('type', 20); // 'fijo' o 'cambio'
            $table->unsignedInteger('quantity');
            $table->timestamps();

            // Restricción única por pedido y tipo para evitar duplicidades
            $table->unique(['order_id', 'type'], 'order_items_order_type_unique');
        });

        // 3. Tabla de historial de transiciones de estado
        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();
            $table->string('previous_status', 30)->nullable();
            $table->string('new_status', 30);
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
