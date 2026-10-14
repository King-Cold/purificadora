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
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedInteger('fixed_bottles_count')
                ->default(0)
                ->after('notes')
                ->comment('Cantidad de botellones fijos registrados (rotulados con el nombre del cliente)');

            $table->unsignedInteger('exchange_bottles_count')
                ->default(0)
                ->after('fixed_bottles_count')
                ->comment('Cantidad de botellones de cambio registrados (intercambiables sin rotulación)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['fixed_bottles_count', 'exchange_bottles_count']);
        });
    }
};
