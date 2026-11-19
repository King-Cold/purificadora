<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'bottle_type' => 'nullable|string|in:fixed,exchange,none',
            'bottles_count' => 'nullable|integer|min:0|max:500',
            'fixed_bottles_count' => 'nullable|integer|min:0|max:500',
            'exchange_bottles_count' => 'nullable|integer|min:0|max:500',
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
            throw ValidationException::withMessages([
                'bottle_type' => 'Elige únicamente una modalidad de botellón: fijos o de cambio.',
            ]);
        }

        // Transacción atómica: Creación de usuario, asignación de rol Cliente y creación de Customer
        $user = DB::transaction(function () use ($request, $fixedCount, $exchangeCount) {
            $createdUser = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            // Asignación exclusiva del rol 'Cliente'
            $createdUser->assignRole('Cliente');

            // Creación automática del perfil comercial Customer asociado
            Customer::create([
                'user_id' => $createdUser->id,
                'name' => $createdUser->name,
                'email' => $createdUser->email,
                'customer_type' => 'particular',
                'price_tier' => 'general',
                'fixed_bottles_count' => $fixedCount,
                'exchange_bottles_count' => $exchangeCount,
                'status' => 'active',
            ]);

            return $createdUser;
        });

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
