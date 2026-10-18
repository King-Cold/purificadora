<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Elimina de forma segura y reversible las columnas y tablas comerciales obsoletas
     * del módulo de clientes particulares de Puritronic.
     */
    public function up(): void
    {
        // 1. Eliminar tabla de auditoría de estados comerciales obsoleta
        if (Schema::hasTable('customer_status_histories')) {
            Schema::dropIfExists('customer_status_histories');
        }

        // 2. Eliminar columnas comerciales obsoletas de customers
        if (Schema::hasTable('customers')) {
            $driver = \Illuminate\Support\Facades\DB::getDriverName();
            if ($driver === 'sqlite') {
                \Illuminate\Support\Facades\DB::statement('DROP INDEX IF EXISTS customers_status_index');
            } else {
                try {
                    Schema::table('customers', function (Blueprint $table) {
                        $table->dropIndex(['status']);
                    });
                } catch (\Throwable $e) {
                    // Index already dropped or does not exist
                }
            }

            Schema::table('customers', function (Blueprint $table) {
                $obsoleteColumns = [
                    'status',
                    'max_credit_bottles',
                    'has_deposit_bottles',
                    'credit_limit',
                    'credit_days',
                    'price_tier',
                    'customer_type',
                    'tax_id',
                    'contact_name',
                ];

                $columnsToDrop = [];
                foreach ($obsoleteColumns as $column) {
                    if (Schema::hasColumn('customers', $column)) {
                        $columnsToDrop[] = $column;
                    }
                }

                if (!empty($columnsToDrop)) {
                    $table->dropColumn($columnsToDrop);
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     * Restaura las columnas y tablas para reversibilidad completa.
     */
    public function down(): void
    {
        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                if (!Schema::hasColumn('customers', 'contact_name')) {
                    $table->string('contact_name', 120)->nullable()->after('nickname');
                }
                if (!Schema::hasColumn('customers', 'tax_id')) {
                    $table->string('tax_id', 25)->nullable()->after('email');
                }
                if (!Schema::hasColumn('customers', 'customer_type')) {
                    $table->enum('customer_type', ['particular', 'comercio', 'empresa'])
                        ->default('particular')
                        ->after('tax_id');
                }
                if (!Schema::hasColumn('customers', 'price_tier')) {
                    $table->enum('price_tier', ['general', 'mayoreo', 'especial'])
                        ->default('general')
                        ->after('customer_type');
                }
                if (!Schema::hasColumn('customers', 'credit_limit')) {
                    $table->decimal('credit_limit', 10, 2)
                        ->default(0.00)
                        ->after('price_tier');
                }
                if (!Schema::hasColumn('customers', 'credit_days')) {
                    $table->unsignedSmallInteger('credit_days')
                        ->default(0)
                        ->after('credit_limit');
                }
                if (!Schema::hasColumn('customers', 'has_deposit_bottles')) {
                    $table->boolean('has_deposit_bottles')
                        ->default(false)
                        ->after('credit_days');
                }
                if (!Schema::hasColumn('customers', 'max_credit_bottles')) {
                    $table->unsignedSmallInteger('max_credit_bottles')
                        ->default(0)
                        ->after('has_deposit_bottles');
                }
                if (!Schema::hasColumn('customers', 'status')) {
                    $table->enum('status', ['active', 'suspended', 'inactive'])
                        ->default('active')
                        ->index()
                        ->after('max_credit_bottles');
                }
            });
        }

        if (!Schema::hasTable('customer_status_histories')) {
            Schema::create('customer_status_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->string('previous_status', 20)->default('active');
                $table->string('new_status', 20)->default('active');
                $table->string('reason', 500)->nullable();
                $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }
};
