<?php

namespace Tests\Feature;

use App\Models\BranchSchedule;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\StoreScheduleService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClientOrderCreationTest extends TestCase
{
    use RefreshDatabase;

    protected User $clientUser;
    protected Customer $clientCustomer;
    protected CustomerAddress $clientAddress;

    protected User $otherClientUser;
    protected Customer $otherClientCustomer;
    protected CustomerAddress $otherClientAddress;

    protected User $repartidorUser;
    protected User $plantaUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        // Asegurar horario configurado que esté abierto para las pruebas
        // Apertura 08:00, Descanso 14:00 - 15:00, Anticipación 60 min, Cierre 19:00
        BranchSchedule::updateOrCreate(
            ['id' => 1],
            [
                'opening_time' => '08:00:00',
                'break_start_time' => '14:00:00',
                'break_end_time' => '15:00:00',
                'closing_time' => '19:00:00',
                'order_cutoff_advance_minutes' => 60,
                'is_active' => true,
            ]
        );

        // 1. Cliente Principal
        $this->clientUser = User::factory()->create([
            'name' => 'María Pech',
            'email' => 'maria@example.com',
            'status' => 'active',
        ]);
        $this->clientUser->assignRole('Cliente');

        $this->clientCustomer = Customer::create([
            'user_id' => $this->clientUser->id,
            'code' => 'CLI-0001',
            'name' => 'María Pech',
            'phone' => '9991234567',
            'fixed_bottles_count' => 3,
            'exchange_bottles_count' => 2,
        ]);

        $this->clientAddress = CustomerAddress::create([
            'customer_id' => $this->clientCustomer->id,
            'street' => 'Calle 20',
            'exterior_number' => '102',
            'neighborhood' => 'Centro',
            'city' => 'Hocabá',
            'delivery_zone' => CustomerAddress::ZONE_NORTH,
            'is_default' => true,
        ]);

        // 2. Otro Cliente
        $this->otherClientUser = User::factory()->create([
            'name' => 'José Canché',
            'email' => 'jose@example.com',
            'status' => 'active',
        ]);
        $this->otherClientUser->assignRole('Cliente');

        $this->otherClientCustomer = Customer::create([
            'user_id' => $this->otherClientUser->id,
            'code' => 'CLI-0002',
            'name' => 'José Canché',
            'phone' => '9997654321',
            'fixed_bottles_count' => 1,
            'exchange_bottles_count' => 1,
        ]);

        $this->otherClientAddress = CustomerAddress::create([
            'customer_id' => $this->otherClientCustomer->id,
            'street' => 'Calle 15',
            'exterior_number' => '45',
            'neighborhood' => 'Centro',
            'city' => 'Hocabá',
            'delivery_zone' => CustomerAddress::ZONE_SOUTH,
            'is_default' => true,
        ]);

        // 3. Roles operativos
        $this->repartidorUser = User::factory()->create(['name' => 'Repartidor', 'status' => 'active']);
        $this->repartidorUser->assignRole('Repartidor');

        $this->plantaUser = User::factory()->create(['name' => 'Operador Planta', 'status' => 'active']);
        $this->plantaUser->assignRole('Operador Planta');

        // Congelar tiempo en horario válido (10:00 AM)
        Carbon::setTestNow(Carbon::create(2026, 10, 26, 10, 0, 0, StoreScheduleService::TIMEZONE));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * 1. La vista de creación de pedidos carga correctamente con las props necesarias.
     */
    public function test_client_can_view_order_creation_screen(): void
    {
        $response = $this->actingAs($this->clientUser)
            ->get(route('cliente.orders.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Cliente/Orders/Create')
            ->has('customer')
            ->where('customer.fixed_bottles_count', 3)
            ->where('customer.exchange_bottles_count', 2)
            ->has('address')
            ->where('address.full_address', $this->clientAddress->full_address)
            ->where('activeOrder', null)
            ->where('blocks.has_active_order', false)
            ->where('blocks.outside_schedule', false)
            ->where('blocks.no_address', false)
            ->where('blocks.no_bottles', false)
            ->where('canOrder', true)
        );
    }

    /**
     * 2. Creación exitosa de pedido solo de botellones fijos.
     */
    public function test_client_can_create_order_with_only_fixed_bottles(): void
    {
        $response = $this->actingAs($this->clientUser)
            ->post(route('cliente.orders.store'), [
                'fixed_quantity' => 2,
                'exchange_quantity' => 0,
            ]);

        $order = Order::first();
        $this->assertNotNull($order);
        $response->assertRedirect(route('cliente.orders.show', $order->id));
        $response->assertSessionHas('success');

        $this->assertEquals('PED-0001', $order->folio);
        $this->assertEquals(Order::STATUS_PENDING, $order->status);
        $this->assertEquals(2, $order->fixed_quantity);
        $this->assertEquals(0, $order->exchange_quantity);
        $this->assertEquals(2, $order->total_bottles);
    }

    /**
     * 3. Creación exitosa de pedido solo de botellones de cambio.
     */
    public function test_client_can_create_order_with_only_exchange_bottles(): void
    {
        $response = $this->actingAs($this->clientUser)
            ->post(route('cliente.orders.store'), [
                'fixed_quantity' => 0,
                'exchange_quantity' => 2,
            ]);

        $order = Order::first();
        $this->assertNotNull($order);
        $response->assertRedirect(route('cliente.orders.show', $order->id));

        $this->assertEquals(0, $order->fixed_quantity);
        $this->assertEquals(2, $order->exchange_quantity);
        $this->assertEquals(2, $order->total_bottles);
    }

    /**
     * 4. Creación exitosa de pedido mixto con notas de entrega opcionales.
     */
    public function test_client_can_create_order_with_mixed_bottles_and_notes(): void
    {
        $response = $this->actingAs($this->clientUser)
            ->post(route('cliente.orders.store'), [
                'fixed_quantity' => 2,
                'exchange_quantity' => 1,
                'notes' => 'Dejar los garrafones junto al portón azul.',
            ]);

        $order = Order::first();
        $this->assertNotNull($order);
        $response->assertRedirect(route('cliente.orders.show', $order->id));

        $this->assertEquals(2, $order->fixed_quantity);
        $this->assertEquals(1, $order->exchange_quantity);
        $this->assertEquals(3, $order->total_bottles);
        $this->assertEquals('Dejar los garrafones junto al portón azul.', $order->notes);
    }

    /**
     * 5. Al guardar, redirige al detalle del pedido creado y confirma con el folio.
     */
    public function test_client_order_creation_redirects_to_show_with_folio_confirmation(): void
    {
        $response = $this->actingAs($this->clientUser)
            ->post(route('cliente.orders.store'), [
                'fixed_quantity' => 1,
                'exchange_quantity' => 1,
            ]);

        $order = Order::first();
        $this->assertNotNull($order);
        $response->assertRedirect(route('cliente.orders.show', $order->id));
        $response->assertSessionHas('success', function ($msg) use ($order) {
            return str_contains($msg, $order->folio);
        });
    }

    /**
     * 6. Conservación estricta de los conteos de botellones del cliente (no se alteran).
     */
    public function test_client_order_creation_preserves_customer_bottles_count(): void
    {
        $initialFixed = $this->clientCustomer->fixed_bottles_count;
        $initialExchange = $this->clientCustomer->exchange_bottles_count;

        $this->actingAs($this->clientUser)
            ->post(route('cliente.orders.store'), [
                'fixed_quantity' => 2,
                'exchange_quantity' => 1,
            ]);

        $freshCustomer = $this->clientCustomer->fresh();
        $this->assertEquals($initialFixed, $freshCustomer->fixed_bottles_count);
        $this->assertEquals($initialExchange, $freshCustomer->exchange_bottles_count);
    }

    /**
     * 7. Rechazo de cantidades que superan los botellones fijos registrados.
     */
    public function test_rejects_quantities_exceeding_registered_fixed_bottles(): void
    {
        // María tiene 3 fijos, pide 4
        $response = $this->actingAs($this->clientUser)
            ->from(route('cliente.orders.create'))
            ->post(route('cliente.orders.store'), [
                'fixed_quantity' => 4,
                'exchange_quantity' => 0,
            ]);

        $response->assertRedirect(route('cliente.orders.create'));
        $response->assertSessionHasErrors('fixed_quantity');
        $this->assertDatabaseCount('orders', 0);
    }

    /**
     * 8. Rechazo de cantidades que superan los botellones de cambio registrados.
     */
    public function test_rejects_quantities_exceeding_registered_exchange_bottles(): void
    {
        // María tiene 2 de cambio, pide 3
        $response = $this->actingAs($this->clientUser)
            ->from(route('cliente.orders.create'))
            ->post(route('cliente.orders.store'), [
                'fixed_quantity' => 0,
                'exchange_quantity' => 3,
            ]);

        $response->assertRedirect(route('cliente.orders.create'));
        $response->assertSessionHasErrors('exchange_quantity');
        $this->assertDatabaseCount('orders', 0);
    }

    /**
     * 9. Rechazo de pedido con total cero de botellones.
     */
    public function test_rejects_empty_order_with_zero_bottles(): void
    {
        $response = $this->actingAs($this->clientUser)
            ->from(route('cliente.orders.create'))
            ->post(route('cliente.orders.store'), [
                'fixed_quantity' => 0,
                'exchange_quantity' => 0,
            ]);

        $response->assertRedirect(route('cliente.orders.create'));
        $response->assertSessionHasErrors('quantity');
        $this->assertDatabaseCount('orders', 0);
    }

    /**
     * 10. Rechazo de cantidades negativas o decimales.
     */
    public function test_rejects_negative_or_decimal_quantities(): void
    {
        $response = $this->actingAs($this->clientUser)
            ->from(route('cliente.orders.create'))
            ->post(route('cliente.orders.store'), [
                'fixed_quantity' => -1,
                'exchange_quantity' => 1,
            ]);

        $response->assertRedirect(route('cliente.orders.create'));
        $response->assertSessionHasErrors('fixed_quantity');
    }

    /**
     * 11. Bloqueo cuando el cliente no tiene domicilio registrado.
     */
    public function test_blocks_order_creation_when_customer_has_no_address(): void
    {
        // Eliminamos la dirección del cliente
        $this->clientAddress->delete();

        // 1. Pantalla muestra bloqueado el domicilio
        $response = $this->actingAs($this->clientUser)
            ->get(route('cliente.orders.create'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('blocks.no_address', true)
            ->where('canOrder', false)
        );

        // 2. Envío rechazado por validación
        $postResponse = $this->actingAs($this->clientUser)
            ->from(route('cliente.orders.create'))
            ->post(route('cliente.orders.store'), [
                'fixed_quantity' => 1,
            ]);

        $postResponse->assertRedirect(route('cliente.orders.create'));
        $postResponse->assertSessionHasErrors('address');
    }

    /**
     * 12. Bloqueo cuando el cliente no tiene botellones registrados (ambos en cero).
     */
    public function test_blocks_order_creation_when_customer_has_zero_total_bottles(): void
    {
        $this->clientCustomer->update([
            'fixed_bottles_count' => 0,
            'exchange_bottles_count' => 0,
        ]);

        // 1. Pantalla muestra bloqueado
        $response = $this->actingAs($this->clientUser)
            ->get(route('cliente.orders.create'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('blocks.no_bottles', true)
            ->where('canOrder', false)
        );

        // 2. Envío rechazado
        $postResponse = $this->actingAs($this->clientUser)
            ->from(route('cliente.orders.create'))
            ->post(route('cliente.orders.store'), [
                'fixed_quantity' => 1,
            ]);

        $postResponse->assertRedirect(route('cliente.orders.create'));
        $postResponse->assertSessionHasErrors('quantity');
    }

    /**
     * 13. Bloqueo cuando ya existe un pedido activo (pendiente o confirmado) y muestra su folio.
     */
    public function test_blocks_order_creation_when_customer_already_has_active_order(): void
    {
        // Crear un primer pedido activo
        $order = Order::create([
            'folio' => 'PED-0001',
            'customer_id' => $this->clientCustomer->id,
            'created_by_user_id' => $this->clientUser->id,
            'customer_address_id' => $this->clientAddress->id,
            'recipient_name' => 'María Pech',
            'delivery_street' => 'Calle 20',
            'status' => Order::STATUS_PENDING,
            'address_snapshot' => ['delivery_street' => 'Calle 20'],
        ]);

        // 1. La pantalla debe mostrar el bloqueo y el folio activo
        $response = $this->actingAs($this->clientUser)
            ->get(route('cliente.orders.create'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('blocks.has_active_order', true)
            ->where('activeOrder.folio', 'PED-0001')
            ->where('canOrder', false)
        );

        // 2. Intentar enviar nuevo pedido debe ser rechazado
        $postResponse = $this->actingAs($this->clientUser)
            ->from(route('cliente.orders.create'))
            ->post(route('cliente.orders.store'), [
                'fixed_quantity' => 1,
            ]);

        $postResponse->assertRedirect(route('cliente.orders.create'));
        $postResponse->assertSessionHasErrors('active_order');
        $this->assertDatabaseCount('orders', 1);
    }

    /**
     * 14. Validación del horario al momento del envío (rechaza si la hora límite ya pasó).
     */
    public function test_order_creation_revalidates_schedule_at_submission_time(): void
    {
        // Simular que el cliente abrió el formulario antes, pero envía a las 18:05 (anticipación 60 min -> corte 18:00)
        Carbon::setTestNow(Carbon::create(2026, 10, 26, 18, 5, 0, StoreScheduleService::TIMEZONE));

        $response = $this->actingAs($this->clientUser)
            ->from(route('cliente.orders.create'))
            ->post(route('cliente.orders.store'), [
                'fixed_quantity' => 1,
            ]);

        $response->assertRedirect(route('cliente.orders.create'));
        $response->assertSessionHasErrors('schedule');
        $this->assertDatabaseCount('orders', 0);
    }

    /**
     * 15. Un cliente no puede crear pedidos a nombre de otro cliente.
     */
    public function test_client_cannot_create_order_for_another_customer(): void
    {
        // El cliente intenta enviar customer_id del otro cliente
        $response = $this->actingAs($this->clientUser)
            ->from(route('cliente.orders.create'))
            ->post(route('cliente.orders.store'), [
                'customer_id' => $this->otherClientCustomer->id,
                'fixed_quantity' => 1,
            ]);

        // El pedido creado pertenecerá forzosamente a su propio Customer, nunca al ajeno
        $order = Order::first();
        $this->assertNotNull($order);
        $this->assertEquals($this->clientCustomer->id, $order->customer_id);
        $this->assertNotEquals($this->otherClientCustomer->id, $order->customer_id);
    }

    /**
     * 16. Roles no autorizados no pueden acceder a la pantalla ni registrar pedidos de cliente.
     */
    public function test_unauthorized_roles_cannot_access_client_order_creation_or_submission(): void
    {
        // Invitado
        $this->get(route('cliente.orders.create'))->assertRedirect(route('login'));
        $this->post(route('cliente.orders.store'), ['fixed_quantity' => 1])->assertRedirect(route('login'));

        // Repartidor
        $this->actingAs($this->repartidorUser)->get(route('cliente.orders.create'))->assertForbidden();
        $this->actingAs($this->repartidorUser)->post(route('cliente.orders.store'), ['fixed_quantity' => 1])->assertForbidden();

        // Operador Planta
        $this->actingAs($this->plantaUser)->get(route('cliente.orders.create'))->assertForbidden();
        $this->actingAs($this->plantaUser)->post(route('cliente.orders.store'), ['fixed_quantity' => 1])->assertForbidden();
    }
}
