<?php

namespace Tests\Feature;

use App\Exceptions\OrderBusinessRuleException;
use App\Models\BottlePresentation;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\TruckInventory;
use App\Models\User;
use App\Services\OrderService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAccountBottleRulesTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $driverUser;
    protected OrderService $orderService;

    protected function setUp(): void
    {
        parent::setUp();

        \Carbon\Carbon::setTestNow(\Carbon\Carbon::create(2026, 11, 19, 11, 0, 0, 'America/Merida'));

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->orderService = app(OrderService::class);

        BottlePresentation::firstOrCreate(
            ['capacity' => 20.00],
            [
                'name' => 'Garrafón 20L',
                'refill_price' => 15.00,
                'bottle_price' => 120.00,
                'is_available_for_delivery' => true,
                'is_active' => true,
            ]
        );

        $this->adminUser = User::factory()->create(['email' => 'admin@rules.test']);
        $this->adminUser->assignRole('Administrador');

        $this->driverUser = User::factory()->create(['email' => 'driver@rules.test', 'status' => 'active']);
        $this->driverUser->assignRole('Repartidor');

        TruckInventory::updateOrCreate(
            ['driver_user_id' => $this->driverUser->id],
            ['exchange_full_available' => 20, 'exchange_empty_collected' => 0]
        );
    }

    protected function tearDown(): void
    {
        \Carbon\Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_account_user_registers_single_modality_and_respects_order_limit(): void
    {
        $response = $this->post('/register', [
            'name' => 'Cliente Cuenta Fijos',
            'email' => 'fijos@rules.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'bottle_type' => 'fixed',
            'bottles_count' => 2,
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'fijos@rules.test')->firstOrFail();
        $customer = $user->customer;
        $this->assertSame(2, (int) $customer->fixed_bottles_count);
        $this->assertSame(0, (int) $customer->exchange_bottles_count);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'street' => 'Calle 10',
            'exterior_number' => '55',
            'neighborhood' => 'Centro',
            'city' => 'Hocabá',
            'state' => 'Yucatán',
            'delivery_zone' => 'north',
            'is_default' => true,
        ]);

        // Pedir 3 fijos (supera su límite de 2) debe fallar
        try {
            $this->orderService->createOrder($user, ['fixed_quantity' => 3]);
            $this->fail('Debió rechazar pedir más botellones fijos de los registrados.');
        } catch (OrderBusinessRuleException $e) {
            $this->assertSame('fixed_quantity', $e->getErrorKey());
        }

        // Pedir 1 de cambio (modalidad contraria) debe fallar
        try {
            $this->orderService->createOrder($user, ['exchange_quantity' => 1]);
            $this->fail('Debió rechazar la modalidad contraria.');
        } catch (OrderBusinessRuleException $e) {
            $this->assertSame('exchange_quantity', $e->getErrorKey());
        }

        // Pedir 2 fijos (dentro del límite) debe funcionar
        $order = $this->orderService->createOrder($user, ['fixed_quantity' => 2]);
        $this->assertSame(2, $order->fixed_quantity);
    }

    public function test_account_user_cannot_register_both_modalities_simultaneously(): void
    {
        $response = $this->post('/register', [
            'name' => 'Mixto',
            'email' => 'mixto@rules.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'fixed_bottles_count' => 2,
            'exchange_bottles_count' => 3,
        ]);

        $response->assertSessionHasErrors('bottle_type');
        $this->assertNull(User::where('email', 'mixto@rules.test')->first());
    }

    public function test_customer_without_account_registers_without_bottles_and_orders_freely(): void
    {
        // Repartidor da de alta cliente sin cuenta sin pedir botellones
        $this->actingAs($this->driverUser)->post(route('repartidor.customers.store-quick'), [
            'nickname' => 'Don Libre',
            'delivery_zone' => 'north',
        ])->assertSessionHasNoErrors();

        $customer = Customer::where('nickname', 'Don Libre')->firstOrFail();
        $this->assertNull($customer->user_id);
        $this->assertSame(0, (int) $customer->fixed_bottles_count);
        $this->assertSame(0, (int) $customer->exchange_bottles_count);

        // Pedido al paso 1: pide 4 de cambio libremente sin amarrar su ficha
        $this->actingAs($this->driverUser)->post(route('repartidor.direct-service.store'), [
            'customer_id' => $customer->id,
            'exchange_quantity' => 4,
            'exchange_empty_received' => 4,
            'units_paid_cash' => 4,
        ])->assertSessionHasNoErrors();

        $customer->refresh();
        $this->assertSame(0, (int) $customer->fixed_bottles_count);
        $this->assertSame(0, (int) $customer->exchange_bottles_count);

        // Cerrar pedido activo y validar que en otro pedido puede elegir Fijos libremente
        $customer->orders()->update(['status' => 'delivered']);

        $this->actingAs($this->driverUser)->post(route('repartidor.direct-service.store'), [
            'customer_id' => $customer->id,
            'fixed_quantity' => 5,
        ])->assertSessionHasNoErrors();

        $customer->refresh();
        $this->assertSame(0, (int) $customer->fixed_bottles_count);
        $this->assertSame(0, (int) $customer->exchange_bottles_count);
    }

    public function test_admin_creates_customer_without_account_with_zero_bottles(): void
    {
        $this->actingAs($this->adminUser)->post(route('admin.customers.store'), [
            'nickname' => 'Tienda Sin Cuenta',
        ])->assertSessionHasNoErrors();

        $customer = Customer::where('nickname', 'Tienda Sin Cuenta')->firstOrFail();
        $this->assertNull($customer->user_id);
        $this->assertSame(0, (int) $customer->fixed_bottles_count);
        $this->assertSame(0, (int) $customer->exchange_bottles_count);
    }
}
