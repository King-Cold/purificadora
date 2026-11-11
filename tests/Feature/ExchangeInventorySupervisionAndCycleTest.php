<?php

namespace Tests\Feature;

use App\Models\BottleCustodyMovement;
use App\Models\BottleIncident;
use App\Models\BottlePresentation;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PlantInventory;
use App\Models\PlantCounterRefill;
use App\Models\TruckInventory;
use App\Models\User;
use App\Services\PlantInventoryService;
use App\Services\SettingService;
use App\Services\TruckInventoryService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExchangeInventorySupervisionAndCycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $plantaUser;
    protected User $repartidorUser;
    protected PlantInventoryService $plantService;
    protected TruckInventoryService $truckService;
    protected SettingService $settingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->adminUser = User::factory()->create([
            'name' => 'Admin Puritronic',
            'email' => 'admin.cambios@puritronic.local',
            'status' => 'active',
        ]);
        $this->adminUser->assignRole('Administrador');

        $this->plantaUser = User::factory()->create([
            'name' => 'Operador Planta',
            'email' => 'planta.cambios@puritronic.local',
            'status' => 'active',
        ]);
        $this->plantaUser->assignRole('Operador Planta');

        $this->repartidorUser = User::factory()->create([
            'name' => 'Repartidor Ruta',
            'email' => 'repartidor.cambios@puritronic.local',
            'status' => 'active',
        ]);
        $this->repartidorUser->assignRole('Repartidor');

        BottlePresentation::getTwentyLiters()->update([
            'name' => 'Botellón 20L',
            'is_active' => true,
            'store_price' => 15.00,
            'refill_price' => 35.00,
            'base_price' => 150.00,
        ]);

        $this->plantService = app(PlantInventoryService::class);
        $this->truckService = app(TruckInventoryService::class);
        $this->settingService = app(SettingService::class);
    }

    /**
     * 1. Con una meta configurada de 40 y 40 activos:
     * - Reportar 1 roto deja 39 activos, 1 dañado en revisión y Falta 1 para la meta.
     * - Confirmar la baja deja 39 activos y 1 baja en el historial, sin doble descuento.
     * - Registrar el alta de 1 botellón nuevo devuelve el sistema a 40 activos.
     * - Impide resolver dos veces la misma incidencia.
     * - Editar la meta no altera los saldos activos.
     */
    public function test_target_40_and_40_active_breakage_writeoff_and_replacement_cycle(): void
    {
        // Meta 40 y conteo inicial en Planta: 15 vacíos + 25 llenos = 40 activos
        $this->settingService->setExchangeBottlesTarget(40, $this->adminUser->name);
        $this->plantService->recordInitialCount($this->plantaUser, 15, 25, 'Arranque con 40 botellones de cambio');

        $state0 = $this->plantService->getExchangeSupervisionState();
        $this->assertSame(40, $state0['summary']['target_quantity']);
        $this->assertSame(40, $state0['summary']['active_total']);
        $this->assertSame(0, $state0['summary']['in_review_total']);
        $this->assertSame('En la meta', $state0['summary']['diff_label']);

        // Paso 1: El Operador reporta 1 botellón roto durante lavado/recepción en Planta
        $resReport = $this->actingAs($this->plantaUser)->post(route('planta.cambios.report-inconvenience'), [
            'quantity' => 1,
            'origin_stage' => 'plant_empty',
            'reason' => 'rotura',
            'location_status' => 'separado',
            'notes' => 'Botellón roto durante lavado en Planta',
        ]);
        $resReport->assertRedirect();
        $resReport->assertSessionHasNoErrors();

        // Paso 2: El sistema muestra 39 activos · 1 dañado en revisión · Falta 1 para la meta
        $state1 = $this->plantService->getExchangeSupervisionState();
        $this->assertSame(39, $state1['summary']['active_total']);
        $this->assertSame(1, $state1['summary']['damaged_in_review']);
        $this->assertSame(1, $state1['summary']['in_review_total']);
        $this->assertSame(-1, $state1['summary']['diff_from_target']);
        $this->assertSame('Falta 1 para la meta', $state1['summary']['diff_label']);

        $incidentId = $state1['pending_incidents'][0]['id'];
        $incident = BottleIncident::findOrFail($incidentId);

        // Paso 3: El Administrador revisa el caso y confirma la baja:
        // quedan 39 activos y 1 baja en el historial, sin volver a descontarlo
        $resWriteOff = $this->actingAs($this->adminUser)->post(
            route('admin.exchange-inventory.incidents.resolve', $incident->id),
            [
                'resolution_type' => 'baja',
                'notes' => 'Rotura total en fondo verificada por administración',
            ]
        );
        $resWriteOff->assertRedirect();
        $resWriteOff->assertSessionHasNoErrors();

        $state2 = $this->plantService->getExchangeSupervisionState();
        $this->assertSame(39, $state2['summary']['active_total']);
        $this->assertSame(0, $state2['summary']['damaged_in_review']);
        $this->assertSame(0, $state2['summary']['in_review_total']);
        $this->assertSame(1, $state2['breakdown']['history_totals']['write_offs']);
        $this->assertSame('Falta 1 para la meta', $state2['summary']['diff_label']);

        // Intentar resolver la misma incidencia por segunda vez debe rechazarse
        $resDouble = $this->actingAs($this->adminUser)->post(
            route('admin.exchange-inventory.incidents.resolve', $incident->id),
            [
                'resolution_type' => 'baja',
                'notes' => 'Intento duplicado',
            ]
        );
        $resDouble->assertSessionHasErrors('incident');
        $this->assertSame(39, $this->plantService->getActiveExchangeCount());

        // Paso 4: Cuando entra físicamente un botellón de reposición, el Administrador registra el alta
        // y el sistema vuelve a 40 activos
        $resNew = $this->actingAs($this->adminUser)->post(
            route('admin.exchange-inventory.new-bottles.store'),
            [
                'quantity' => 1,
                'bottle_condition' => 'empty',
                'reason' => 'Reposición de envase',
                'notes' => 'Botellón nuevo de 20L para reponer baja #' . $incident->id,
            ]
        );
        $resNew->assertRedirect();
        $resNew->assertSessionHasNoErrors();

        $state3 = $this->plantService->getExchangeSupervisionState();
        $this->assertSame(40, $state3['summary']['active_total']);
        $this->assertSame(15, $state3['breakdown']['plant']['empty_available']); // 15 - 1 + 1 = 15
        $this->assertSame(25, $state3['breakdown']['plant']['full_available']);
        $this->assertSame(1, $state3['breakdown']['history_totals']['new_entries']);
        $this->assertSame(1, $state3['breakdown']['history_totals']['write_offs']);
        $this->assertSame('En la meta', $state3['summary']['diff_label']);
    }

    /**
     * 1b. Recuperación de botellón en revisión y edición de meta sin alterar saldos activos.
     */
    public function test_admin_can_recover_reviewed_bottle_and_edit_target_without_altering_active_stock(): void
    {
        $this->settingService->setExchangeBottlesTarget(40, $this->adminUser->name);
        $this->plantService->recordInitialCount($this->plantaUser, 20, 20);

        // Reportar 2 sin localizar desde llenos en Planta
        $this->actingAs($this->plantaUser)->post(route('planta.cambios.report-inconvenience'), [
            'quantity' => 2,
            'origin_stage' => 'plant_full',
            'reason' => 'otro',
            'location_status' => 'sin_localizar',
            'notes' => '2 botellones llenos no ubicados en tarima principal',
        ])->assertSessionHasNoErrors();

        $stateAfterUnlocated = $this->plantService->getExchangeSupervisionState();
        $this->assertSame(38, $stateAfterUnlocated['summary']['active_total']);
        $this->assertSame(2, $stateAfterUnlocated['summary']['unlocated_in_review']);
        $this->assertSame('Faltan 2 para la meta', $stateAfterUnlocated['summary']['diff_label']);

        // Recuperar los 2 botellones hacia llenos libres en Planta
        $this->actingAs($this->adminUser)->post(route('admin.exchange-inventory.unlocated.resolve'), [
            'quantity' => 2,
            'resolution_type' => 'recuperacion',
            'recovery_location' => 'plant_full',
            'notes' => 'Encontrados en tarima secundaria de almacén',
        ])->assertSessionHasNoErrors();

        $stateAfterRecovery = $this->plantService->getExchangeSupervisionState();
        $this->assertSame(40, $stateAfterRecovery['summary']['active_total']);
        $this->assertSame(0, $stateAfterRecovery['summary']['unlocated_in_review']);
        $this->assertSame(20, $stateAfterRecovery['breakdown']['plant']['full_available']);

        // Editar meta a 38 -> muestra "2 por encima de la meta" sin alterar los 40 activos
        $this->actingAs($this->adminUser)->put(route('admin.exchange-inventory.target.update'), [
            'target_quantity' => 38,
            'notes' => 'Ajuste de meta operativa',
        ])->assertSessionHasNoErrors();

        $stateTarget38 = $this->plantService->getExchangeSupervisionState();
        $this->assertSame(38, $stateTarget38['summary']['target_quantity']);
        $this->assertSame(40, $stateTarget38['summary']['active_total']);
        $this->assertSame(2, $stateTarget38['summary']['diff_from_target']);
        $this->assertSame('2 por encima de la meta', $stateTarget38['summary']['diff_label']);
    }

    /**
     * 2. En una visita a Planta:
     * - El camión entrega 14 vacíos y recibe una propuesta de 20 llenos.
     * - Los saldos de Planta y camión se actualizan por separado sin obligar igualdad.
     * - Conserva el total de cambios activos (40) en todo momento.
     */
    public function test_plant_visit_allows_14_empties_received_and_20_full_sent_independently(): void
    {
        // Meta 40 y 40 activos distribuidos:
        // Planta: 2 vacíos + 22 llenos libres = 24
        // Camión: 2 llenos disponibles + 14 vacíos recogidos = 16
        // Total = 40 activos
        $this->settingService->setExchangeBottlesTarget(40, $this->adminUser->name);
        $this->plantService->recordInitialCount($this->plantaUser, 2, 22);

        $truckInv = $this->truckService->getOrCreateTruckInventory($this->repartidorUser);
        $truckInv->update([
            'exchange_full_available' => 2,
            'exchange_empty_collected' => 14,
        ]);

        $this->assertSame(40, $this->plantService->getActiveExchangeCount());

        // 1. Repartidor declara descarga de 14 vacíos en Planta
        $this->actingAs($this->repartidorUser)->post(route('repartidor.truck-load.propose-unload'), [
            'bottle_type' => 'cambio',
            'bottle_condition' => 'empty',
            'quantity' => 14,
            'notes' => 'Entrega de 14 vacíos recogidos en la mañana',
        ])->assertSessionHasNoErrors();

        // Mientras la descarga está pendiente de confirmar, los 14 vacíos siguen en el camión (comprometidos)
        // y NO se pueden volver a proponer
        $this->actingAs($this->repartidorUser)->post(route('repartidor.truck-load.propose-unload'), [
            'bottle_type' => 'cambio',
            'bottle_condition' => 'empty',
            'quantity' => 1,
        ])->assertSessionHasErrors('unload');

        $unloadMovement = BottleCustodyMovement::where('movement_type', BottleCustodyMovement::TYPE_TRUCK_UNLOAD_EXCHANGE)
            ->where('status', BottleCustodyMovement::STATUS_PROPOSED)
            ->firstOrFail();

        // 2. Operador de Planta confirma la recepción física de los 14 vacíos
        $this->actingAs($this->plantaUser)->post(route('planta.reception.confirm-exchange'), [
            'movement_id' => $unloadMovement->id,
            'received_quantity' => 14,
        ])->assertSessionHasNoErrors();

        // Ahora Planta tiene 2 + 14 = 16 vacíos aptos, y el camión tiene 0 vacíos recogidos
        $this->assertSame(16, PlantInventory::first()->exchange_empty_available);
        $this->assertSame(0, $truckInv->fresh()->exchange_empty_collected);
        $this->assertSame(40, $this->plantService->getActiveExchangeCount());

        // 3. En la misma visita, el Operador de Planta envía 20 llenos al camión (movimiento independiente)
        $this->actingAs($this->plantaUser)->post(route('planta.loads.store'), [
            'bottle_type' => 'cambio',
            'quantity' => 20,
            'notes' => 'Envío de 20 cambios llenos para segunda salida',
        ])->assertSessionHasNoErrors();

        // Los 20 llenos quedan reservados en Planta hasta que el Repartidor confirme:
        // Planta llenos libres: 22 - 20 = 2; reservados: 20; camión llenos: 2 -> Total activos = 40
        $supMid = $this->plantService->getExchangeSupervisionState();
        $this->assertSame(2, $supMid['breakdown']['plant']['full_available']);
        $this->assertSame(20, $supMid['breakdown']['plant']['full_reserved']);
        $this->assertSame(2, $supMid['breakdown']['truck']['full_available']);
        $this->assertSame(40, $supMid['summary']['active_total']);

        $loadMovement = BottleCustodyMovement::where('movement_type', BottleCustodyMovement::TYPE_TRUCK_LOAD_EXCHANGE)
            ->where('status', BottleCustodyMovement::STATUS_PROPOSED)
            ->firstOrFail();

        // 4. El Repartidor acepta los 20 llenos en Mi carga
        $this->actingAs($this->repartidorUser)->post(
            route('repartidor.truck-load.accept', $loadMovement->id),
            ['confirmed_quantity' => 20]
        )->assertSessionHasNoErrors();

        // Saldos finales tras la visita:
        // Planta: 16 vacíos aptos, 2 llenos libres, 0 reservados
        // Camión: 22 llenos disponibles (2 + 20), 0 vacíos
        // Total cambios activos = 16 + 2 + 22 = 40
        $supAfter = $this->plantService->getExchangeSupervisionState();
        $this->assertSame(16, $supAfter['breakdown']['plant']['empty_available']);
        $this->assertSame(2, $supAfter['breakdown']['plant']['full_available']);
        $this->assertSame(0, $supAfter['breakdown']['plant']['full_reserved']);
        $this->assertSame(22, $supAfter['breakdown']['truck']['full_available']);
        $this->assertSame(0, $supAfter['breakdown']['truck']['empty_collected']);
        $this->assertSame(40, $supAfter['summary']['active_total']);
    }

    /**
     * 3. Propuesta de 20 llenos aceptada parcialmente por 18:
     * - Suma 18 al camión y registra las otras 2 según su situación física (1 retenido en Planta, 1 dañado en revisión).
     */
    public function test_partial_load_acceptance_18_of_20_reconciles_remaining_2_units(): void
    {
        $this->settingService->setExchangeBottlesTarget(40, $this->adminUser->name);
        $this->plantService->recordInitialCount($this->plantaUser, 10, 30);

        // Planta propone 20 llenos al camión (quedan 10 llenos libres y 20 reservados en Planta)
        $this->actingAs($this->plantaUser)->post(route('planta.loads.store'), [
            'bottle_type' => 'cambio',
            'quantity' => 20,
        ])->assertSessionHasNoErrors();

        $movement = BottleCustodyMovement::where('movement_type', BottleCustodyMovement::TYPE_TRUCK_LOAD_EXCHANGE)
            ->where('status', BottleCustodyMovement::STATUS_PROPOSED)
            ->firstOrFail();

        // El Repartidor acepta 18 en su camión: 1 quedó apto retenido en Planta y 1 se detectó dañado en carga
        $this->actingAs($this->repartidorUser)->post(
            route('repartidor.truck-load.accept', $movement->id),
            [
                'confirmed_quantity' => 18,
                'retained_in_plant_quantity' => 1,
                'damaged_quantity' => 1,
                'damage_type' => 'fuga_cuerpo',
                'damage_notes' => '1 garrafón presentó fisura al subir a plataforma',
                'unlocated_quantity' => 0,
                'difference_reason' => '1 retenido en planta y 1 fisurado en carga',
            ]
        )->assertSessionHasNoErrors();

        $state = $this->plantService->getExchangeSupervisionState();
        // Camión recibió 18 llenos
        $this->assertSame(18, $state['breakdown']['truck']['full_available']);
        // Planta liberó 1 retenido apto -> 10 + 1 = 11 llenos libres, 0 reservados
        $this->assertSame(11, $state['breakdown']['plant']['full_available']);
        $this->assertSame(0, $state['breakdown']['plant']['full_reserved']);
        // 1 dañado pasó a En revisión
        $this->assertSame(1, $state['summary']['damaged_in_review']);
        // Activos: 10 vacíos Planta + 11 llenos Planta + 18 llenos Camión = 39 activos (+ 1 en revisión = 40)
        $this->assertSame(39, $state['summary']['active_total']);
        $this->assertSame('Falta 1 para la meta', $state['summary']['diff_label']);
    }

    /**
     * 4. Entrega de cambio a domicilio:
     * - Baja 1 lleno del camión, sube 1 vacío recogido al camión y conserva el total de cambios activos.
     */
    public function test_home_exchange_delivery_decrements_truck_full_increments_truck_empty_and_preserves_active_total(): void
    {
        $this->settingService->setExchangeBottlesTarget(40, $this->adminUser->name);
        $this->plantService->recordInitialCount($this->plantaUser, 10, 20);

        $truckInv = $this->truckService->getOrCreateTruckInventory($this->repartidorUser);
        $truckInv->update([
            'exchange_full_available' => 10,
            'exchange_empty_collected' => 0,
        ]);

        // Total activos inicial: 10 + 20 + 10 = 40
        $this->assertSame(40, $this->plantService->getActiveExchangeCount());

        $customer = Customer::create([
            'code' => 'CLI-9001',
            'name' => 'Doña Carmen',
            'nickname' => 'Carmen Centro',
            'fixed_bottles_count' => 0,
            'exchange_bottles_count' => 1,
        ]);

        // Atender pedido al paso de 1 botellón de cambio a domicilio
        $this->actingAs($this->repartidorUser)->post(route('repartidor.direct-service.store'), [
            'customer_id' => $customer->id,
            'service_mode' => 'cambio',
            'exchange_quantity' => 1,
            'payment_mode' => 'completo',
            'cash_tendered' => 35,
        ])->assertSessionHasNoErrors();

        $stateAfter = $this->plantService->getExchangeSupervisionState();
        $this->assertSame(9, $stateAfter['breakdown']['truck']['full_available']);
        $this->assertSame(1, $stateAfter['breakdown']['truck']['empty_collected']);
        $this->assertSame(40, $stateAfter['summary']['active_total']);
    }

    /**
     * 5. Flujo completo de fijos:
     * - Pedido -> recolección con ajuste de cantidad -> recepción en Planta -> preparación -> carga -> entrega y cobro,
     *   sin alterar el total de botellones de cambio.
     */
    public function test_complete_fixed_bottles_flow_with_quantity_adjustment_does_not_alter_exchange_inventory(): void
    {
        $this->settingService->setExchangeBottlesTarget(40, $this->adminUser->name);
        $this->plantService->recordInitialCount($this->plantaUser, 15, 15);
        $truckInv = $this->truckService->getOrCreateTruckInventory($this->repartidorUser);
        $truckInv->update([
            'exchange_full_available' => 10,
            'exchange_empty_collected' => 0,
        ]);

        $this->assertSame(40, $this->plantService->getActiveExchangeCount());

        // Cliente con 4 botellones fijos registrados pide recolección de 3 fijos
        $customer = Customer::create([
            'code' => 'CLI-9002',
            'name' => 'Jorge Méndez',
            'nickname' => 'Don Jorge Norte',
            'fixed_bottles_count' => 4,
            'exchange_bottles_count' => 0,
        ]);

        $order = Order::create([
            'folio' => 'ORD-FIJ-001',
            'customer_id' => $customer->id,
            'recipient_name' => 'Jorge Méndez',
            'address_snapshot' => 'Calle 10 #20, Norte',
            'status' => Order::STATUS_CONFIRMED,
            'total_bottles' => 3,
            'fixed_quantity' => 3,
            'exchange_quantity' => 0,
            'total_amount' => 105.00,
        ]);

        $fixedItem = OrderItem::create([
            'order_id' => $order->id,
            'type' => OrderItem::TYPE_FIXED,
            'presentation' => '20L',
            'quantity' => 3,
            'requested_quantity' => 3,
            'unit_price' => 35.00,
            'subtotal' => 105.00,
            'status' => OrderItem::STATUS_PENDING,
        ]);

        // 1. Repartidor recoge fijos con ajuste de cantidad: el cliente solo entrega 2 de los 3 solicitados
        $this->actingAs($this->repartidorUser)->post(
            route('repartidor.orders.collect-fixed', $order->id),
            [
                'collected_quantity' => 2,
                'collection_notes' => 'El cliente solo tenía 2 vacíos listos hoy',
            ]
        )->assertSessionHasNoErrors();

        $fixedItem->refresh();
        $this->assertSame(3, (int) $fixedItem->quantity);
        $this->assertSame(2, (int) $fixedItem->collected_quantity);
        $this->assertSame(40, $this->plantService->getActiveExchangeCount());

        // 2. Operador de Planta recibe los 2 fijos del cliente e inicia lavado
        $this->actingAs($this->plantaUser)->post(route('planta.reception.confirm-fixed'), [
            'order_item_id' => $fixedItem->id,
            'received_quantity' => 2,
            'start_washing' => true,
        ])->assertSessionHasNoErrors();

        // 3. Operador de Planta finaliza el llenado de los 2 fijos
        $this->actingAs($this->plantaUser)->post(route('planta.production.fixed.finish'), [
            'order_item_id' => $fixedItem->id,
            'quantity' => 2,
        ])->assertSessionHasNoErrors();

        // 4. Operador de Planta propone la carga de los 2 fijos preparados al camión
        $this->actingAs($this->plantaUser)->post(route('planta.loads.store'), [
            'bottle_type' => 'fijo',
            'order_item_id' => $fixedItem->id,
            'quantity' => 2,
        ])->assertSessionHasNoErrors();

        $fixedLoadMov = BottleCustodyMovement::where('order_item_id', $fixedItem->id)
            ->where('movement_type', BottleCustodyMovement::TYPE_TRUCK_LOAD_FIXED)
            ->where('status', BottleCustodyMovement::STATUS_PROPOSED)
            ->firstOrFail();

        // 5. Repartidor acepta los 2 fijos en Mi carga
        $this->actingAs($this->repartidorUser)->post(
            route('repartidor.truck-load.accept', $fixedLoadMov->id),
            ['confirmed_quantity' => 2]
        )->assertSessionHasNoErrors();

        // 6. Repartidor abre atención del pedido y entrega los 2 fijos llenos al cliente cobrando completo
        $this->actingAs($this->repartidorUser)->post(
            route('repartidor.orders.serve', $order->id)
        )->assertSessionHasNoErrors();

        $stop = \App\Models\RouteStop::where('order_id', $order->id)->firstOrFail();

        $this->actingAs($this->repartidorUser)->post(
            route('repartidor.stops.process-serve', $stop->id),
            [
                'fixed_delivered' => 2,
                'exchange_delivered' => 0,
                'exchange_empty_received' => 0,
                'units_paid_cash' => 2,
                'cash_tendered' => 70.00,
            ]
        )->assertSessionHasNoErrors();

        $this->assertSame(Order::STATUS_DELIVERED, $order->fresh()->status);
        // El inventario de cambios permanece intacto en 40 activos
        $this->assertSame(40, $this->plantService->getActiveExchangeCount());
    }

    /**
     * 6. Pedido mixto, pedido al paso y alta de cliente sin cuenta (apodo obligatorio y nombre opcional).
     */
    public function test_walk_in_customer_and_mixed_direct_order_process_each_bottle_lane_correctly(): void
    {
        $this->settingService->setExchangeBottlesTarget(40, $this->adminUser->name);
        $this->plantService->recordInitialCount($this->plantaUser, 10, 20);
        $truckInv = $this->truckService->getOrCreateTruckInventory($this->repartidorUser);
        $truckInv->update([
            'exchange_full_available' => 10,
            'exchange_empty_collected' => 0,
        ]);

        // Alta de cliente sin cuenta: apodo obligatorio, nombre opcional vacío
        $this->actingAs($this->repartidorUser)->post(route('repartidor.customers.store-quick'), [
            'nickname' => 'Tienda La Esquina',
            'name' => '',
        ])->assertSessionHasNoErrors();

        $customer = Customer::where('nickname', 'Tienda La Esquina')->firstOrFail();
        $customer->update([
            'fixed_bottles_count' => 2,
            'exchange_bottles_count' => 1,
        ]);

        // Pedido al paso Mixto: recoge 2 fijos vacíos y entrega 1 cambio lleno (recogiendo 1 vacío de cambio)
        $this->actingAs($this->repartidorUser)->post(route('repartidor.direct-service.store'), [
            'customer_id' => $customer->id,
            'service_mode' => 'mixto',
            'fixed_quantity' => 2,
            'exchange_quantity' => 1,
            'units_paid_cash' => 1,
            'cash_tendered' => 35.00,
        ])->assertSessionHasNoErrors();

        $sup = $this->plantService->getExchangeSupervisionState();
        // El cambio entregó 1 lleno (10 -> 9) y recogió 1 vacío (0 -> 1); total activos de cambio sigue en 40
        $this->assertSame(9, $sup['breakdown']['truck']['full_available']);
        $this->assertSame(1, $sup['breakdown']['truck']['empty_collected']);
        $this->assertSame(40, $sup['summary']['active_total']);

        // Y los 2 fijos quedaron en custodia del camión para llevar a Planta
        $truckState = $this->truckService->getTruckInventoryState($this->repartidorUser);
        $this->assertSame(2, $truckState['truck_inventory']['fixed_empty_collected_total']);
    }

    /**
     * 7. Llenado rápido en Planta:
     * - Cobra al precio de Planta ($15) y queda registrado por separado del inventario de cambios.
     */
    public function test_plant_quick_refill_charges_store_price_and_does_not_alter_exchange_inventory(): void
    {
        $this->settingService->setExchangeBottlesTarget(40, $this->adminUser->name);
        $this->plantService->recordInitialCount($this->plantaUser, 15, 25);

        $this->assertSame(40, $this->plantService->getActiveExchangeCount());

        // Registrar llenado rápido de mostrador por 3 botellones de 20L
        $this->actingAs($this->plantaUser)->post(route('planta.quick-refill.store'), [
            'quantity' => 3,
        ])->assertSessionHasNoErrors();

        $refill = PlantCounterRefill::latest('id')->firstOrFail();
        $this->assertSame(3, (int) $refill->quantity);
        $this->assertEquals(15.00, (float) $refill->unit_price);
        $this->assertEquals(45.00, (float) $refill->total_amount);

        // El inventario de cambios sigue exactamente en 40 activos (15 vacíos y 25 llenos)
        $state = $this->plantService->getExchangeSupervisionState();
        $this->assertSame(40, $state['summary']['active_total']);
        $this->assertSame(15, $state['breakdown']['plant']['empty_available']);
        $this->assertSame(25, $state['breakdown']['plant']['full_available']);
    }
}
