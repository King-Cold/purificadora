<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderReview extends Model
{
    use HasFactory;

    /**
     * Tabla asociada.
     *
     * @var string
     */
    protected $table = 'order_reviews';

    /**
     * Atributos asignables en masa.
     *
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'customer_id',
        'user_id',
        'rating',
        'comment',
        'show_on_landing',
        'featured_at',
        'reviewed_by_user_id',
    ];

    /**
     * Casts de atributos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'show_on_landing' => 'boolean',
            'featured_at' => 'datetime',
        ];
    }

    /**
     * Pedido calificado.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Cliente autor de la calificación.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Usuario autenticado que envió la reseña.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Administrador que destacó u ocultó el comentario en portada.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
