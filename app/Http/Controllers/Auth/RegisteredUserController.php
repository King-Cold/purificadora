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
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'fixed_bottles_count' => 'nullable|integer|min:0|max:500',
            'exchange_bottles_count' => 'nullable|integer|min:0|max:500',
        ]);

        // Transacción atómica: Creación de usuario, asignación de rol Cliente y creación de Customer
        $user = DB::transaction(function () use ($request) {
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
                'fixed_bottles_count' => (int) ($request->input('fixed_bottles_count') ?? 0),
                'exchange_bottles_count' => (int) ($request->input('exchange_bottles_count') ?? 0),
                'status' => 'active',
            ]);

            return $createdUser;
        });

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
