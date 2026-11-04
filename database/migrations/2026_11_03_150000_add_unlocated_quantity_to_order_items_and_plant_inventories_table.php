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
        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedInteger('unlocated_quantity')->default(0)->after('loaded_quantity');
        });

        Schema::table('plant_inventories', function (Blueprint $table) {
            $table->unsignedInteger('exchange_unlocated')->default(0)->after('exchange_full_reserved');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('unlocated_quantity');
        });

        Schema::table('plant_inventories', function (Blueprint $table) {
            $table->dropColumn('exchange_unlocated');
        });
    }
};

