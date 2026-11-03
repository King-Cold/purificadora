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
            $table->string('delivery_street', 150)->nullable()->change();
            $table->string('delivery_city', 100)->nullable()->change();
            $table->string('delivery_state', 100)->nullable()->change();
        });

        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->string('exterior_number', 25)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->string('exterior_number', 25)->nullable(false)->change();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_street', 150)->nullable(false)->change();
            $table->string('delivery_city', 100)->nullable(false)->change();
            $table->string('delivery_state', 100)->nullable(false)->change();
        });
    }
};

