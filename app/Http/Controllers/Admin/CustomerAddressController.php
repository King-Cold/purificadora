<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerAddressRequest;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Services\CustomerAddressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CustomerAddressController extends Controller
{
    public function __construct(
        protected CustomerAddressService $addressService
    ) {}

    /**
     * Muestra el formulario para registrar el único domicilio del cliente.
     */
    public function create(Request $request, Customer $customer): Response|RedirectResponse
    {
        $authUser = $request->user() ?: Auth::user();
        if (!$authUser || (!$authUser->can('customers.create') && !$authUser->can('customers.update'))) {
            abort(403, 'No tiene autorización para registrar domicilios de clientes.');
        }

        // Clientes con cuenta gestionan su propio domicilio
        if (!is_null($customer->user_id)) {
            return redirect()
                ->route('admin.customers.show', $customer->id)
                ->with('error', 'El Administrador no puede registrar el domicilio de un cliente con cuenta. El cliente conservará la gestión de su propio domicilio.');
        }

        // Si ya tiene un domicilio activo, redirigir a la ficha
        if ($customer->address()->exists()) {
            return redirect()
                ->route('admin.customers.show', $customer->id)
                ->with('warning', 'Este cliente ya cuenta con un domicilio registrado. Puede editarlo o modificarlo directamente.');
        }

        return Inertia::render('Admin/Customers/Addresses/Create', [
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
     * Almacena el único domicilio para el cliente especificado.
     */
    public function store(CustomerAddressRequest $request, Customer $customer): RedirectResponse
    {
        $authUser = $request->user() ?: Auth::user();
        if (!$authUser || (!$authUser->can('customers.create') && !$authUser->can('customers.update'))) {
            abort(403, 'No tiene autorización para registrar domicilios de clientes.');
        }

        // Clientes con cuenta gestionan su propio domicilio
        if (!is_null($customer->user_id)) {
            abort(403, 'El Administrador no puede registrar el domicilio de un cliente con cuenta. El cliente conservará la gestión de su propio domicilio.');
        }

        // Protección HTTP manipulada contra segundo domicilio
        if ($customer->address()->exists()) {
            return redirect()
                ->route('admin.customers.show', $customer->id)
                ->with('warning', 'Este cliente ya cuenta con un domicilio registrado. Solo se permite un único domicilio.');
        }

        $address = $this->addressService->createAddress($customer, $request->validated());

        $displayName = $customer->name ?: ($customer->nickname ? "Cliente '{$customer->nickname}'" : 'Cliente');

        return redirect()
            ->route('admin.customers.show', $customer->id)
            ->with('success', "Domicilio registrado exitosamente para {$displayName}.");
    }

    /**
     * Muestra el formulario de edición del domicilio del cliente.
     */
    public function edit(Request $request, Customer $customer, CustomerAddress $address): Response
    {
        $authUser = $request->user() ?: Auth::user();
        if (!$authUser || !$authUser->can('customers.update')) {
            abort(403, 'No tiene autorización para editar domicilios de clientes.');
        }

        // Clientes con cuenta gestionan su propio domicilio
        if (!is_null($customer->user_id)) {
            abort(403, 'El Administrador no puede editar el domicilio de un cliente con cuenta. El cliente conservará la gestión de su propio domicilio.');
        }

        if ($address->customer_id !== $customer->id) {
            abort(404, 'El domicilio no pertenece al cliente especificado.');
        }

        $isHocaba = empty($address->city) || in_array(strtolower(trim($address->city)), ['hocaba', 'hocabá', 'hocabá, yucatán', 'hocaba, yucatan']);

        return Inertia::render('Admin/Customers/Addresses/Edit', [
            'customer' => [
                'id' => $customer->id,
                'code' => $customer->code,
                'name' => $customer->name,
                'nickname' => $customer->nickname,
            ],
            'address' => [
                'id' => $address->id,
                'customer_id' => $address->customer_id,
                'street_and_number' => $address->street,
                'municipality_type' => $isHocaba ? 'hocaba' : 'other',
                'other_municipality' => $isHocaba ? '' : $address->city,
                'delivery_zone' => $address->delivery_zone,
                'reference' => $address->reference,
            ],
            'zones' => CustomerAddress::ZONES,
        ]);
    }

    /**
     * Actualiza el domicilio del cliente.
     */
    public function update(CustomerAddressRequest $request, Customer $customer, CustomerAddress $address): RedirectResponse
    {
        $authUser = $request->user() ?: Auth::user();
        if (!$authUser || !$authUser->can('customers.update')) {
            abort(403, 'No tiene autorización para editar domicilios de clientes.');
        }

        // Clientes con cuenta gestionan su propio domicilio
        if (!is_null($customer->user_id)) {
            abort(403, 'El Administrador no puede editar ni actualizar el domicilio de un cliente con cuenta. El cliente conservará la gestión de su propio domicilio.');
        }

        if ($address->customer_id !== $customer->id) {
            abort(404, 'El domicilio no pertenece al cliente especificado.');
        }

        $this->addressService->updateAddress($customer, $address, $request->validated());

        return redirect()
            ->route('admin.customers.show', $customer->id)
            ->with('success', 'Domicilio actualizado correctamente.');
    }

    /**
     * Elimina el domicilio mediante eliminación lógica (SoftDeletes).
     */
    public function destroy(Request $request, Customer $customer, CustomerAddress $address): RedirectResponse
    {
        $authUser = $request->user() ?: Auth::user();
        if (!$authUser || (!$authUser->can('customers.delete') && !$authUser->can('customers.update'))) {
            abort(403, 'No tiene autorización para eliminar domicilios.');
        }

        // Clientes con cuenta gestionan su propio domicilio
        if (!is_null($customer->user_id)) {
            abort(403, 'El Administrador no puede eliminar el domicilio de un cliente con cuenta. El cliente conservará la gestión de su propio domicilio.');
        }

        if ($address->customer_id !== $customer->id) {
            abort(404, 'El domicilio no pertenece al cliente especificado.');
        }

        $this->addressService->deleteAddress($customer, $address);

        return redirect()
            ->route('admin.customers.show', $customer->id)
            ->with('success', 'Domicilio eliminado correctamente. Ahora puede registrar una nueva dirección cuando lo requiera.');
    }
}
