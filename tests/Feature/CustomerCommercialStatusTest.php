<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerAddress;
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
            'fixed_bottles_count' => 4,
            'exchange_bottles_count' => 2,
        ]);

        // 5. Cliente SIN cuenta (user_id = null, sin teléfono ni correo requeridos)
        $this->customerWithoutAccount = Customer::create([
            'user_id' => null,
            'name' => 'Don Pepe Morales',
            'nickname' => 'Don Pepe',
            'phone' => null,
            'email' => null,
            'notes' => 'Tocar timbre blanco en portón',
            'fixed_bottles_count' => 3,
            'exchange_bottles_count' => 1,
        ]);
    }

    /**
     * 1. La ruta de actualización de estado comercial fue eliminada completamente y retorna 404.
     */
    public function test_commercial_status_update_route_is_removed(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->patch("/admin/customers/{$this->customerWithAccount->id}/status", [
                'status' => 'inactive',
            ]);

        $response->assertNotFound();
    }

    /**
     * 2. No se permite desactivar cuentas de clientes desde el panel administrativo.
     */
    public function test_client_user_account_cannot_be_deactivated_from_admin_panel(): void
    {
        $this->assertEquals('active', $this->clientUser->status);

        $response = $this->actingAs($this->adminUser)
            ->patch(route('admin.users.toggle-status', $this->clientUser->id));

        $response->assertSessionHas('error', 'No está permitido desactivar cuentas de clientes desde el panel administrativo. Una cuenta registrada permanece habilitada.');

        $this->clientUser->refresh();
        $this->assertEquals('active', $this->clientUser->status);
        $this->assertTrue($this->clientUser->isActive());
    }

    /**
     * 3. canPlaceOrders() es verdadero para clientes no eliminados, sin depender de estados comerciales.
     */
    public function test_can_place_orders_business_logic(): void
    {
        $customer = $this->customerWithAccount;

        $this->assertTrue($customer->canPlaceOrders());

        // Al eliminar lógicamente (soft delete), no puede ordenar
        $customer->delete();
        $this->assertFalse($customer->canPlaceOrders());
    }

    /**
     * 4. Ficha de cliente muestra únicamente si tiene cuenta o no, y no expone estados comerciales ni de acceso.
     */
    public function test_customer_show_view_props_do_not_contain_commercial_or_access_statuses(): void
    {
        // 1. Cliente CON cuenta
        $responseWithAccount = $this->actingAs($this->adminUser)
            ->get(route('admin.customers.show', $this->customerWithAccount->id));

        $responseWithAccount->assertOk();
        $responseWithAccount->assertInertia(fn ($page) => $page
            ->component('Admin/Customers/Show')
            ->where('customer.has_account', true)
            ->where('customer.name', $this->clientUser->name)
            ->where('customer.nickname', 'Carlitos')
            ->where('customer.email', $this->clientUser->email)
            ->where('customer.phone', '9991112233')
            ->where('customer.fixed_bottles_count', 4)
            ->where('customer.exchange_bottles_count', 2)
            ->where('customer.can_manage_address', false) // Administrador no puede editar domicilio de cliente con cuenta
            ->missing('customer.status')
            ->missing('customer.status_label')
            ->missing('status_histories')
            ->missing('available_statuses')
        );

        // 2. Cliente SIN cuenta
        $responseWithoutAccount = $this->actingAs($this->adminUser)
            ->get(route('admin.customers.show', $this->customerWithoutAccount->id));

        $responseWithoutAccount->assertOk();
        $responseWithoutAccount->assertInertia(fn ($page) => $page
            ->component('Admin/Customers/Show')
            ->where('customer.has_account', false)
            ->where('customer.name', 'Don Pepe Morales')
            ->where('customer.nickname', 'Don Pepe')
            ->where('customer.notes', 'Tocar timbre blanco en portón')
            ->where('customer.fixed_bottles_count', 3)
            ->where('customer.exchange_bottles_count', 1)
            ->where('customer.can_manage_address', true)
            ->missing('customer.status')
            ->missing('customer.status_label')
            ->missing('status_histories')
            ->missing('available_statuses')
        );
    }

    /**
     * 5. Cliente sin cuenta se puede crear sin teléfono, correo ni estado comercial.
     */
    public function test_customer_without_account_can_be_created_without_phone_email_or_status(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.customers.store'), [
                'name' => 'Doña Remedios Várguez',
                'nickname' => 'Doña Reme',
                'fixed_bottles_count' => 2,
                'exchange_bottles_count' => 1,
                'notes' => 'Portón verde con reja',
            ]);

        $createdCustomer = Customer::where('nickname', 'Doña Reme')->first();
        $this->assertNotNull($createdCustomer);
        $this->assertNull($createdCustomer->user_id);
        $this->assertNull($createdCustomer->phone);
        $this->assertNull($createdCustomer->email);
        $this->assertEquals(2, $createdCustomer->fixed_bottles_count);
        $this->assertEquals(1, $createdCustomer->exchange_bottles_count);
        $this->assertEquals('Doña Remedios Várguez', $createdCustomer->name);
        $this->assertStringStartsWith('CLI-', $createdCustomer->code);

        $response->assertRedirect(route('admin.customers.show', $createdCustomer->id));
    }

    /**
     * 6. Directorio de clientes no filtra ni expone el filtro de estado comercial.
     */
    public function test_customers_index_does_not_filter_by_commercial_status(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.customers.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Customers/Index')
            ->missing('filters.status')
            ->has('customers.data', 2)
        );
    }
}
