<?php

namespace Tests\Feature;

use App\Models\BottleCustodyMovement;
use App\Models\BottleIncident;
use App\Models\BottlePresentation;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PlantCounterRefill;
use App\Models\PlantInventory;
use App\Models\TruckInventory;
use App\Models\User;
use App\Services\OrderCustodyService;
use App\Services\PlantInventoryService;
use App\Services\SettingService;
use App\Services\TruckInventoryService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlantaOperatorModuleRedesignTest extends TestCase
{
    use RefreshDatabase;

    protected User $plantaUser;
    protected User $adminUser;
    protected User $repartidorUser;
    protected User $clientUser;
    protected Customer $customer;
    protected PlantInventoryService $plantService;
    protected TruckInventoryService $truckService;
    protected OrderCustodyService $custodyService;
    protected SettingService $settingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->plantService = app(PlantInventoryService::class);
        $this->truckService = app(TruckInventoryService::class);
        $this->custodyService = app(OrderCustodyService::class);
        $this->settingService = app(SettingService::class);

        // Operador Planta
        $this->plantaUser = User::factory()->create([
            'name' => 'Operador Principal Planta',
            'email' => 'operador@purificadora.com',
        ]);
        $this->plantaUser->assignRole('Operador Planta');

        // Repartidor
        $this->repartidorUser = User::factory()->create([
            'name' => 'Chofer Reparto',
            'email' => 'chofer@purificadora.com',
        ]);
        $this->repartidorUser->assignRole('Repartidor');

        // Administrador
        $this->adminUser = User::factory()->create([
            'name' => 'Admin Planta',
            'email' => 'admin@purificadora.com',
        ]);
        $this->adminUser->assignRole('Administrador');

        // Cliente
        $this->clientUser = User::factory()->create([
            'name' => 'Cliente Mostrador',
            'email' => 'cliente@purificadora.com',
        ]);
        $this->clientUser->assignRole('Cliente');

        $this->customer = Customer::create([
            'user_id' => $this->clientUser->id,
            'name' => 'Tienda La Esperanza',
            'nickname' => 'Doña Esperanza',
            'phone' => '9991234567',
            'bottle_type' => 'fijo',
            'fixed_bottles_count' => 5,
            'code' => 'CLI-0001',
        ]);

        CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'street' => 'Calle 10 #123',
            'neighborhood' => 'Centro',
            'zone' => 'Norte',
            'is_default' => true,
        ]);

        // Asegurar presentación de 20L
        $pres = BottlePresentation::getTwentyLiters();
        $pres->update([
            'store_price' => 12.50,
            'refill_price' => 16.00,
        ]);
    }

    /**
     * 1. Llenado rápido: Carga con el precio de Planta configurado y capacidad de 20L.
     */
    public function test_quick_refill_loads_with_correct_store_price_and_capacity_20l(): void
    {
        $response = $this->actingAs($this->plantaUser)->get(route('planta.quick-refill.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Planta/QuickRefill/Index')
            ->where('unitPrice', 12.50)
            ->where('capacity', 20)
            ->has('todaySummary')
            ->has('recentRefills')
            ->where('isOperator', true)
        );
    }

    /**
     * 2. Llenado rápido: Registra un servicio calculando el importe en el servidor,
     * guarda el registro inmutable, no crea cliente ni pedido de reparto, y no afecta
     * el inventario de cambios.
     */
    public function test_quick_refill_stores_with_server_side_calculation_and_isolation(): void
    {
        $ordersCountBefore = Order::count();
        $customersCountBefore = Customer::count();

        // Conteo inicial previo de cambios
        $this->plantService->recordInitialCount($this->plantaUser, 20, 30, 'Arranque');
        $plantInv = $this->plantService->getOrCreatePlantInventory();
        $emptyBefore = $plantInv->exchange_empty_available;
        $fullBefore = $plantInv->exchange_full_ready;

        // Registrar 3 llenados de mostrador
        $response = $this->actingAs($this->plantaUser)->post(route('planta.quick-refill.store'), [
            'quantity' => 3,
            'notes' => 'Atención rápida mostrador particular',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Comprobar persistencia inmutable de precio y cálculo en servidor
        $refill = PlantCounterRefill::latest('id')->first();
        $this->assertNotNull($refill);
        $this->assertEquals(3, $refill->quantity);
        $this->assertEquals(12.50, (float)$refill->unit_price);
        $this->assertEquals(37.50, (float)$refill->total_amount); // 3 * 12.50 = 37.50
        $this->assertEquals($this->plantaUser->id, $refill->operator_id);

        // Cambiar el precio de la sucursal para verificar inmutabilidad histórica
        $pres = BottlePresentation::getTwentyLiters();
        $pres->update(['store_price' => 20.00]);

        $this->assertEquals(37.50, (float)$refill->fresh()->total_amount);
        $this->assertEquals(12.50, (float)$refill->fresh()->unit_price);

        // Verificar que no se creó pedido de reparto ni cliente nuevo
        $this->assertEquals($ordersCountBefore, Order::count());
        $this->assertEquals($customersCountBefore, Customer::count());

        // Verificar que NO sumó los botellones del cliente al inventario de cambios genérico
        $plantInvFresh = $plantInv->fresh();
        $this->assertEquals($emptyBefore, $plantInvFresh->exchange_empty_available);
        $this->assertEquals($fullBefore, $plantInvFresh->exchange_full_ready);
    }

    /**
     * 3. Navegación unificada: Dashboard de Planta abre o redirige a Llenado Rápido,
     * y las vistas unificadas Fijos y Cambios responden 200 OK.
     */
    public function test_planta_unified_navigation_endpoints(): void
    {
        // Operador accede a dashboard (responde con Llenado Rápido)
        $this->actingAs($this->plantaUser)->get(route('planta.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Planta/QuickRefill/Index'));

        // Operador accede a Fijos
        $this->actingAs($this->plantaUser)->get(route('planta.fijos.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Planta/Fijos/Index')
                ->has('fixedPending')
                ->has('fixedPendingPrepare')
                ->has('fixedInWashing')
                ->has('fixedReadyToLoad')
            );

        // Operador accede a Cambios
        $this->actingAs($this->plantaUser)->get(route('planta.cambios.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Planta/Cambios/Index')
                ->has('balances')
                ->has('pendingExchangeReceptions')
                ->has('pendingExchangeProposals')
            );
    }

    /**
     * 4. Fijos: Recepción parcial de fijos (esperaban 3, llegaron 2, faltante con motivo).
     */
    public function test_fijos_partial_reception_records_shortage_with_reason(): void
    {
        // Crear pedido con 3 botellones fijos recogidos por el chofer y en tránsito a planta
        $order = Order::create([
            'folio' => 'PED-FIJO-01',
            'customer_id' => $this->customer->id,
            'recipient_name' => 'Doña Esperanza',
            'delivery_street' => 'Calle 10 #123',
            'delivery_zone' => 'Norte',
            'address_snapshot' => ['street' => 'Calle 10 #123'],
            'status' => Order::STATUS_IN_PROCESS,
            'total_amount' => 45.00,
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'presentation_id' => BottlePresentation::getTwentyLiters()->id,
            'type' => OrderItem::TYPE_FIXED,
            'quantity' => 3,
            'unit_price' => 15.00,
            'subtotal' => 45.00,
            'collected_quantity' => 3,
            'received_at_plant_quantity' => 0,
            'washing_quantity' => 0,
            'prepared_quantity' => 0,
            'loaded_quantity' => 0,
            'missing_quantity' => 0,
            'damaged_quantity' => 0,
            'status' => OrderItem::STATUS_TRANSPORTING_TO_PLANT,
        ]);

        // Operador confirma recepción física de solo 2 unidades con justificación del faltante
        $response = $this->actingAs($this->plantaUser)->post(route('planta.reception.confirm-fixed'), [
            'order_item_id' => $item->id,
            'received_quantity' => 2,
            'difference_reason' => 'El cliente no entregó el tercer botellón por estar fuera de la ciudad',
            'start_washing' => false,
            'damaged_quantity' => 0,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $itemFresh = $item->fresh();
        $this->assertEquals(2, $itemFresh->received_at_plant_quantity);
        $this->assertEquals(2, $itemFresh->getPendingToWashQuantity());
        $this->assertEquals(1, $itemFresh->collected_quantity - $itemFresh->received_at_plant_quantity);

        // La discrepancia queda registrada en el movimiento de custodia
        $this->assertDatabaseHas('bottle_custody_movements', [
            'order_id' => $order->id,
            'movement_type' => BottleCustodyMovement::TYPE_PLANT_RECEPTION_FIXED,
            'quantity' => 2,
            'status' => 'accepted_with_discrepancy',
            'difference_reason' => 'El cliente no entregó el tercer botellón por estar fuera de la ciudad',
        ]);
    }

    /**
     * 5. Fijos: Lavado y finalización parcial (inicia 3, finaliza 2 marcados, 1 sigue en proceso).
     */
    public function test_fijos_washing_and_finish_marking_advances_only_selected_units(): void
    {
        $order = Order::create([
            'folio' => 'PED-FIJO-02',
            'customer_id' => $this->customer->id,
            'recipient_name' => 'Doña Esperanza',
            'delivery_street' => 'Calle 10 #123',
            'delivery_zone' => 'Norte',
            'address_snapshot' => ['street' => 'Calle 10 #123'],
            'status' => Order::STATUS_IN_PROCESS,
            'total_amount' => 45.00,
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'presentation_id' => BottlePresentation::getTwentyLiters()->id,
            'type' => OrderItem::TYPE_FIXED,
            'quantity' => 3,
            'unit_price' => 15.00,
            'subtotal' => 45.00,
            'collected_quantity' => 3,
            'received_at_plant_quantity' => 3,
            'washing_quantity' => 0,
            'prepared_quantity' => 0,
            'loaded_quantity' => 0,
            'status' => OrderItem::STATUS_TRANSPORTING_TO_PLANT,
        ]);

        // Iniciar lavado de los 3
        $this->actingAs($this->plantaUser)->post(route('planta.production.fixed.start'), [
            'order_item_id' => $item->id,
            'quantity' => 3,
        ])->assertRedirect();

        $this->assertEquals(3, $item->fresh()->getInWashingQuantity());

        // Finalizar únicamente 2 marcados
        $this->actingAs($this->plantaUser)->post(route('planta.production.fixed.finish'), [
            'order_item_id' => $item->id,
            'quantity' => 2,
        ])->assertRedirect();

        $itemFresh = $item->fresh();
        // 2 preparados listos para enviar
        $this->assertEquals(2, $itemFresh->prepared_quantity);
        $this->assertEquals(2, $itemFresh->getReadyToLoadQuantity());
        // 1 sigue en proceso de lavado
        $this->assertEquals(1, $itemFresh->getInWashingQuantity());
    }

    /**
     * 6. Fijos: Propuesta de carga queda 'proposed' hasta que el Repartidor la acepta en Mi Carga.
     */
    public function test_fijos_load_proposal_stays_proposed_until_driver_accepts_in_mi_carga(): void
    {
        $order = Order::create([
            'folio' => 'PED-FIJO-03',
            'customer_id' => $this->customer->id,
            'recipient_name' => 'Doña Esperanza',
            'delivery_street' => 'Calle 10 #123',
            'delivery_zone' => 'Norte',
            'address_snapshot' => ['street' => 'Calle 10 #123'],
            'status' => Order::STATUS_IN_PROCESS,
            'total_amount' => 30.00,
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'presentation_id' => BottlePresentation::getTwentyLiters()->id,
            'type' => OrderItem::TYPE_FIXED,
            'quantity' => 2,
            'unit_price' => 15.00,
            'subtotal' => 30.00,
            'collected_quantity' => 2,
            'received_at_plant_quantity' => 2,
            'washing_quantity' => 2,
            'prepared_quantity' => 2,
            'loaded_quantity' => 0,
            'status' => OrderItem::STATUS_TRANSPORTING_TO_PLANT,
        ]);

        // Operador propone enviar los 2 fijos al chofer
        $this->actingAs($this->plantaUser)->post(route('planta.loads.store'), [
            'bottle_type' => 'fijo',
            'order_item_id' => $item->id,
            'quantity' => 2,
        ])->assertRedirect();

        // Verificar que la transferencia queda en estado 'proposed'
        $movement = BottleCustodyMovement::where('order_item_id', $item->id)
            ->where('movement_type', BottleCustodyMovement::TYPE_TRUCK_LOAD_FIXED)
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals(BottleCustodyMovement::STATUS_PROPOSED, $movement->status);
        $this->assertEquals(2, $item->fresh()->reserved_quantity);

        // Repartidor acepta la carga en Mi Carga
        $this->actingAs($this->repartidorUser)->post(route('repartidor.truck-load.accept', $movement->id), [
            'confirmed_quantity' => 2,
        ])->assertRedirect();

        // Tras aceptación del repartidor, queda aceptado y cargado
        $this->assertEquals(BottleCustodyMovement::STATUS_ACCEPTED, $movement->fresh()->status);
        $this->assertEquals(2, $item->fresh()->loaded_quantity);
        $this->assertEquals(0, $item->fresh()->reserved_quantity);
    }

    /**
     * 7. Cambios: Visita de camión en la que Planta recibe 4 vacíos y envía 5 llenos.
     */
    public function test_cambios_visit_receives_4_empties_and_sends_5_fulls(): void
    {
        // Conteo inicial
        $this->plantService->recordInitialCount($this->plantaUser, 10, 15, 'Existencias iniciales');

        // Camión del chofer con 4 vacíos recogidos
        $truckInv = TruckInventory::firstOrCreate(
            ['driver_user_id' => $this->repartidorUser->id],
            [
                'exchange_full_available' => 0,
                'exchange_full_initial' => 0,
                'exchange_full_delivered' => 0,
                'exchange_empty_collected' => 4,
            ]
        );
        $truckInv->update(['exchange_empty_collected' => 4]);

        // Chofer declara entrega de 4 vacíos a planta
        $unloadMovement = $this->truckService->proposeTruckToPlantUnload(
            $this->repartidorUser,
            OrderItem::TYPE_EXCHANGE,
            BottleCustodyMovement::CONDITION_EMPTY,
            4,
            null,
            'Entrega de vacíos de ruta'
        );
        $this->assertEquals(BottleCustodyMovement::STATUS_PROPOSED, $unloadMovement->status);

        // 1. Planta recibe los 4 vacíos físicamente
        $responseRecv = $this->actingAs($this->plantaUser)->post(route('planta.reception.confirm-exchange'), [
            'movement_id' => $unloadMovement->id,
            'received_quantity' => 4,
        ]);
        $responseRecv->assertRedirect();

        // Vacíos de planta suben de 10 a 14, vacíos de camión bajan de 4 a 0
        $this->assertEquals(14, $this->plantService->getOrCreatePlantInventory()->fresh()->exchange_empty_available);
        $this->assertEquals(0, $truckInv->fresh()->exchange_empty_collected);

        // 2. Planta envía 5 llenos al camión
        $responseSend = $this->actingAs($this->plantaUser)->post(route('planta.loads.store'), [
            'bottle_type' => 'cambio',
            'quantity' => 5,
            'notes' => 'Carga de 5 cambios llenos para segundo turno',
        ]);
        $responseSend->assertRedirect();

        // Reserva en planta 5 botellones
        $plantInv = $this->plantService->getOrCreatePlantInventory()->fresh();
        $this->assertEquals(5, $plantInv->exchange_full_reserved);

        $loadMovement = BottleCustodyMovement::where('movement_type', BottleCustodyMovement::TYPE_TRUCK_LOAD_EXCHANGE)
            ->where('status', BottleCustodyMovement::STATUS_PROPOSED)
            ->first();
        $this->assertNotNull($loadMovement);
        $this->assertEquals(5, $loadMovement->quantity);

        // 3. Repartidor confirma lo recibido en Mi Carga
        $this->actingAs($this->repartidorUser)->post(route('repartidor.truck-load.accept', $loadMovement->id), [
            'confirmed_quantity' => 5,
        ])->assertRedirect();

        // Camión ahora tiene 5 llenos disponibles y planta descontó los 5 de almacén y reserva
        $this->assertEquals(5, $truckInv->fresh()->exchange_full_available);
        $plantInvFresh = $this->plantService->getOrCreatePlantInventory()->fresh();
        $this->assertEquals(10, $plantInvFresh->exchange_full_available); // 15 - 5 = 10
        $this->assertEquals(0, $plantInvFresh->exchange_full_reserved);
    }
}
