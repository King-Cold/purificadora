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
        Schema::create('delivery_routes', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 50)->unique();
            $table->foreignId('driver_user_id')->constrained('users')->restrictOnDelete();
            $table->date('date')->index();
            $table->string('status', 30)->default('assigned')->index(); // assigned, in_progress, completed, cancelled
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('route_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_route_id')->constrained('delivery_routes')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->unsignedInteger('stop_number');
            $table->string('status', 30)->default('pending')->index(); // pending, completed, failed
            $table->string('failure_reason', 255)->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['delivery_route_id', 'order_id'], 'route_stops_route_order_unique');
            $table->unique(['delivery_route_id', 'stop_number'], 'route_stops_route_stop_num_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('route_stops');
        Schema::dropIfExists('delivery_routes');
    }
};

