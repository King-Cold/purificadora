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
        // 1. Cargos por unidades efectivamente entregadas
        Schema::create('order_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->string('bottle_type', 20); // 'fijo' o 'cambio'
            $table->unsignedInteger('quantity_delivered');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_amount', 10, 2);
            $table->decimal('paid_amount', 10, 2)->default(0.00);
            $table->string('status', 30)->default('pending')->index(); // pending, partially_paid, paid, waived_promotion
            $table->timestamps();
        });

        // 2. Pagos y liquidaciones en efectivo
        Schema::create('order_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('received_by_user_id')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('payment_method', 30)->default('cash');
            $table->unsignedInteger('bottles_covered_count')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 3. Aplicación atómica de pagos a cargos para proteger trazabilidad
        Schema::create('payment_charge_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_payment_id')->constrained('order_payments')->cascadeOnDelete();
            $table->foreignId('order_charge_id')->constrained('order_charges')->cascadeOnDelete();
            $table->decimal('amount_applied', 10, 2);
            $table->unsignedInteger('bottles_count')->default(0);
            $table->timestamp('created_at')->useCurrent();
        });

        // 4. Promoción semanal (9 pagados = décimo gratis de 20L a domicilio)
        Schema::create('customer_promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('week_identifier', 15)->index(); // ej. '2026-W45'
            $table->date('week_start_date');
            $table->date('week_end_date');
            $table->timestamp('eligible_at');
            $table->timestamp('expires_at');
            $table->string('status', 30)->default('available')->index(); // available, redeemed, expired
            $table->foreignId('redeemed_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('redeemed_order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamps();

            // Garantiza que un cliente reciba a lo sumo 1 botellón gratuito por semana
            $table->unique(['customer_id', 'week_identifier'], 'cust_promo_week_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_promotions');
        Schema::dropIfExists('payment_charge_allocations');
        Schema::dropIfExists('order_payments');
        Schema::dropIfExists('order_charges');
    }
};

