<?php

namespace Tests\Feature;

use App\Exceptions\OrderBusinessRuleException;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\OrderService;
use App\Services\StoreScheduleService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerBottleExclusivityTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $clientUser;
    protected User $repartidorUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->adminUser = User::factory()->create(['status' => 'active']);
        $this->adminUser->assignRole('Administrador');

        $this->clientUser = User::factory()->create(['status' => 'active']);
        $this->clientUser->assignRole('Cliente');

        $this->repartidorUser = User::factory()->create(['status' => 'active']);
        $this->repartidorUser->assignRole('Repartidor');

        // Congelar tiempo en horario válido (10:00 AM)
        Carbon::setTestNow(Carbon::create(2026, 10, 26, 10, 0, 0, StoreScheduleService::TIMEZONE));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Impide guardar cantidades positivas en fixed_bottles_count y exchange_bottles_count
     * para el mismo cliente a nivel de modelo Eloquent.
     */
    public function test_eloquent_prevents_simultaneous_positive_bottle_counts(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Un cliente maneja exclusivamente botellones fijos o botellones de cambio');

        Customer::create([
            'user_id' => null,
            'code' => 'CLI-TEST1',
            'nickname' => 'Cliente Test',
            'fixed_bottles_count' => 3,
            'exchange_bottles_count' => 2,
        ]);
    }

    /**
     * Permite guardar cliente con solo fijos, solo cambios o sin botellones.
     */
    public function test_allows_creating_customer_with_single_type_or_unassigned(): void
    {
        // Solo fijos
        $fixedCust = Customer::create([
            'code' => 'CLI-FIXED',
            'nickname' => 'Don Fijo',
            'fixed_bottles_count' => 4,
            'exchange_bottles_count' => 0,
        ]);
        $this->assertEquals(Customer::BOTTLE_TYPE_FIXED, $fixedCust->bottle_type);
        $this->assertEquals(4, $fixedCust->assigned_bottles_count);

        // Solo cambios
        $exchangeCust = Customer::create([
            'code' => 'CLI-EXCH',
            'nickname' => 'Doña Cambio',
            'fixed_bottles_count' => 0,
            'exchange_bottles_count' => 5,
        ]);
        $this->assertEquals(Customer::BOTTLE_TYPE_EXCHANGE, $exchangeCust->bottle_type);
        $this->assertEquals(5, $exchangeCust->assigned_bottles_count);

        // Sin botellones
        $noneCust = Customer::create([
            'code' => 'CLI-NONE',
            'nickname' => 'Sin Envases',
            'fixed_bottles_count' => 0,
            'exchange_bottles_count' => 0,
        ]);
        $this->assertEquals(Customer::BOTTLE_TYPE_UNASSIGNED, $noneCust->bottle_type);
        $this->assertEquals(0, $noneCust->assigned_bottles_count);
    }

    /**
     * Admin Customer Controller aplica exclusividad al crear cliente.
     */
    public function test_admin_customer_controller_enforces_single_type_on_create(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.customers.store'), [
                'nickname' => 'Nuevo Exclusivo',
                'bottle_type' => 'fixed',
                'bottles_count' => 3,
                'exchange_bottles_count' => 2, // Intentando forzar ambos en el payload
            ]);

        // El controlador asigna exclusivamente el tipo seleccionado
        $customer = Customer::where('nickname', 'Nuevo Exclusivo')->first();
        $this->assertNotNull($customer);
        $this->assertEquals(3, $customer->fixed_bottles_count);
        $this->assertEquals(0, $customer->exchange_bottles_count);
        $this->assertEquals(Customer::BOTTLE_TYPE_FIXED, $customer->bottle_type);
    }

    /**
     * Bloquea el cambio de modalidad de fijos a cambios si el cliente tiene botellones fijos en custodia.
     */
    public function test_blocks_switching_customer_type_when_fixed_bottles_are_in_custody(): void
    {
        $customer = Customer::create([
            'code' => 'CLI-CUSTODY',
            'nickname' => 'Cliente Custodia',
            'fixed_bottles_count' => 3,
            'exchange_bottles_count' => 0,
        ]);

        $address = CustomerAddress::create([
            'customer_id' => $customer->id,
            'street' => 'Calle 10',
            'exterior_number' => '100',
            'neighborhood' => 'Centro',
            'delivery_zone' => CustomerAddress::ZONE_NORTH,
            'is_default' => true,
        ]);

        $order = Order::create([
            'folio' => 'PED-CUSTODY',
            'customer_id' => $customer->id,
            'created_by_user_id' => $this->adminUser->id,
            'customer_address_id' => $address->id,
            'recipient_name' => 'Cliente Custodia',
            'delivery_street' => 'Calle 10',
            'status' => Order::STATUS_IN_PROCESS,
            'address_snapshot' => ['street' => 'Calle 10'],
        ]);

        // Item de fijos recogido por repartidor/planta que aún no ha sido entregado
        OrderItem::create([
            'order_id' => $order->id,
            'type' => OrderItem::TYPE_FIXED,
            'quantity' => 2,
            'collected_quantity' => 2,
            'delivered_quantity' => 0,
            'returned_empty_quantity' => 0,
        ]);

        // Admin intenta cambiarlo a botellones de cambio
        $response = $this->actingAs($this->adminUser)
            ->put(route('admin.customers.update', $customer->id), [
                'nickname' => 'Cliente Custodia',
                'bottle_type' => 'exchange',
                'bottles_count' => 2,
            ]);

        $response->assertSessionHasErrors('bottle_type');

        // El cliente conserva su modalidad fijos intacta
        $this->assertEquals(Customer::BOTTLE_TYPE_FIXED, $customer->fresh()->bottle_type);
    }

    /**
     * Rechaza pedidos que intenten combinar botellones fijos y de cambio a la vez.
     */
    public function test_rejects_order_combining_fixed_and_exchange_bottles(): void
    {
        $customer = Customer::create([
            'code' => 'CLI-ORD',
            'nickname' => 'Cliente Pedido',
            'fixed_bottles_count' => 3,
            'exchange_bottles_count' => 0,
        ]);

        $address = CustomerAddress::create([
            'customer_id' => $customer->id,
            'street' => 'Calle 50',
            'exterior_number' => '500',
            'neighborhood' => 'Centro',
            'delivery_zone' => CustomerAddress::ZONE_NORTH,
            'is_default' => true,
        ]);

        // 1. Intento de pedido vía AdminOrderController con ambos tipos
        $adminResponse = $this->actingAs($this->adminUser)
            ->post(route('admin.orders.store'), [
                'customer_id' => $customer->id,
                'customer_address_id' => $address->id,
                'fixed_quantity' => 2,
                'exchange_quantity' => 1,
            ]);

        $adminResponse->assertSessionHasErrors('quantity');

        // 2. Intento vía OrderService directamente
        $orderService = app(OrderService::class);
        $this->expectException(OrderBusinessRuleException::class);
        $this->expectExceptionMessage('No se permiten pedidos combinados');

        $orderService->createOrder($this->adminUser, [
            'customer_id' => $customer->id,
            'customer_address_id' => $address->id,
            'fixed_quantity' => 2,
            'exchange_quantity' => 1,
        ]);
    }

    /**
     * Rechaza pedidos que soliciten modalidad contraria a la exclusiva del cliente.
     */
    public function test_rejects_ordering_opposite_bottle_type_for_exclusive_customer(): void
    {
        $fixedCustomer = Customer::create([
            'code' => 'CLI-FX',
            'nickname' => 'Exclusivo Fijos',
            'fixed_bottles_count' => 3,
            'exchange_bottles_count' => 0,
        ]);

        $address = CustomerAddress::create([
            'customer_id' => $fixedCustomer->id,
            'street' => 'Calle 3',
            'exterior_number' => '30',
            'neighborhood' => 'Centro',
            'delivery_zone' => CustomerAddress::ZONE_NORTH,
            'is_default' => true,
        ]);

        // Intento de pedir cambios para cliente de fijos vía Admin
        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.orders.store'), [
                'customer_id' => $fixedCustomer->id,
                'customer_address_id' => $address->id,
                'fixed_quantity' => 0,
                'exchange_quantity' => 2,
            ]);

        $response->assertSessionHasErrors('exchange_quantity');
    }
}
