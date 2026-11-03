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
        Schema::table('truck_inventories', function (Blueprint $table) {
            $table->foreignId('delivery_route_id')->nullable()->change();
            $table->unsignedInteger('exchange_full_available')->default(0)->after('driver_user_id');
        });

        Schema::table('bottle_custody_movements', function (Blueprint $table) {
            $table->string('bottle_condition', 20)->default('full')->after('bottle_type');
            $table->string('status', 30)->default('accepted')->index()->after('destination');
            $table->unsignedInteger('proposed_quantity')->nullable()->after('quantity');
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete()->after('status');
            $table->timestamp('confirmed_at')->nullable()->after('confirmed_by_user_id');
            $table->text('difference_reason')->nullable()->after('confirmed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bottle_custody_movements', function (Blueprint $table) {
            $table->dropForeign(['confirmed_by_user_id']);
            $table->dropColumn([
                'bottle_condition',
                'status',
                'proposed_quantity',
                'confirmed_by_user_id',
                'confirmed_at',
                'difference_reason',
            ]);
        });

        Schema::table('truck_inventories', function (Blueprint $table) {
            $table->dropColumn('exchange_full_available');
            $table->foreignId('delivery_route_id')->nullable(false)->change();
        });
    }
};

