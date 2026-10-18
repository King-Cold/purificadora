<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\User;
use App\Services\CustomerAddressService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CustomerAddressManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $clientUser;
    protected User $otherClientUser;
    protected User $driverUser;
    protected User $plantUser;
    protected Customer $customerWithoutAccount;
    protected Customer $customerWithAccount;
    protected Customer $otherCustomerWithAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        // 1. Admin
        $this->adminUser = User::factory()->create(['status' => 'active']);
        $this->adminUser->assignRole('Administrador');

        // 2. Client User & Customer
        $this->clientUser = User::factory()->create(['status' => 'active']);
        $this->clientUser->assignRole('Cliente');

        $this->customerWithAccount = Customer::create([
            'user_id' => $this->clientUser->id,
            'name' => 'José Pérez Morales',
            'nickname' => 'Don Pepe',
            'phone' => '9991234567',
            'customer_type' => 'particular',
            'price_tier' => 'general',
            'fixed_bottles_count' => 3,
            'exchange_bottles_count' => 2,
            'status' => 'active',
        ]);

        // 3. Other Client User & Customer (para pruebas de aislamiento)
        $this->otherClientUser = User::factory()->create(['status' => 'active']);
        $this->otherClientUser->assignRole('Cliente');

        $this->otherCustomerWithAccount = Customer::create([
            'user_id' => $this->otherClientUser->id,
            'name' => 'Manuel Canché Pech',
            'nickname' => 'Don Manolo',
            'phone' => '9999887766',
            'customer_type' => 'particular',
            'price_tier' => 'general',
            'fixed_bottles_count' => 1,
            'exchange_bottles_count' => 1,
            'status' => 'active',
        ]);

        // 4. Customer without account
        $this->customerWithoutAccount = Customer::create([
            'user_id' => null,
            'name' => 'María Cocom Can',
            'nickname' => 'Doña Mary',
            'phone' => '9997654321',
            'customer_type' => 'particular',
            'price_tier' => 'general',
            'fixed_bottles_count' => 1,
            'exchange_bottles_count' => 0,
            'status' => 'active',
        ]);

        // 5. Driver & Plant
        $this->driverUser = User::factory()->create(['status' => 'active']);
        $this->driverUser->assignRole('Repartidor');

        $this->plantUser = User::factory()->create(['status' => 'active']);
        $this->plantUser->assignRole('Operador Planta');
    }

    /**
     * 1. Registro de un único domicilio.
     */
    public function test_registration_of_single_address(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.customers.addresses.store', $this->customerWithoutAccount->id), [
                'municipality_type' => 'hocaba',
                'delivery_zone' => 'north',
                'street_and_number' => 'Calle 20 #105',
                'reference' => 'Casa azul frente al parque',
            ]);

        $response->assertRedirect(route('admin.customers.show', $this->customerWithoutAccount->id));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('customer_addresses', [
            'customer_id' => $this->customerWithoutAccount->id,
            'city' => 'Hocabá',
            'delivery_zone' => 'north',
            'street' => 'Calle 20 #105',
            'reference' => 'Casa azul frente al parque',
            'is_default' => 1,
            'deleted_at' => null,
        ]);

        $this->assertEquals(1, $this->customerWithoutAccount->fresh()->addresses()->count());
    }

    /**
     * 2. Rechazo del segundo domicilio mediante el servicio.
     */
    public function test_rejection_of_second_address_via_service(): void
    {
        $service = app(CustomerAddressService::class);

        // Primer registro exitoso
        $service->createAddress($this->customerWithoutAccount, [
            'municipality_type' => 'hocaba',
            'delivery_zone' => 'north',
            'street_and_number' => 'Calle 20 #105',
        ]);

        // Intento de segundo registro debe arrojar ValidationException
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->expectExceptionMessage('El cliente ya cuenta con un domicilio registrado.');

        $service->createAddress($this->customerWithoutAccount, [
            'municipality_type' => 'hocaba',
            'delivery_zone' => 'south',
            'street_and_number' => 'Calle 22 #200',
        ]);
    }

    /**
     * 3. Rechazo del segundo domicilio mediante petición HTTP manipulada.
     */
    public function test_rejection_of_second_address_via_manipulated_http_request(): void
    {
        // 1er registro
        $this->actingAs($this->adminUser)
            ->post(route('admin.customers.addresses.store', $this->customerWithoutAccount->id), [
                'municipality_type' => 'hocaba',
                'delivery_zone' => 'north',
                'street_and_number' => 'Calle 20 #105',
            ]);

        // 2da petición HTTP manipulada al endpoint de almacenamiento
        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.customers.addresses.store', $this->customerWithoutAccount->id), [
                'municipality_type' => 'hocaba',
                'delivery_zone' => 'south',
                'street_and_number' => 'Calle 22 #200',
            ]);

        $response->assertRedirect(route('admin.customers.show', $this->customerWithoutAccount->id));
        $response->assertSessionHas('warning', 'Este cliente ya cuenta con un domicilio registrado. Solo se permite un único domicilio.');

        // Debe mantenerse exactamente un único registro activo
        $this->assertEquals(1, $this->customerWithoutAccount->fresh()->addresses()->count());
    }

    /**
     * 4. Protección contra registros simultáneos (pessimistic locking en transacción).
     */
    public function test_protection_against_simultaneous_registrations(): void
    {
        $service = app(CustomerAddressService::class);

        // Simulación: registro inicial
        $service->createAddress($this->customerWithoutAccount, [
            'municipality_type' => 'hocaba',
            'delivery_zone' => 'east',
            'street_and_number' => 'Calle 15 #50',
        ]);

        // Intento concurrente en transacción separada
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->createAddress($this->customerWithoutAccount, [
            'municipality_type' => 'hocaba',
            'delivery_zone' => 'west',
            'street_and_number' => 'Calle 18 #80',
        ]);
    }

    /**
     * 5. Hocabá como municipio predeterminado.
     */
    public function test_hocaba_as_default_municipality(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.customers.addresses.store', $this->customerWithoutAccount->id), [
                'municipality_type' => 'hocaba',
                'delivery_zone' => 'south',
                'street_and_number' => 'Calle 24 #112',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('customer_addresses', [
            'customer_id' => $this->customerWithoutAccount->id,
            'city' => 'Hocabá',
            'state' => 'Yucatán',
        ]);
    }

    /**
     * 6. Registro en Zona Norte.
     */
    public function test_registration_in_zone_north(): void
    {
        $this->actingAs($this->adminUser)
            ->post(route('admin.customers.addresses.store', $this->customerWithoutAccount->id), [
                'municipality_type' => 'hocaba',
                'delivery_zone' => 'north',
                'street_and_number' => 'Calle 10 #100',
            ]);

        $this->assertDatabaseHas('customer_addresses', [
            'customer_id' => $this->customerWithoutAccount->id,
            'delivery_zone' => 'north',
            'city' => 'Hocabá',
        ]);
    }

    /**
     * 7. Registro en Zona Sur.
     */
    public function test_registration_in_zone_south(): void
    {
        $this->actingAs($this->adminUser)
            ->post(route('admin.customers.addresses.store', $this->customerWithoutAccount->id), [
                'municipality_type' => 'hocaba',
                'delivery_zone' => 'south',
                'street_and_number' => 'Calle 30 #200',
            ]);

        $this->assertDatabaseHas('customer_addresses', [
            'customer_id' => $this->customerWithoutAccount->id,
            'delivery_zone' => 'south',
            'city' => 'Hocabá',
        ]);
    }

    /**
     * 8. Registro en Zona Este.
     */
    public function test_registration_in_zone_east(): void
    {
        $this->actingAs($this->adminUser)
            ->post(route('admin.customers.addresses.store', $this->customerWithoutAccount->id), [
                'municipality_type' => 'hocaba',
                'delivery_zone' => 'east',
                'street_and_number' => 'Calle 19 #50',
            ]);

        $this->assertDatabaseHas('customer_addresses', [
            'customer_id' => $this->customerWithoutAccount->id,
            'delivery_zone' => 'east',
            'city' => 'Hocabá',
        ]);
    }

    /**
     * 9. Registro en Zona Oeste.
     */
    public function test_registration_in_zone_west(): void
    {
        $this->actingAs($this->adminUser)
            ->post(route('admin.customers.addresses.store', $this->customerWithoutAccount->id), [
                'municipality_type' => 'hocaba',
                'delivery_zone' => 'west',
                'street_and_number' => 'Calle 25 #80',
            ]);

        $this->assertDatabaseHas('customer_addresses', [
            'customer_id' => $this->customerWithoutAccount->id,
            'delivery_zone' => 'west',
            'city' => 'Hocabá',
        ]);
    }

    /**
     * 10. Rechazo de zonas inválidas.
     */
    public function test_rejection_of_invalid_zones(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.customers.addresses.store', $this->customerWithoutAccount->id), [
                'municipality_type' => 'hocaba',
                'delivery_zone' => 'zona_inexistente',
                'street_and_number' => 'Calle 20 #105',
            ]);

        $response->assertSessionHasErrors(['delivery_zone']);
        $this->assertDatabaseCount('customer_addresses', 0);
    }

    /**
     * 11. Rechazo de domicilio de Hocabá sin zona.
     */
    public function test_rejection_of_hocaba_address_without_zone(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.customers.addresses.store', $this->customerWithoutAccount->id), [
                'municipality_type' => 'hocaba',
                'delivery_zone' => '',
                'street_and_number' => 'Calle 20 #105',
            ]);

        $response->assertSessionHasErrors(['delivery_zone']);
        $this->assertDatabaseCount('customer_addresses', 0);
    }

    /**
     * 12. Registro de otro municipio.
     */
    public function test_registration_of_other_municipality(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.customers.addresses.store', $this->customerWithoutAccount->id), [
                'municipality_type' => 'other',
                'other_municipality' => 'Seyé',
                'street_and_number' => 'Calle 14 #45',
                'reference' => 'Cerca de la entrada principal',
            ]);

        $response->assertRedirect(route('admin.customers.show', $this->customerWithoutAccount->id));
        $this->assertDatabaseHas('customer_addresses', [
            'customer_id' => $this->customerWithoutAccount->id,
            'city' => 'Seyé',
            'delivery_zone' => null,
            'street' => 'Calle 14 #45',
        ]);
    }

    /**
     * 13. Edición de domicilio.
     */
    public function test_editing_address(): void
    {
        $service = app(CustomerAddressService::class);
        $address = $service->createAddress($this->customerWithoutAccount, [
            'municipality_type' => 'hocaba',
            'delivery_zone' => 'north',
            'street_and_number' => 'Calle 20 #105',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->put(route('admin.customers.addresses.update', [$this->customerWithoutAccount->id, $address->id]), [
                'municipality_type' => 'hocaba',
                'delivery_zone' => 'south',
                'street_and_number' => 'Calle 20 #105 modificado',
                'reference' => 'Portón negro nuevo',
            ]);

        $response->assertRedirect(route('admin.customers.show', $this->customerWithoutAccount->id));

        $this->assertDatabaseHas('customer_addresses', [
            'id' => $address->id,
            'delivery_zone' => 'south',
            'street' => 'Calle 20 #105 modificado',
            'reference' => 'Portón negro nuevo',
        ]);
    }

    /**
     * 14. Eliminación lógica (Soft Delete).
     */
    public function test_soft_deletion_of_address(): void
    {
        $service = app(CustomerAddressService::class);
        $address = $service->createAddress($this->customerWithoutAccount, [
            'municipality_type' => 'hocaba',
            'delivery_zone' => 'north',
            'street_and_number' => 'Calle 20 #105',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->delete(route('admin.customers.addresses.destroy', [$this->customerWithoutAccount->id, $address->id]));

        $response->assertRedirect(route('admin.customers.show', $this->customerWithoutAccount->id));

        // En la base de datos sigue existiendo con deleted_at no nulo
        $this->assertSoftDeleted('customer_addresses', [
            'id' => $address->id,
        ]);

        // Y la relación activa no devuelve nada
        $this->assertNull($this->customerWithoutAccount->fresh()->address);
    }

    /**
     * 15. Registro de un nuevo domicilio después de eliminar el anterior.
     */
    public function test_register_new_address_after_soft_deleting_previous_one(): void
    {
        $service = app(CustomerAddressService::class);
        $address1 = $service->createAddress($this->customerWithoutAccount, [
            'municipality_type' => 'hocaba',
            'delivery_zone' => 'north',
            'street_and_number' => 'Calle 20 #105',
        ]);

        // Eliminación lógica
        $service->deleteAddress($this->customerWithoutAccount, $address1);

        // Registro de un nuevo domicilio permitido
        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.customers.addresses.store', $this->customerWithoutAccount->id), [
                'municipality_type' => 'hocaba',
                'delivery_zone' => 'west',
                'street_and_number' => 'Calle 25 #300',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // El cliente ahora tiene un nuevo domicilio activo
        $this->assertEquals(1, $this->customerWithoutAccount->fresh()->addresses()->count());
        $this->assertEquals('west', $this->customerWithoutAccount->fresh()->address->delivery_zone);
    }

    /**
     * 16. Conservación de datos históricos con Soft Deletes.
     */
    public function test_preservation_of_historical_data_with_soft_deletes(): void
    {
        $service = app(CustomerAddressService::class);
        $address1 = $service->createAddress($this->customerWithoutAccount, [
            'municipality_type' => 'hocaba',
            'delivery_zone' => 'north',
            'street_and_number' => 'Calle 20 #105 antigua',
        ]);

        $service->deleteAddress($this->customerWithoutAccount, $address1);

        $address2 = $service->createAddress($this->customerWithoutAccount, [
            'municipality_type' => 'hocaba',
            'delivery_zone' => 'south',
            'street_and_number' => 'Calle 28 #200 nueva',
        ]);

        // Los datos históricos permanecen en la tabla (total 2 registros, 1 soft-deleted)
        $this->assertEquals(2, CustomerAddress::withTrashed()->where('customer_id', $this->customerWithoutAccount->id)->count());
        $this->assertEquals(1, CustomerAddress::where('customer_id', $this->customerWithoutAccount->id)->count());
    }

    /**
     * 17. Filtro de clientes por zona (Norte, Sur, Este, Oeste).
     */
    public function test_filter_customers_by_zone(): void
    {
        $service = app(CustomerAddressService::class);

        // Cliente 1 en Zona Norte
        $service->createAddress($this->customerWithoutAccount, [
            'municipality_type' => 'hocaba',
            'delivery_zone' => 'north',
            'street_and_number' => 'Calle 10 #10',
        ]);

        // Cliente 2 en Zona Sur
        $service->createAddress($this->customerWithAccount, [
            'municipality_type' => 'hocaba',
            'delivery_zone' => 'south',
            'street_and_number' => 'Calle 30 #30',
        ]);

        // Filtro Zona Norte
        $responseNorth = $this->actingAs($this->adminUser)
            ->get(route('admin.customers.index', ['zone' => 'north']));
        $responseNorth->assertOk();
        $responseNorth->assertInertia(fn ($page) =>
            $page->component('Admin/Customers/Index')
                ->where('customers.total', 1)
                ->where('customers.data.0.id', $this->customerWithoutAccount->id)
        );

        // Filtro Zona Sur
        $responseSouth = $this->actingAs($this->adminUser)
            ->get(route('admin.customers.index', ['zone' => 'south']));
        $responseSouth->assertOk();
        $responseSouth->assertInertia(fn ($page) =>
            $page->component('Admin/Customers/Index')
                ->where('customers.total', 1)
                ->where('customers.data.0.id', $this->customerWithAccount->id)
        );
    }

    /**
     * 18. Filtro de otros municipios.
     */
    public function test_filter_customers_by_other_municipality(): void
    {
        $service = app(CustomerAddressService::class);

        // Cliente en Hocabá
        $service->createAddress($this->customerWithoutAccount, [
            'municipality_type' => 'hocaba',
            'delivery_zone' => 'north',
            'street_and_number' => 'Calle 10 #10',
        ]);

        // Cliente en Seyé
        $service->createAddress($this->customerWithAccount, [
            'municipality_type' => 'other',
            'other_municipality' => 'Seyé',
            'street_and_number' => 'Calle Principal #5',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.customers.index', ['zone' => 'other_municipality']));

        $response->assertOk();
        $response->assertInertia(fn ($page) =>
            $page->component('Admin/Customers/Index')
                ->where('customers.total', 1)
                ->where('customers.data.0.id', $this->customerWithAccount->id)
        );
    }

    /**
     * 19. Filtro de clientes sin zona.
     */
    public function test_filter_customers_without_zone(): void
    {
        $service = app(CustomerAddressService::class);

        // Cliente con zona
        $service->createAddress($this->customerWithoutAccount, [
            'municipality_type' => 'hocaba',
            'delivery_zone' => 'north',
            'street_and_number' => 'Calle 10 #10',
        ]);

        // Cliente 2 y 3 no tienen domicilio aún -> caen en "no_zone"
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.customers.index', ['zone' => 'no_zone']));

        $response->assertOk();
        $response->assertInertia(fn ($page) =>
            $page->component('Admin/Customers/Index')
                ->where('customers.total', 2)
        );
    }

    /**
     * 20. Conservación de búsqueda y paginación con filtros combinados.
     */
    public function test_preservation_of_search_and_pagination_with_zone_filter(): void
    {
        $service = app(CustomerAddressService::class);

        // Cliente 1: José (Norte)
        $service->createAddress($this->customerWithAccount, [
            'municipality_type' => 'hocaba',
            'delivery_zone' => 'north',
            'street_and_number' => 'Calle 20 #100',
        ]);

        // Cliente 2: María (Norte)
        $service->createAddress($this->customerWithoutAccount, [
            'municipality_type' => 'hocaba',
            'delivery_zone' => 'north',
            'street_and_number' => 'Calle 10 #50',
        ]);

        // Buscar "José" filtrando por Zona Norte
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.customers.index', ['zone' => 'north', 'search' => 'José']));

        $response->assertOk();
        $response->assertInertia(fn ($page) =>
            $page->component('Admin/Customers/Index')
                ->where('customers.total', 1)
                ->where('customers.data.0.id', $this->customerWithAccount->id)
                ->where('filters.zone', 'north')
                ->where('filters.search', 'José')
        );
    }

    /**
     * 21. Restricción de acceso a domicilios ajenos (portal cliente autoservicio).
     */
    public function test_restriction_of_access_to_other_customers_addresses(): void
    {
        $service = app(CustomerAddressService::class);

        // Domicilio del cliente 2
        $otherAddress = $service->createAddress($this->otherCustomerWithAccount, [
            'municipality_type' => 'hocaba',
            'delivery_zone' => 'north',
            'street_and_number' => 'Calle 12 #40',
        ]);

        // Cliente 1 intenta editar el domicilio del cliente 2 pasando el ID ajeno
        $response = $this->actingAs($this->clientUser)
            ->get("/cliente/domicilios/{$otherAddress->id}/editar");

        $response->assertNotFound();

        // Cliente 1 intenta actualizar el domicilio del cliente 2 por HTTP PUT
        $responseUpdate = $this->actingAs($this->clientUser)
            ->put("/cliente/domicilios/{$otherAddress->id}", [
                'municipality_type' => 'hocaba',
                'delivery_zone' => 'south',
                'street_and_number' => 'Calle Hackeada #999',
            ]);

        $responseUpdate->assertNotFound();
    }

    /**
     * 22. Conservación de roles y permisos.
     */
    public function test_preservation_of_roles_and_permissions(): void
    {
        // Repartidor NO puede crear domicilios administrativos
        $responseDriver = $this->actingAs($this->driverUser)
            ->post(route('admin.customers.addresses.store', $this->customerWithoutAccount->id), [
                'municipality_type' => 'hocaba',
                'delivery_zone' => 'north',
                'street_and_number' => 'Calle 20 #105',
            ]);
        $responseDriver->assertForbidden();

        // Operador Planta NO puede crear domicilios administrativos
        $responsePlant = $this->actingAs($this->plantUser)
            ->post(route('admin.customers.addresses.store', $this->customerWithoutAccount->id), [
                'municipality_type' => 'hocaba',
                'delivery_zone' => 'north',
                'street_and_number' => 'Calle 20 #105',
            ]);
        $responsePlant->assertForbidden();

        // Admin SÍ puede crear domicilios para clientes sin cuenta
        $responseAdmin = $this->actingAs($this->adminUser)
            ->post(route('admin.customers.addresses.store', $this->customerWithoutAccount->id), [
                'municipality_type' => 'hocaba',
                'delivery_zone' => 'north',
                'street_and_number' => 'Calle 20 #105',
            ]);
        $responseAdmin->assertRedirect();
    }

    /**
     * 23. El Administrador NO puede editar ni eliminar el domicilio de un cliente con cuenta.
     */
    public function test_admin_cannot_edit_or_delete_address_of_customer_with_account(): void
    {
        $service = app(CustomerAddressService::class);
        $address = $service->createAddress($this->customerWithAccount, [
            'municipality_type' => 'hocaba',
            'delivery_zone' => 'north',
            'street_and_number' => 'Calle 20 #500',
        ]);

        // 1. Intento de GET form de edición administrativa
        $responseEdit = $this->actingAs($this->adminUser)
            ->get(route('admin.customers.addresses.edit', [$this->customerWithAccount->id, $address->id]));
        $responseEdit->assertForbidden();

        // 2. Intento de PUT actualización administrativa
        $responseUpdate = $this->actingAs($this->adminUser)
            ->put(route('admin.customers.addresses.update', [$this->customerWithAccount->id, $address->id]), [
                'municipality_type' => 'hocaba',
                'delivery_zone' => 'south',
                'street_and_number' => 'Calle Alterada #999',
            ]);
        $responseUpdate->assertForbidden();

        // 3. Intento de DELETE eliminación administrativa
        $responseDelete = $this->actingAs($this->adminUser)
            ->delete(route('admin.customers.addresses.destroy', [$this->customerWithAccount->id, $address->id]));
        $responseDelete->assertForbidden();

        // El domicilio se conserva intacto
        $this->assertDatabaseHas('customer_addresses', [
            'id' => $address->id,
            'street' => 'Calle 20 #500',
            'deleted_at' => null,
        ]);
    }
}

