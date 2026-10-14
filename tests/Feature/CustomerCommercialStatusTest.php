<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerStatusHistory;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerCommercialStatusTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $clientUser;
    protected User $repartidorUser;
    protected Customer $customerWithAccount;
    protected Customer $customerWithoutAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        // 1. Usuario Administrador con permisos completos
        $this->adminUser = User::factory()->create([
            'name' => 'Admin Puritronic',
            'email' => 'admin.test@puritronic.com',
            'status' => 'active',
        ]);
        $this->adminUser->assignRole('Administrador');

        // 2. Usuario Cliente con rol Cliente
        $this->clientUser = User::factory()->create([
            'name' => 'Carlos Cliente',
            'email' => 'carlos.cliente@example.com',
            'status' => 'active',
        ]);
        $this->clientUser->assignRole('Cliente');

        // 3. Usuario Repartidor
        $this->repartidorUser = User::factory()->create([
            'name' => 'Roberto Repartidor',
            'email' => 'repartidor.test@puritronic.com',
            'status' => 'active',
        ]);
        $this->repartidorUser->assignRole('Repartidor');

        // 4. Cliente CON cuenta
        $this->customerWithAccount = Customer::create([
            'user_id' => $this->clientUser->id,
            'name' => $this->clientUser->name,
            'nickname' => 'Carlitos',
            'phone' => '9991112233',
            'email' => $this->clientUser->email,
            'customer_type' => 'particular',
            'price_tier' => 'general',
            'status' => Customer::STATUS_ACTIVE,
            'fixed_bottles_count' => 4,
            'exchange_bottles_count' => 2,
        ]);

        // 5. Cliente SIN cuenta (user_id = null)
        $this->customerWithoutAccount = Customer::create([
            'user_id' => null,
            'name' => 'Don Pepe Morales',
            'nickname' => 'Don Pepe',
            'phone' => '9994445566',
            'email' => null,
            'customer_type' => 'particular',
            'price_tier' => 'general',
            'status' => Customer::STATUS_ACTIVE,
            'fixed_bottles_count' => 3,
            'exchange_bottles_count' => 1,
        ]);
    }

    /**
     * 1. Administrador puede activar, desactivar y suspender a un cliente sin cuenta.
     */
    public function test_admin_can_change_commercial_status_for_customer_without_account(): void
    {
        $customer = $this->customerWithoutAccount;
        $this->assertEquals(Customer::STATUS_ACTIVE, $customer->status);

        // Suspender
        $response = $this->actingAs($this->adminUser)
            ->patch(route('admin.customers.status.update', $customer->id), [
                'status' => Customer::STATUS_SUSPENDED,
                'reason' => 'Suspensión temporal acordada con el cliente',
            ]);

        $response->assertRedirect(route('admin.customers.show', $customer->id));
        $response->assertSessionHas('success');

        $customer->refresh();
        $this->assertEquals(Customer::STATUS_SUSPENDED, $customer->status);
        $this->assertNull($customer->user_id);

        // Desactivar
        $response = $this->actingAs($this->adminUser)
            ->patch(route('admin.customers.status.update', $customer->id), [
                'status' => Customer::STATUS_INACTIVE,
                'reason' => 'Cliente inactivo por temporada',
            ]);

        $response->assertRedirect(route('admin.customers.show', $customer->id));
        $customer->refresh();
        $this->assertEquals(Customer::STATUS_INACTIVE, $customer->status);

        // Reactivar
        $response = $this->actingAs($this->adminUser)
            ->patch(route('admin.customers.status.update', $customer->id), [
                'status' => Customer::STATUS_ACTIVE,
                'reason' => 'Reactivación comercial aprobada',
            ]);

        $response->assertRedirect(route('admin.customers.show', $customer->id));
        $customer->refresh();
        $this->assertEquals(Customer::STATUS_ACTIVE, $customer->status);
    }

    /**
     * 2. Administrador puede cambiar el estado comercial de un cliente con cuenta sin afectar User.status.
     */
    public function test_admin_can_change_commercial_status_for_customer_with_account_without_altering_user_status(): void
    {
        $customer = $this->customerWithAccount;
        $user = $this->clientUser;

        $this->assertEquals(Customer::STATUS_ACTIVE, $customer->status);
        $this->assertEquals('active', $user->status);

        // Suspender cliente comercialmente
        $response = $this->actingAs($this->adminUser)
            ->patch(route('admin.customers.status.update', $customer->id), [
                'status' => Customer::STATUS_SUSPENDED,
                'reason' => 'Revisión comercial de envases',
            ]);

        $response->assertRedirect(route('admin.customers.show', $customer->id));

        $customer->refresh();
        $user->refresh();

        // El estado comercial cambió a suspendido
        $this->assertEquals(Customer::STATUS_SUSPENDED, $customer->status);
        // La cuenta de usuario sigue activa e intacta
        $this->assertEquals('active', $user->status);
        $this->assertTrue($user->isActive());
    }

    /**
     * 3. Cambiar el estado de acceso de User no altera Customer.status comercial.
     */
    public function test_altering_user_access_status_does_not_modify_customer_commercial_status(): void
    {
        $customer = $this->customerWithAccount;
        $user = $this->clientUser;

        $this->assertEquals(Customer::STATUS_ACTIVE, $customer->status);
        $this->assertEquals('active', $user->status);

        // Deshabilitar la cuenta del usuario
        $user->status = 'inactive';
        $user->save();

        $customer->refresh();

        // El usuario está inactivo pero el estado comercial sigue activo
        $this->assertEquals('inactive', $user->status);
        $this->assertEquals(Customer::STATUS_ACTIVE, $customer->status);
        $this->assertTrue($customer->isActive());
    }

    /**
     * 4. Cliente comercialmente suspendido o inactivo PUEDE seguir iniciando sesión y consultar su perfil.
     */
    public function test_suspended_and_inactive_client_can_still_access_portal_and_view_profile(): void
    {
        $customer = $this->customerWithAccount;
        $user = $this->clientUser;

        // Caso 1: Suspendido
        $customer->status = Customer::STATUS_SUSPENDED;
        $customer->save();

        $response = $this->actingAs($user)->get(route('cliente.profile.index'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Profile/Edit')
            ->where('clientData.customer.status', Customer::STATUS_SUSPENDED)
            ->where('clientData.customer.can_place_orders', false)
        );

        // Acceso al Dashboard de cliente
        $dashResponse = $this->actingAs($user)->get(route('cliente.dashboard'));
        $dashResponse->assertOk();
        $dashResponse->assertInertia(fn ($page) => $page
            ->component('Cliente/Dashboard')
            ->where('customer.status', Customer::STATUS_SUSPENDED)
        );

        // Caso 2: Inactivo
        $customer->status = Customer::STATUS_INACTIVE;
        $customer->save();
        $user->unsetRelation('customer');

        $response2 = $this->actingAs($user)->get(route('cliente.profile.index'));
        $response2->assertOk();
        $response2->assertInertia(fn ($page) => $page
            ->component('Profile/Edit')
            ->where('clientData.customer.status', Customer::STATUS_INACTIVE)
            ->where('clientData.customer.can_place_orders', false)
        );
    }

    /**
     * 5. canPlaceOrders() solo es verdadero cuando el cliente está en estado activo y no eliminado.
     */
    public function test_can_place_orders_business_logic(): void
    {
        $customer = $this->customerWithAccount;

        $customer->status = Customer::STATUS_ACTIVE;
        $this->assertTrue($customer->canPlaceOrders());
        $this->assertTrue($customer->isActive());
        $this->assertFalse($customer->isSuspended());
        $this->assertFalse($customer->isInactive());

        $customer->status = Customer::STATUS_INACTIVE;
        $this->assertFalse($customer->canPlaceOrders());
        $this->assertFalse($customer->isActive());
        $this->assertFalse($customer->isSuspended());
        $this->assertTrue($customer->isInactive());

        $customer->status = Customer::STATUS_SUSPENDED;
        $this->assertFalse($customer->canPlaceOrders());
        $this->assertFalse($customer->isActive());
        $this->assertTrue($customer->isSuspended());
        $this->assertFalse($customer->isInactive());
    }

    /**
     * 6. Se rechazan estados inválidos o manipulados.
     */
    public function test_invalid_commercial_status_is_rejected(): void
    {
        $customer = $this->customerWithAccount;

        $response = $this->actingAs($this->adminUser)
            ->patch(route('admin.customers.status.update', $customer->id), [
                'status' => 'bloqueado_arbitrario',
                'reason' => 'Prueba de valor no permitido',
            ]);

        $response->assertSessionHasErrors('status');
        $customer->refresh();
        $this->assertEquals(Customer::STATUS_ACTIVE, $customer->status);
    }

    /**
     * 7. Se rechaza intento de modificar estado si el usuario no tiene permisos (Cliente o Repartidor).
     */
    public function test_unauthorized_users_cannot_modify_commercial_status(): void
    {
        $customer = $this->customerWithoutAccount;

        // Intento por parte de un Cliente
        $responseClient = $this->actingAs($this->clientUser)
            ->patch(route('admin.customers.status.update', $customer->id), [
                'status' => Customer::STATUS_SUSPENDED,
            ]);
        $responseClient->assertForbidden();

        // Intento por parte de un Repartidor
        $responseRepartidor = $this->actingAs($this->repartidorUser)
            ->patch(route('admin.customers.status.update', $customer->id), [
                'status' => Customer::STATUS_SUSPENDED,
            ]);
        $responseRepartidor->assertForbidden();

        // El cliente conserva su estado intacto
        $customer->refresh();
        $this->assertEquals(Customer::STATUS_ACTIVE, $customer->status);
    }

    /**
     * 8. Se registra auditoría completa en el historial de cambios de estado comercial.
     */
    public function test_status_changes_are_logged_with_previous_and_new_status_reason_and_admin(): void
    {
        $customer = $this->customerWithAccount;

        $this->assertEquals(0, CustomerStatusHistory::where('customer_id', $customer->id)->count());

        // Cambio 1: Activo -> Suspendido
        $this->actingAs($this->adminUser)
            ->patch(route('admin.customers.status.update', $customer->id), [
                'status' => Customer::STATUS_SUSPENDED,
                'reason' => 'Motivo A: Retención temporal de servicio',
            ]);

        $this->assertEquals(1, CustomerStatusHistory::where('customer_id', $customer->id)->count());

        $history1 = CustomerStatusHistory::where('customer_id', $customer->id)->first();
        $this->assertEquals(Customer::STATUS_ACTIVE, $history1->previous_status);
        $this->assertEquals(Customer::STATUS_SUSPENDED, $history1->new_status);
        $this->assertEquals('Motivo A: Retención temporal de servicio', $history1->reason);
        $this->assertEquals($this->adminUser->id, $history1->changed_by);

        // Cambio 2: Suspendido -> Activo
        $this->actingAs($this->adminUser)
            ->patch(route('admin.customers.status.update', $customer->id), [
                'status' => Customer::STATUS_ACTIVE,
                'reason' => 'Motivo B: Reactivación autorizada',
            ]);

        $this->assertEquals(2, CustomerStatusHistory::where('customer_id', $customer->id)->count());

        $history2 = CustomerStatusHistory::where('customer_id', $customer->id)->latest('id')->first();
        $this->assertEquals(Customer::STATUS_SUSPENDED, $history2->previous_status);
        $this->assertEquals(Customer::STATUS_ACTIVE, $history2->new_status);
        $this->assertEquals('Motivo B: Reactivación autorizada', $history2->reason);
        $this->assertEquals($this->adminUser->id, $history2->changed_by);
    }

    /**
     * 9. Las transiciones redundantes (mismo estado) no generan registros de historial innecesarios.
     */
    public function test_redundant_status_transitions_do_not_create_duplicate_history(): void
    {
        $customer = $this->customerWithAccount;
        $this->assertEquals(Customer::STATUS_ACTIVE, $customer->status);

        $response = $this->actingAs($this->adminUser)
            ->patch(route('admin.customers.status.update', $customer->id), [
                'status' => Customer::STATUS_ACTIVE,
                'reason' => 'Reiterando mismo estado',
            ]);

        $response->assertRedirect(route('admin.customers.show', $customer->id));
        $response->assertSessionHas('info');

        $this->assertEquals(0, CustomerStatusHistory::where('customer_id', $customer->id)->count());
    }

    /**
     * 10. Filtro en el directorio de clientes por estado comercial combinado con zonas y búsqueda.
     */
    public function test_customers_index_filters_by_commercial_status_and_zone(): void
    {
        // Asignar domicilios en distintas zonas
        CustomerAddress::create([
            'customer_id' => $this->customerWithAccount->id,
            'street' => 'Calle 20 x 21 y 23',
            'exterior_number' => '100',
            'neighborhood' => 'Centro',
            'city' => 'Hocabá',
            'postal_code' => '97440',
            'delivery_zone' => 'north',
            'is_main' => true,
        ]);

        CustomerAddress::create([
            'customer_id' => $this->customerWithoutAccount->id,
            'street' => 'Calle 15 x 18',
            'exterior_number' => '102',
            'neighborhood' => 'Centro',
            'city' => 'Hocabá',
            'postal_code' => '97440',
            'delivery_zone' => 'south',
            'is_main' => true,
        ]);

        // Cambiar el cliente con cuenta a 'suspended'
        $this->customerWithAccount->status = Customer::STATUS_SUSPENDED;
        $this->customerWithAccount->save();

        // 1. Filtrar solo 'suspended'
        $responseSuspended = $this->actingAs($this->adminUser)
            ->get(route('admin.customers.index', ['status' => 'suspended']));
        $responseSuspended->assertOk();
        $responseSuspended->assertInertia(fn ($page) => $page
            ->component('Admin/Customers/Index')
            ->has('customers.data', 1)
            ->where('customers.data.0.id', $this->customerWithAccount->id)
        );

        // 2. Filtrar solo 'active'
        $responseActive = $this->actingAs($this->adminUser)
            ->get(route('admin.customers.index', ['status' => 'active']));
        $responseActive->assertOk();
        $responseActive->assertInertia(fn ($page) => $page
            ->component('Admin/Customers/Index')
            ->has('customers.data', 1)
            ->where('customers.data.0.id', $this->customerWithoutAccount->id)
        );

        // 3. Filtrar combinación Zona Norte + Suspendido (Debe coincidir customerWithAccount)
        $responseNorthSuspended = $this->actingAs($this->adminUser)
            ->get(route('admin.customers.index', ['status' => 'suspended', 'zone' => 'north']));
        $responseNorthSuspended->assertOk();
        $responseNorthSuspended->assertInertia(fn ($page) => $page
            ->component('Admin/Customers/Index')
            ->has('customers.data', 1)
            ->where('customers.data.0.id', $this->customerWithAccount->id)
        );

        // 4. Filtrar combinación Zona Sur + Suspendido (No hay ningún suspendido en zona sur)
        $responseSouthSuspended = $this->actingAs($this->adminUser)
            ->get(route('admin.customers.index', ['status' => 'suspended', 'zone' => 'south']));
        $responseSouthSuspended->assertOk();
        $responseSouthSuspended->assertInertia(fn ($page) => $page
            ->component('Admin/Customers/Index')
            ->has('customers.data', 0)
        );
    }

    /**
     * 11. Cambiar estado comercial no altera cantidades de botellones, domicilios ni código CLI-XXXX.
     */
    public function test_commercial_status_update_does_not_modify_bottles_addresses_or_code(): void
    {
        $customer = $this->customerWithAccount;
        $initialCode = $customer->code;
        $initialFixed = $customer->fixed_bottles_count;
        $initialExchange = $customer->exchange_bottles_count;

        $this->actingAs($this->adminUser)
            ->patch(route('admin.customers.status.update', $customer->id), [
                'status' => Customer::STATUS_SUSPENDED,
                'reason' => 'Verificación de integridad de datos',
            ]);

        $customer->refresh();

        $this->assertEquals($initialCode, $customer->code);
        $this->assertEquals($initialFixed, $customer->fixed_bottles_count);
        $this->assertEquals($initialExchange, $customer->exchange_bottles_count);
        $this->assertEquals(Customer::STATUS_SUSPENDED, $customer->status);
    }
}
