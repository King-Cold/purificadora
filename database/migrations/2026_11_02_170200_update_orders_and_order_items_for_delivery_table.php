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
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('delivery_route_id')
                ->nullable()
                ->after('customer_address_id')
                ->constrained('delivery_routes')
                ->nullOnDelete();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedInteger('delivered_quantity')->default(0)->after('quantity');
            $table->unsignedInteger('cancelled_quantity')->default(0)->after('delivered_quantity');
            $table->string('cancellation_reason', 255)->nullable()->after('cancelled_quantity');
            $table->decimal('unit_price', 10, 2)->nullable()->after('cancellation_reason');
            
            // Trazabilidad de custodia física
            $table->unsignedInteger('collected_quantity')->default(0)->after('unit_price');
            $table->unsignedInteger('received_at_plant_quantity')->default(0)->after('collected_quantity');
            $table->unsignedInteger('prepared_quantity')->default(0)->after('received_at_plant_quantity');
            $table->unsignedInteger('loaded_quantity')->default(0)->after('prepared_quantity');
            $table->unsignedInteger('returned_empty_quantity')->default(0)->after('loaded_quantity');
            $table->string('status', 50)->default('pending')->after('returned_empty_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn([
                'delivered_quantity',
                'cancelled_quantity',
                'cancellation_reason',
                'unit_price',
                'collected_quantity',
                'received_at_plant_quantity',
                'prepared_quantity',
                'loaded_quantity',
                'returned_empty_quantity',
                'status',
            ]);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivery_route_id');
        });
    }
};

