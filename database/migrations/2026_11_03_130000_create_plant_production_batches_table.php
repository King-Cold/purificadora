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
        // 1. Trazabilidad de cantidades de fijos ingresadas a lavado
        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedInteger('washing_quantity')->default(0)->after('received_at_plant_quantity');
        });

        // 2. Lotes de producción y purificación de botellones de cambio en planta
        Schema::create('plant_production_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_number', 50)->unique();
            $table->foreignId('operator_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('initial_quantity');
            $table->unsignedInteger('finished_quantity')->default(0);
            $table->unsignedInteger('damaged_quantity')->default(0);
            $table->string('status', 30)->default('in_process')->index(); // in_process, completed, cancelled
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plant_production_batches');

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('washing_quantity');
        });
    }
};

