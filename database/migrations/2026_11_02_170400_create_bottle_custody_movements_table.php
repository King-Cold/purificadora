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
        // 1. Inventario por jornada/ruta de camión
        Schema::create('truck_inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_route_id')->constrained('delivery_routes')->cascadeOnDelete();
            $table->foreignId('driver_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('exchange_full_initial')->default(0);
            $table->unsignedInteger('exchange_full_delivered')->default(0);
            $table->unsignedInteger('exchange_empty_collected')->default(0);
            $table->timestamps();
        });

        // 2. Movimientos auditables de custodia física de envases
        Schema::create('bottle_custody_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('movement_type', 50)->index(); 
            // e.g.: truck_load_exchange, collection_fixed_from_customer, collection_exchange_empty, 
            // plant_reception_fixed, plant_prepared_fixed, truck_load_fixed, 
            // delivery_refill_to_customer, return_empty_fixed_to_customer
            $table->string('bottle_type', 20); // fijo, cambio
            $table->unsignedInteger('quantity');
            $table->string('origin', 50)->nullable(); // customer, truck, plant
            $table->string('destination', 50)->nullable(); // truck, plant, customer
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bottle_custody_movements');
        Schema::dropIfExists('truck_inventories');
    }
};

