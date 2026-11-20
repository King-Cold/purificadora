<?php

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrderReviewController extends Controller
{
    /**
     * Guarda la calificación con estrellas y comentario de un pedido del cliente.
     */
    public function store(Request $request, Order $order): RedirectResponse
    {
        $user = $request->user();
        $customer = $user?->customer;

        if (!$customer || (int) $order->customer_id !== (int) $customer->id) {
            abort(403, 'No tienes permiso para calificar este pedido.');
        }

        if ($order->isCancelled()) {
            return redirect()->route('cliente.orders.show', $order->id)
                ->with('error', 'No es posible calificar un pedido cancelado.');
        }

        if ($order->review()->exists()) {
            return redirect()->route('cliente.orders.show', $order->id)
                ->with('error', 'Este pedido ya cuenta con una calificación registrada.');
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'rating.required' => 'Selecciona una puntuación de 1 a 5 estrellas.',
            'rating.min' => 'La puntuación mínima es de 1 estrella.',
            'rating.max' => 'La puntuación máxima es de 5 estrellas.',
            'comment.required' => 'Por favor escribe un breve comentario sobre tu experiencia.',
            'comment.min' => 'Tu comentario debe tener al menos 3 caracteres.',
            'comment.max' => 'Tu comentario no puede superar los 500 caracteres.',
        ]);

        OrderReview::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'rating' => (int) $validated['rating'],
            'comment' => trim($validated['comment']),
            'show_on_landing' => false,
        ]);

        return redirect()->route('cliente.orders.show', $order->id)
            ->with('success', '¡Gracias por calificar tu pedido! Tu comentario fue enviado correctamente.');
    }
}
