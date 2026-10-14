<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerAddressService
{
    /**
     * Registra el único domicilio de entrega del cliente (su casa).
     * Impide registrar un segundo domicilio si ya existe uno activo.
     */
    public function createAddress(Customer $customer, array $data): CustomerAddress
    {
        return DB::transaction(function () use ($customer, $data) {
            // Bloqueo pesimista del registro del cliente para evitar registros concurrentes simultáneos
            Customer::where('id', $customer->id)->lockForUpdate()->first();

            // REGLA DEFINITIVA: CADA CLIENTE TENDRÁ ÚNICAMENTE UN DOMICILIO REGISTRADO (SU CASA).
            $existingActive = CustomerAddress::where('customer_id', $customer->id)
                ->whereNull('deleted_at')
                ->first();

            if ($existingActive) {
                throw ValidationException::withMessages([
                    'address' => 'El cliente ya cuenta con un domicilio registrado. Cada cliente tiene únicamente una dirección (su casa).',
                ]);
            }

            // Resolución de municipio y zona de reparto
            $municipalityType = $data['municipality_type'] ?? 'hocaba';
            if ($municipalityType === 'other') {
                $municipality = !empty($data['other_municipality']) ? trim($data['other_municipality']) : 'Otro municipio';
                $deliveryZone = null;
            } else {
                $municipality = 'Hocabá';
                $deliveryZone = !empty($data['delivery_zone']) ? $data['delivery_zone'] : null;
            }

            $streetAndNumber = trim($data['street_and_number'] ?? ($data['street'] ?? ''));

            // Extracción o asignación de número exterior para compatibilidad con la base de datos
            $exteriorNumber = 'S/N';
            if (preg_match('/(?:#|No\.|Num\.|número)?\s*([0-9]+[A-Za-z\-0-9]*)\s*$/i', $streetAndNumber, $matches)) {
                $exteriorNumber = $matches[1];
            }

            $address = new CustomerAddress();
            $address->customer_id = $customer->id;
            $address->label = 'Casa';
            $address->street = $streetAndNumber;
            $address->exterior_number = $exteriorNumber;
            $address->interior_number = null;
            $address->neighborhood = ($municipality === 'Hocabá') ? 'Centro' : $municipality;
            $address->city = $municipality;
            $address->state = 'Yucatán';
            $address->postal_code = null;
            $address->between_streets = null;
            $address->reference = !empty($data['reference']) ? trim($data['reference']) : null;
            $address->delivery_zone = $deliveryZone;
            $address->preferred_time_window = null;
            $address->is_default = true;
            $address->save();

            return $address;
        });
    }

    /**
     * Actualiza la información del único domicilio del cliente.
     */
    public function updateAddress(Customer $customer, CustomerAddress $address, array $data): CustomerAddress
    {
        return DB::transaction(function () use ($customer, $address, $data) {
            Customer::where('id', $customer->id)->lockForUpdate()->first();

            if ($address->customer_id !== $customer->id) {
                abort(404, 'El domicilio no pertenece al cliente especificado.');
            }

            $municipalityType = $data['municipality_type'] ?? 'hocaba';
            if ($municipalityType === 'other') {
                $municipality = !empty($data['other_municipality']) ? trim($data['other_municipality']) : 'Otro municipio';
                $deliveryZone = null;
            } else {
                $municipality = 'Hocabá';
                $deliveryZone = !empty($data['delivery_zone']) ? $data['delivery_zone'] : null;
            }

            $streetAndNumber = trim($data['street_and_number'] ?? ($data['street'] ?? ''));

            $exteriorNumber = 'S/N';
            if (preg_match('/(?:#|No\.|Num\.|número)?\s*([0-9]+[A-Za-z\-0-9]*)\s*$/i', $streetAndNumber, $matches)) {
                $exteriorNumber = $matches[1];
            }

            $address->label = 'Casa';
            $address->street = $streetAndNumber;
            $address->exterior_number = $exteriorNumber;
            $address->neighborhood = ($municipality === 'Hocabá') ? 'Centro' : $municipality;
            $address->city = $municipality;
            $address->delivery_zone = $deliveryZone;
            $address->reference = !empty($data['reference']) ? trim($data['reference']) : null;
            $address->is_default = true;
            $address->save();

            return $address;
        });
    }

    /**
     * Elimina el domicilio mediante eliminación lógica (SoftDeletes).
     */
    public function deleteAddress(Customer $customer, CustomerAddress $address): void
    {
        DB::transaction(function () use ($customer, $address) {
            Customer::where('id', $customer->id)->lockForUpdate()->first();

            if ($address->customer_id !== $customer->id) {
                abort(404, 'El domicilio no pertenece al cliente especificado.');
            }

            $address->delete();
        });
    }

    /**
     * Método conservado por compatibilidad de interfaz.
     */
    public function setDefaultAddress(Customer $customer, CustomerAddress $address): void
    {
        DB::transaction(function () use ($customer, $address) {
            Customer::where('id', $customer->id)->lockForUpdate()->first();

            if ($address->customer_id !== $customer->id) {
                abort(404, 'El domicilio no pertenece al cliente especificado.');
            }

            $address->update(['is_default' => true]);
        });
    }
}
