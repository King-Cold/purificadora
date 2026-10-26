<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('branch_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('opening_time', 5)->default('08:00')->comment('Hora de apertura (HH:mm)');
            $table->string('break_start_time', 5)->default('13:00')->comment('Hora de inicio de descanso (HH:mm)');
            $table->string('break_end_time', 5)->default('15:00')->comment('Hora de fin de descanso (HH:mm)');
            $table->string('closing_time', 5)->default('19:00')->comment('Hora de cierre (HH:mm)');
            $table->unsignedSmallInteger('order_cutoff_advance_minutes')->default(60)->comment('Minutos de anticipación previos al cierre para cortar pedidos');
            $table->timestamps();
        });

        // Inicializar horario únicamente si la tabla está vacía para no sobrescribir configuraciones futuras
        if (DB::table('branch_schedules')->count() === 0) {
            DB::table('branch_schedules')->insert([
                'id' => 1,
                'opening_time' => '08:00',
                'break_start_time' => '13:00',
                'break_end_time' => '15:00',
                'closing_time' => '19:00',
                'order_cutoff_advance_minutes' => 60,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branch_schedules');
    }
};
