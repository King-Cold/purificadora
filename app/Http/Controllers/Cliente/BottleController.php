<?php

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BottleController extends Controller
{
    /**
     * Actualiza la cantidad de botellones de 20L del cliente autenticado.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user() ?: Auth::user();

        if (!$user) {
            abort(401, 'No autenticado.');
        }

        $validated = $request->validate([
            'fixed_bottles_count' => ['required', 'integer', 'min:0', 'max:500'],
            'exchange_bottles_count' => ['required', 'integer', 'min:0', 'max:500'],
        ], [
            'fixed_bottles_count.required' => 'La cantidad de botellones fijos es obligatoria.',
            'fixed_bottles_count.integer' => 'La cantidad debe ser un número entero.',
            'fixed_bottles_count.min' => 'La cantidad no puede ser negativa.',
            'exchange_bottles_count.required' => 'La cantidad de botellones de cambio es obligatoria.',
            'exchange_bottles_count.integer' => 'La cantidad debe ser un número entero.',
            'exchange_bottles_count.min' => 'La cantidad no puede ser negativa.',
        ]);

        $customer = $user->customer;
        if (!$customer) {
            $customer = Customer::create([
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'customer_type' => 'particular',
                'price_tier' => 'general',
                'status' => 'active',
                'fixed_bottles_count' => 0,
                'exchange_bottles_count' => 0,
            ]);
        }

        $customer->update([
            'fixed_bottles_count' => (int) $validated['fixed_bottles_count'],
            'exchange_bottles_count' => (int) $validated['exchange_bottles_count'],
        ]);

        return back()->with('success', 'Tus garrafones de 20 litros han sido actualizados correctamente.');
    }
}
