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
            $table->string('name', 150)->nullable()->change();
            if (!Schema::hasColumn('customers', 'max_credit_bottles')) {
                $table->unsignedSmallInteger('max_credit_bottles')
                    ->default(0)
                    ->after('has_deposit_bottles')
                    ->comment('Límite de garrafones que se le pueden fiar al cliente');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('name', 150)->nullable(false)->change();
            if (Schema::hasColumn('customers', 'max_credit_bottles')) {
                $table->dropColumn('max_credit_bottles');
            }
        });
    }
};
