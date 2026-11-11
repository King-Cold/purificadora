<?php

namespace Tests\Feature;

use App\Models\BottleCustodyMovement;
use App\Models\BottleIncident;
use App\Models\BottlePresentation;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderCharge;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\PlantInventory;
use App\Models\TruckInventory;
use App\Models\User;
use App\Services\OrderCustodyService;
use App\Services\PlantInventoryService;
use App\Services\SettingService;
use App\Services\TruckInventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FixedBottlesFullCycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $driver;
    protected User $operator;
    protected User $clientUser;
    protected Customer $customer;
    protected CustomerAddress $address;
    protected BottlePresentation $pres20;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->pres20 = BottlePresentation::firstOrCreate(
            ['capacity' => 20.00],
            [
                'name' => 'Botellón 20 Litros',
                'capacity' => 20.00,
                'unit' => 'Litros',
                'refill_price' => 15.00,
                'store_price' => 10.00,
                'new_bottle_price' => 95.00,
                'image_path' => '/images/garrafon-20l.png',
                'is_available_for_delivery' => true,
                'is_active' => true,
            ]
        );

        $this->admin = User::factory()->create(['name' => 'Admin Test', 'status' => 'active']);
        $this->admin->assignRole('Administrador');

        $this->driver = User::factory()->create(['name' => 'Repartidor Test', 'status' => 'active']);
        $this->driver->assignRole('Repartidor');

        $this->operator = User::factory()->create(['name' => 'Operador Test', 'status' => 'active']);
        $this->operator->assignRole('Operador Planta');

        $this->clientUser = User::factory()->create(['name' => 'Juana Cliente', 'status' => 'active']);
        $this->clientUser->assignRole('Cliente');

        $this->customer = Customer::create([
            'user_id' => $this->clientUser->id,
            'code' => 'CLI-JUANA-01',
            'name' => 'Juana Morales Rivas',
            'nickname' => 'Juana',
            'phone' => '9991234567',
            'fixed_bottles_count' => 4,
            'exchange_bottles_count' => 0,
        ]);

        $this->address = CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'street' => 'Calle 60',
            'exterior_number' => '123',
            'neighborhood' => 'Centro',
            'city' => 'Mérida',
            'state' => 'Yucatán',
            'postal_code' => '97000',
            'delivery_zone' => 'norte',
            'is_default' => true,
        ]);

        PlantInventory::create([
            'id' => 1,
            'exchange_empty_available' => 10,
            'exchange_full_available' => 20,
        ]);

        app(TruckInventoryService::class)->getOrCreateTruckInventory($this->driver);
        app(SettingService::class)->setMaxPendingPaymentBottles(4, $this->admin->name);
    }

    /**
     * Helper para crear pedidos de botellones fijos de 20 L.
     */
    protected function createFixedOrder(int $quantity, ?Customer $customer = null): Order
    {
        $targetCustomer = $customer ?: $this->customer;
        $order = Order::create([
            'folio' => 'ORD-' . strtoupper(uniqid()),
            'customer_id' => $targetCustomer->id,
            'created_by_user_id' => $targetCustomer->user_id,
            'customer_address_id' => $this->address->id,
            'recipient_name' => $targetCustomer->name,
            'recipient_phone' => $targetCustomer->phone,
            'delivery_street' => $this->address->street,
            'delivery_exterior_number' => $this->address->exterior_number,
            'delivery_neighborhood' => $this->address->neighborhood,
            'delivery_zone' => $this->address->delivery_zone,
            'address_snapshot' => $this->address->street . ' #' . $this->address->exterior_number . ', ' . $this->address->neighborhood,
            'status' => Order::STATUS_CONFIRMED,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'type' => OrderItem::TYPE_FIXED,
            'quantity' => $quantity,
            'unit_price' => 15.00,
            'status' => OrderItem::STATUS_PENDING,
        ]);

        return $order;
    }

    public function test_order_appears_directly_in_por_recoger_without_prior_route_assignment(): void
    {
        $order = $this->createFixedOrder(2);

        $response = $this->actingAs($this->driver)->get(route('repartidor.collections.index'));
        $response->assertOk();
        $response->assertInertia(fn ($page) =>
            $page->component('Repartidor/Collections/Index')
                ->has('orders', 1)
                ->where('orders.0.id', $order->id)
                ->where('orders.0.fixed_quantity_pending', 2)
                ->where('orders.0.customer.nickname', 'Juana')
        );
    }

    public function test_repartidor_collect_fixed_with_advance_payment_and_complete_cycle(): void
    {
        $order = $this->createFixedOrder(2);

        // 1. Repartidor confirma recogida con pago anticipado ($30 por 2 fijos)
        $resp = $this->actingAs($this->driver)->post(route('repartidor.orders.collect-fixed', $order->id), [
            'quantity' => 2,
            'is_advance_payment' => true,
            'cash_tendered' => 30.00,
            'notes' => 'Recogida con pago anticipado en domicilio',
        ]);
        $resp->assertRedirect();
        $resp->assertSessionHas('success');

        $item = $order->items()->first();
        $this->assertEquals(2, $item->collected_quantity);
        $this->assertEquals(0, $item->delivered_quantity);
        $this->assertEquals(Order::STATUS_IN_PROCESS, $order->fresh()->status);

        // Verificar que se registró el cobro anticipado
        $charge = OrderCharge::where('order_id', $order->id)->first();
        $this->assertNotNull($charge);
        $this->assertEquals(OrderCharge::STATUS_PAID, $charge->status);
        $this->assertEquals(30.00, (float)$charge->paid_amount);

        $payment = OrderPayment::where('order_id', $order->id)->first();
        $this->assertNotNull($payment);
        $this->assertEquals(30.00, (float)$payment->amount);
        $this->assertEquals(2, $payment->bottles_covered_count);

        // 2. Operador de Planta ve el pedido en "Por recibir" y confirma recepción
        $respPlanta = $this->actingAs($this->operator)->get(route('planta.fijos.index', ['tab' => 'por-recibir']));
        $respPlanta->assertOk();
        $respPlanta->assertInertia(fn ($page) =>
            $page->has('fixedPending', 1)
                ->where('fixedPending.0.expected_quantity', 2)
        );

        $respRecv = $this->actingAs($this->operator)->post(route('planta.reception.confirm-fixed'), [
            'order_item_id' => $item->id,
            'received_quantity' => 2,
        ]);
        $respRecv->assertRedirect();
        $this->assertEquals(2, $item->fresh()->received_at_plant_quantity);

        // 3. Operador de Planta marca como llenados en "Por llenar"
        $respLlenar = $this->actingAs($this->operator)->post(route('planta.fijos.mark-filled'), [
            'order_item_id' => $item->id,
            'quantity' => 2,
        ]);
        $respLlenar->assertRedirect();
        $this->assertEquals(2, $item->fresh()->prepared_quantity);
        $this->assertEquals(OrderItem::STATUS_READY_FOR_DELIVERY, $item->fresh()->status);

        // 4. Repartidor ve los fijos en "Listos para cargar" y acepta la carga física
        $respReady = $this->actingAs($this->driver)->get(route('repartidor.ready-to-load.index'));
        $respReady->assertOk();
        $respReady->assertInertia(fn ($page) =>
            $page->has('readyItems', 1)
                ->where('readyItems.0.ready_quantity', 2)
        );

        $respLoad = $this->actingAs($this->driver)->post(route('repartidor.ready-to-load.accept'), [
            'order_id' => $order->id,
            'quantity' => 2,
        ]);
        $respLoad->assertRedirect();
        $this->assertEquals(2, $item->fresh()->loaded_quantity);

        // 5. Repartidor ve el pedido en "Por entregar" reconociendo pago previo
        $respEntregar = $this->actingAs($this->driver)->get(route('repartidor.deliveries.index'));
        $respEntregar->assertOk();
        $respEntregar->assertInertia(fn ($page) =>
            $page->has('deliveries', 1)
                ->where('deliveries.0.is_advance_paid', true)
                ->where('deliveries.0.unpaid_amount', 0)
        );

        // Repartidor finaliza la entrega sin duplicar cobros
        $respDone = $this->actingAs($this->driver)->post(route('repartidor.orders.deliver-fixed', $order->id), [
            'quantity_delivered' => 2,
            'payment_action' => 'already_paid',
        ]);
        $respDone->assertRedirect();
        $respDone->assertSessionHas('success');

        $this->assertEquals(2, $item->fresh()->delivered_quantity);
        $this->assertEquals(Order::STATUS_DELIVERED, $order->fresh()->status);
        $this->assertEquals(1, OrderPayment::where('order_id', $order->id)->count(), 'No debe duplicar pagos.');
    }

    public function test_repartidor_collect_fixed_without_advance_and_charges_on_delivery(): void
    {
        $order = $this->createFixedOrder(2);
        $item = $order->items()->first();

        // 1. Recogida simple sin pago
        $this->actingAs($this->driver)->post(route('repartidor.orders.collect-fixed', $order->id), [
            'quantity' => 2,
        ])->assertRedirect();

        $this->assertEquals(0, OrderPayment::where('order_id', $order->id)->count());

        // 2. Recepción y Llenado en Planta
        $this->actingAs($this->operator)->post(route('planta.reception.confirm-fixed'), [
            'order_item_id' => $item->id,
            'received_quantity' => 2,
        ]);
        $this->actingAs($this->operator)->post(route('planta.fijos.mark-filled'), [
            'order_item_id' => $item->id,
            'quantity' => 2,
        ]);

        // 3. Carga en camión
        $this->actingAs($this->driver)->post(route('repartidor.ready-to-load.accept'), [
            'order_id' => $order->id,
            'quantity' => 2,
        ]);

        // 4. Entrega cobrando en efectivo (full_cash)
        $resp = $this->actingAs($this->driver)->post(route('repartidor.orders.deliver-fixed', $order->id), [
            'quantity_delivered' => 2,
            'payment_action' => 'full_cash',
            'cash_tendered' => 30.00,
        ]);
        $resp->assertRedirect();
        $resp->assertSessionHas('success');

        $this->assertEquals(Order::STATUS_DELIVERED, $order->fresh()->status);
        $payment = OrderPayment::where('order_id', $order->id)->first();
        $this->assertNotNull($payment);
        $this->assertEquals(30.00, (float)$payment->amount);
    }

    public function test_cancel_order_before_collecting_fijos(): void
    {
        $order = $this->createFixedOrder(2);

        $resp = $this->actingAs($this->driver)->post(route('repartidor.orders.cancel', $order->id), [
            'reason' => 'Cliente canceló en puerta por viaje imprevisto',
        ]);
        $resp->assertRedirect();
        $resp->assertSessionHas('success');

        $this->assertEquals(Order::STATUS_CANCELLED, $order->fresh()->status);

        // Ya no aparece en "Por recoger"
        $respList = $this->actingAs($this->driver)->get(route('repartidor.collections.index'));
        $respList->assertInertia(fn ($page) => $page->has('orders', 0));
    }

    public function test_planta_reception_with_shortage_and_incident(): void
    {
        $order = $this->createFixedOrder(2);
        $item = $order->items()->first();

        $this->actingAs($this->driver)->post(route('repartidor.orders.collect-fixed', $order->id), [
            'quantity' => 2,
        ]);

        // Planta recibe 1 apto, 1 roto (dañado)
        $resp = $this->actingAs($this->operator)->post(route('planta.reception.confirm-fixed'), [
            'order_item_id' => $item->id,
            'received_quantity' => 1,
            'damaged_quantity' => 1,
            'damage_type' => 'rotura',
            'damage_notes' => 'Envase quebrado en la base al desempacar del camión',
        ]);
        $resp->assertRedirect();

        $item->refresh();
        $this->assertEquals(1, $item->received_at_plant_quantity);
        $this->assertEquals(1, $item->damaged_quantity);

        // En "Por llenar" solo debe haber 1 apto pendiente
        $this->assertEquals(1, $item->received_at_plant_quantity - $item->prepared_quantity);
    }

    public function test_planta_partial_fill_and_ready_to_load(): void
    {
        $order = $this->createFixedOrder(2);
        $item = $order->items()->first();

        $this->actingAs($this->driver)->post(route('repartidor.orders.collect-fixed', $order->id), ['quantity' => 2]);
        $this->actingAs($this->operator)->post(route('planta.reception.confirm-fixed'), [
            'order_item_id' => $item->id,
            'received_quantity' => 2,
        ]);

        // Llenar parcialmente: 1 de 2
        $resp = $this->actingAs($this->operator)->post(route('planta.fijos.mark-filled'), [
            'order_item_id' => $item->id,
            'quantity' => 1,
        ]);
        $resp->assertRedirect();

        $item->refresh();
        $this->assertEquals(1, $item->prepared_quantity);
        // 1 sigue pendiente de llenar
        $this->assertEquals(1, $item->received_at_plant_quantity - $item->prepared_quantity);

        // En "Listos para cargar" aparece 1
        $respReady = $this->actingAs($this->driver)->get(route('repartidor.ready-to-load.index'));
        $respReady->assertInertia(fn ($page) =>
            $page->has('readyItems', 1)
                ->where('readyItems.0.ready_quantity', 1)
        );
    }

    public function test_partial_truck_load_with_difference(): void
    {
        $order = $this->createFixedOrder(2);
        $item = $order->items()->first();

        $this->actingAs($this->driver)->post(route('repartidor.orders.collect-fixed', $order->id), ['quantity' => 2]);
        $this->actingAs($this->operator)->post(route('planta.reception.confirm-fixed'), ['order_item_id' => $item->id, 'received_quantity' => 2]);
        $this->actingAs($this->operator)->post(route('planta.fijos.mark-filled'), ['order_item_id' => $item->id, 'quantity' => 2]);

        // Cargar 1 de 2 al camión
        $resp = $this->actingAs($this->driver)->post(route('repartidor.ready-to-load.accept'), [
            'order_id' => $order->id,
            'quantity' => 1,
            'difference_reason' => 'Espacio limitado en camión, el segundo se llevará en la siguiente vuelta',
        ]);
        $resp->assertRedirect();
        $resp->assertSessionHas('success');

        $item->refresh();
        $this->assertEquals(1, $item->loaded_quantity);
        // Queda 1 listo en Planta
        $this->assertEquals(1, $item->getReadyToLoadQuantity());

        // En "Por entregar" solo aparece 1
        $respDeliver = $this->actingAs($this->driver)->get(route('repartidor.deliveries.index'));
        $respDeliver->assertInertia(fn ($page) =>
            $page->has('deliveries', 1)
                ->where('deliveries.0.fixed_in_truck', 1)
        );
    }

    public function test_batch_accept_all_in_reception_and_load(): void
    {
        $order1 = $this->createFixedOrder(2);
        $order2 = $this->createFixedOrder(3);

        $this->actingAs($this->driver)->post(route('repartidor.orders.collect-fixed', $order1->id), ['quantity' => 2]);
        $this->actingAs($this->driver)->post(route('repartidor.orders.collect-fixed', $order2->id), ['quantity' => 3]);

        // 1. Aceptar todos en recepción masiva
        $respRecvAll = $this->actingAs($this->operator)->post(route('planta.reception.confirm-all-fixed'));
        $respRecvAll->assertRedirect();
        $respRecvAll->assertSessionHas('success');

        $this->assertEquals(2, $order1->items()->first()->received_at_plant_quantity);
        $this->assertEquals(3, $order2->items()->first()->received_at_plant_quantity);

        // 2. Marcar todos como llenados masivo
        $respFillAll = $this->actingAs($this->operator)->post(route('planta.fijos.mark-all-filled'));
        $respFillAll->assertRedirect();
        $respFillAll->assertSessionHas('success');

        $this->assertEquals(2, $order1->items()->first()->prepared_quantity);
        $this->assertEquals(3, $order2->items()->first()->prepared_quantity);

        // 3. Aceptar todos en carga masiva
        $respLoadAll = $this->actingAs($this->driver)->post(route('repartidor.ready-to-load.accept-all'));
        $respLoadAll->assertRedirect();
        $respLoadAll->assertSessionHas('success');

        $this->assertEquals(2, $order1->items()->first()->loaded_quantity);
        $this->assertEquals(3, $order2->items()->first()->loaded_quantity);
    }
}
