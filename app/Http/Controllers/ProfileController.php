<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Muestra la vista de edición del perfil de usuario o "Mi perfil" de Cliente.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $isClient = $user && $user->hasRole('Cliente');

        $clientData = null;
        if ($isClient) {
            $customer = $user->customer;
            if (!$customer) {
                // Manejo seguro: si no existe registro Customer vinculado, se crea atómicamente
                $customer = Customer::create([
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => 'No registrado',
                    'customer_type' => 'particular',
                    'price_tier' => 'general',
                    'status' => 'active',
                    'fixed_bottles_count' => 0,
                    'exchange_bottles_count' => 0,
                ]);
            }

            $address = $customer->address;

            $clientData = [
                'customer' => [
                    'id' => $customer->id,
                    'code' => $customer->code,
                    'name' => $customer->name ?? $user->name,
                    'nickname' => $customer->nickname ?? '',
                    'phone' => ($customer->phone && $customer->phone !== 'No registrado') ? $customer->phone : '',
                    'email' => $user->email,
                    'can_place_orders' => true,
                    'fixed_bottles_count' => (int) ($customer->fixed_bottles_count ?? 0),
                    'exchange_bottles_count' => (int) ($customer->exchange_bottles_count ?? 0),
                    'total_bottles' => (int) $customer->total_bottles,
                ],
                'address' => $address ? [
                    'id' => $address->id,
                    'street_and_number' => $address->street,
                    'municipality' => $address->city ?: 'Hocabá',
                    'delivery_zone' => $address->delivery_zone,
                    'zone_label' => $address->zone_label ?: 'Sin zona asignada',
                    'reference' => $address->reference,
                    'has_address' => true,
                ] : [
                    'has_address' => false,
                ],
            ];
        }

        $isWorker = $user->hasAnyRole(['Administrador', 'Super Admin', 'Repartidor', 'Operador Planta']);

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => session('status'),
            'isClient' => $isClient,
            'clientData' => $clientData,
            'canDeleteAccount' => !$isWorker,
        ]);
    }

    /**
     * Actualiza la información personal del usuario y sincroniza con Customer.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        DB::transaction(function () use ($user, $validated) {
            $user->name = trim($validated['name']);
            $user->email = trim($validated['email']);

            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }

            $user->save();

            // Sincronización transaccional con la entidad Customer si el usuario es Cliente
            if ($user->hasRole('Cliente')) {
                $customer = $user->customer;
                if (!$customer) {
                    $customer = new Customer();
                    $customer->user_id = $user->id;
                    $customer->fixed_bottles_count = 0;
                    $customer->exchange_bottles_count = 0;
                }

                $customer->name = $user->name;
                $customer->email = $user->email;
                if (isset($validated['phone'])) {
                    $customer->phone = trim($validated['phone']);
                }

                // Apodo opcional: agregar, modificar o eliminar (guardar null si viene vacío)
                $nickname = isset($validated['nickname']) ? trim((string) $validated['nickname']) : null;
                $customer->nickname = ($nickname !== '') ? $nickname : null;

                $customer->save();
            }
        });

        return Redirect::route('profile.edit')->with('success', 'Tu información personal ha sido actualizada correctamente.');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        // No permitir que los trabajadores eliminen sus propias cuentas
        if ($user->hasAnyRole(['Administrador', 'Super Admin', 'Repartidor', 'Operador Planta'])) {
            return back()->with('error', 'Las cuentas de personal no pueden ser eliminadas.');
        }

        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
