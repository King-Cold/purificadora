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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('code', 30)->unique()->comment('Código interno único de cliente (ej. CLI-0001)');
            $table->string('name', 150)->index()->comment('Nombre completo o razón social');
            $table->string('contact_name', 120)->nullable()->comment('Persona de contacto en negocio/empresa');
            $table->string('phone', 25)->index()->comment('Teléfono principal para llamadas y pedidos');
            $table->string('secondary_phone', 25)->nullable()->comment('Teléfono secundario o WhatsApp');
            $table->string('email', 120)->nullable()->index()->comment('Correo electrónico de facturación o contacto');
            $table->string('tax_id', 25)->nullable()->comment('RFC o identificación tributaria');

            $table->enum('customer_type', ['particular', 'comercio', 'empresa'])
                ->default('particular')
                ->comment('Tipo de cliente según el giro');

            $table->enum('price_tier', ['general', 'mayoreo', 'especial'])
                ->default('general')
                ->comment('Lista o tabulador de precio asignado');

            $table->decimal('credit_limit', 10, 2)
                ->default(0.00)
                ->comment('Límite de crédito disponible para compras');

            $table->unsignedSmallInteger('credit_days')
                ->default(0)
                ->comment('Días de crédito concedidos');

            $table->boolean('has_deposit_bottles')
                ->default(false)
                ->comment('Indica si el cliente maneja préstamo o comodato de garrafones');

            $table->enum('status', ['active', 'suspended', 'inactive'])
                ->default('active')
                ->index()
                ->comment('Estado actual de la cuenta del cliente');

            $table->text('notes')->nullable()->comment('Notas comerciales o administrativas internas');

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
