<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CustomerStatusHistory extends Model
{
    use HasFactory;

    protected $table = 'customer_status_histories';

    protected $fillable = [
        'customer_id',
        'previous_status',
        'new_status',
        'reason',
        'changed_by',
    ];

    /**
     * Cliente al que pertenece el registro de cambio.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Usuario que efectuó el cambio administrativo.
     */
    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Registra un nuevo evento en el historial garantizando la existencia de la tabla.
     */
    public static function logChange(Customer $customer, string $previousStatus, string $newStatus, ?string $reason = null, ?int $changedBy = null): self
    {
        if (!Schema::hasTable('customer_status_histories')) {
            Schema::create('customer_status_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->string('previous_status', 20);
                $table->string('new_status', 20);
                $table->text('reason')->nullable();
                $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        return static::create([
            'customer_id' => $customer->id,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'reason' => $reason ? trim($reason) : null,
            'changed_by' => $changedBy,
        ]);
    }
}
