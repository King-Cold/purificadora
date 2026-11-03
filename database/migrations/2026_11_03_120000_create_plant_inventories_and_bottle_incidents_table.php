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
        // 1. Inventario continuo de planta para botellones de cambio genéricos
        Schema::create('plant_inventories', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('exchange_empty_available')->default(0);
            $table->unsignedInteger('exchange_washing_filling')->default(0);
            $table->unsignedInteger('exchange_full_available')->default(0);
            $table->unsignedInteger('exchange_full_reserved')->default(0);

            // Conteo inicial físico
            $table->timestamp('initial_count_completed_at')->nullable();
            $table->foreignId('initial_count_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('initial_empty_count')->nullable();
            $table->unsignedInteger('initial_full_count')->nullable();
            $table->text('initial_count_notes')->nullable();

            $table->timestamps();
        });

        // 2. Registro de incidencias y botellones dañados (fijos y de cambio)
        Schema::create('bottle_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->foreignId('reported_by_user_id')->constrained('users')->restrictOnDelete();

            $table->string('bottle_type', 20); // fijo, cambio
            $table->string('bottle_condition', 20)->default('empty'); // empty, full
            $table->unsignedInteger('quantity');
            $table->string('stage', 40); // recepcion, lavado, llenado, almacen, carga
            $table->string('damage_type', 40); // rotura, fisura, contaminacion, deformacion, otro
            $table->string('status', 40)->default('pendiente_resolucion'); // pendiente_resolucion, resuelta, descartada
            $table->text('notes');

            // Resolución por parte de Administración
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();

            $table->timestamps();
        });

        // 3. Trazabilidad de fijos dañados en el pedido
        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedInteger('damaged_quantity')->default(0)->after('received_at_plant_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('damaged_quantity');
        });

        Schema::dropIfExists('bottle_incidents');
        Schema::dropIfExists('plant_inventories');
    }
};

