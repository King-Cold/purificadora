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
            'bottle_type' => ['nullable', 'string', 'in:fixed,exchange,none'],
            'bottles_count' => ['nullable', 'integer', 'min:0', 'max:500'],
            'fixed_bottles_count' => ['nullable', 'integer', 'min:0', 'max:500'],
            'exchange_bottles_count' => ['nullable', 'integer', 'min:0', 'max:500'],
        ]);

        if (isset($validated['bottle_type'])) {
            if ($validated['bottle_type'] === Customer::BOTTLE_TYPE_FIXED) {
                $fixedCount = max(0, (int) ($validated['bottles_count'] ?? $validated['fixed_bottles_count'] ?? 0));
                $exchangeCount = 0;
            } elseif ($validated['bottle_type'] === Customer::BOTTLE_TYPE_EXCHANGE) {
                $fixedCount = 0;
                $exchangeCount = max(0, (int) ($validated['bottles_count'] ?? $validated['exchange_bottles_count'] ?? 0));
            } else {
                $fixedCount = 0;
                $exchangeCount = 0;
            }
        } else {
            $fixedCount = max(0, (int) ($validated['fixed_bottles_count'] ?? 0));
            $exchangeCount = max(0, (int) ($validated['exchange_bottles_count'] ?? 0));
        }

        if ($fixedCount > 0 && $exchangeCount > 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'bottle_type' => 'Solo puedes registrar una modalidad a la vez: fijos o de cambio.',
            ]);
        }

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

        // Impedir cambiar de modalidad si tiene pedidos activos o fijos pendientes en Planta/camión
        $previousType = $customer->bottle_type;
        $newType = ($fixedCount > 0) ? Customer::BOTTLE_TYPE_FIXED : (($exchangeCount > 0) ? Customer::BOTTLE_TYPE_EXCHANGE : Customer::BOTTLE_TYPE_UNASSIGNED);

        if ($previousType !== Customer::BOTTLE_TYPE_UNASSIGNED && $newType !== Customer::BOTTLE_TYPE_UNASSIGNED && $previousType !== $newType) {
            $activeOrders = $customer->orders()
                ->whereIn('status', [
                    \App\Models\Order::STATUS_PENDING,
                    \App\Models\Order::STATUS_CONFIRMED,
                    \App\Models\Order::STATUS_IN_ROUTE,
                    \App\Models\Order::STATUS_IN_PROCESS,
                ])
                ->exists();

            if ($activeOrders) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'bottle_type' => 'No puedes cambiar tu modalidad mientras tengas un pedido activo en curso.',
                ]);
            }
        }

        $customer->update([
            'fixed_bottles_count' => $fixedCount,
            'exchange_bottles_count' => $exchangeCount,
        ]);

        return back()->with('success', 'Tus garrafones de 20 litros han sido actualizados correctamente.');
    }
}
