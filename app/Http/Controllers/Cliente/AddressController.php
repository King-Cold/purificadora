<?php

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerAddressRequest;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Services\CustomerAddressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AddressController extends Controller
{
    public function __construct(
        protected CustomerAddressService $addressService
    ) {}

    /**
     * Resuelve de forma segura el perfil Customer vinculado al usuario autenticado.
     */
    protected function resolveCustomer(Request $request): Customer
    {
        $user = $request->user();
        if (!$user) {
            abort(401, 'No autenticado.');
        }

        $customer = $user->customer;
        if (!$customer) {
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

        return $customer;
    }

    /**
     * Muestra la vista principal de "Mi domicilio" para el cliente autenticado.
     */
    public function index(Request $request): Response
    {
        $customer = $this->resolveCustomer($request);
        $address = $customer->address;

        $addressData = null;
        if ($address) {
            $isHocaba = empty($address->city) || in_array(strtolower(trim($address->city)), ['hocaba', 'hocabá', 'hocabá, yucatán', 'hocaba, yucatan']);
            $addressData = [
                'id' => $address->id,
                'street_and_number' => $address->street,
                'street' => $address->street,
                'exterior_number' => $address->exterior_number,
                'interior_number' => $address->interior_number,
                'between_streets' => $address->between_streets,
                'neighborhood' => $address->neighborhood,
                'city' => $address->city,
                'municipality' => $address->city ?: 'Hocabá',
                'state' => $address->state ?: 'Yucatán',
                'postal_code' => $address->postal_code,
                'is_hocaba' => $isHocaba,
                'delivery_zone' => $address->delivery_zone,
                'zone_key' => $address->delivery_zone,
                'zone_label' => $address->zone_label ?: 'Sin zona asignada',
                'reference' => $address->reference,
                'full_address' => $address->full_address,
            ];
        }

        return Inertia::render('Cliente/Addresses/Index', [
            'address' => $addressData,
            'customer' => [
                'id' => $customer->id,
                'code' => $customer->code,
                'name' => $customer->name,
                'nickname' => $customer->nickname,
            ],
            'zones' => CustomerAddress::ZONES,
        ]);
    }

    /**
     * Muestra el formulario para registrar el único domicilio.
     */
    public function create(Request $request): Response|RedirectResponse
    {
        $customer = $this->resolveCustomer($request);

        // Si ya tiene domicilio registrado, no puede registrar otro
        if ($customer->address()->exists()) {
            return redirect()
                ->route('cliente.addresses.index')
                ->with('warning', 'Ya tienes tu domicilio registrado. Puedes consultar o editar sus datos.');
        }

        return Inertia::render('Cliente/Addresses/Create', [
            'zones' => CustomerAddress::ZONES,
        ]);
    }

    /**
     * Almacena el único domicilio para el cliente autenticado.
     */
    public function store(CustomerAddressRequest $request): RedirectResponse
    {
        $customer = $this->resolveCustomer($request);

        if ($customer->address()->exists()) {
            return redirect()
                ->route('cliente.addresses.index')
                ->with('warning', 'Ya tienes tu domicilio registrado. Puedes consultar o editar sus datos.');
        }

        $address = $this->addressService->createAddress($customer, $request->validated());

        return redirect()
            ->route('cliente.addresses.index')
            ->with('success', 'Tu domicilio ha sido registrado correctamente.');
    }

    /**
     * Muestra el formulario para editar el domicilio del cliente autenticado.
     */
    public function edit(Request $request, ?CustomerAddress $address = null): Response|RedirectResponse
    {
        $customer = $this->resolveCustomer($request);
        $addr = $address ?: $customer->address;

        if (!$addr || $addr->customer_id !== $customer->id) {
            abort(404, 'Domicilio no encontrado.');
        }

        $isHocaba = empty($addr->city) || in_array(strtolower(trim($addr->city)), ['hocaba', 'hocabá', 'hocabá, yucatán', 'hocaba, yucatan']);

        return Inertia::render('Cliente/Addresses/Edit', [
            'address' => [
                'id' => $addr->id,
                'street_and_number' => $addr->street,
                'municipality_type' => $isHocaba ? 'hocaba' : 'other',
                'other_municipality' => $isHocaba ? '' : $addr->city,
                'delivery_zone' => $addr->delivery_zone,
                'reference' => $addr->reference,
            ],
            'zones' => CustomerAddress::ZONES,
        ]);
    }

    /**
     * Actualiza el domicilio del cliente autenticado.
     */
    public function update(CustomerAddressRequest $request, ?CustomerAddress $address = null): RedirectResponse
    {
        $customer = $this->resolveCustomer($request);
        $addr = $address ?: $customer->address;

        if (!$addr || $addr->customer_id !== $customer->id) {
            abort(404, 'Domicilio no encontrado.');
        }

        $this->addressService->updateAddress($customer, $addr, $request->validated());

        return redirect()
            ->route('cliente.addresses.index')
            ->with('success', 'Tu domicilio ha sido actualizado correctamente.');
    }

    /**
     * Elimina el domicilio del cliente autenticado.
     */
    public function destroy(Request $request, ?CustomerAddress $address = null): RedirectResponse
    {
        $customer = $this->resolveCustomer($request);
        $addr = $address ?: $customer->address;

        if (!$addr || $addr->customer_id !== $customer->id) {
            abort(404, 'Domicilio no encontrado.');
        }

        $this->addressService->deleteAddress($customer, $addr);

        return redirect()
            ->route('cliente.addresses.index')
            ->with('success', 'Tu domicilio ha sido eliminado. Ahora puedes registrar una nueva dirección.');
    }
}
