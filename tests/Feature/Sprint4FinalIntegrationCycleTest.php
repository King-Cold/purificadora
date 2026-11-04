<?php

namespace Tests\Feature;

use App\Exceptions\OrderBusinessRuleException;
use App\Models\BottleCustodyMovement;
use App\Models\BottleIncident;
use App\Models\BottlePresentation;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\DeliveryRoute;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PlantInventory;
use App\Models\RouteStop;
use App\Models\TruckInventory;
use App\Models\User;
use App\Services\DeliveryRouteService;
use App\Services\OrderCustodyService;
use App\Services\PlantInventoryService;
use App\Services\TruckInventoryService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Sprint4FinalIntegrationCycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $operatorUser;
    protected User $driverUser;
    protected User $adminUser;
    protected Customer $customer;
    protected PlantInventoryService $plantService;
    protected TruckInventoryService $truckService;
    protected OrderCustodyService $custodyService;
    protected DeliveryRouteService $routeService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->plantService = app(PlantInventoryService::class);
        $this->truckService = app(TruckInventoryService::class);
        $this->custodyService = app(OrderCustodyService::class);
        $this->routeService = app(DeliveryRouteService::class);

        // Crear Usuarios
        $this->operatorUser = User::factory()->create([
            'name' => 'Operador Puritronic',
            'email' => 'operador@puritronic.com',
            'status' => 'active',
        ]);
        $this->operatorUser->assignRole('Operador Planta');

        $this->driverUser = User::factory()->create([
            'name' => 'Repartidor Ruta',
            'email' => 'repartidor@puritronic.com',
            'status' => 'active',
        ]);
        $this->driverUser->assignRole('Repartidor');

        $this->adminUser = User::factory()->create([
            'name' => 'Administrador Planta',
            'email' => 'admin@puritronic.com',
            'status' => 'active',
        ]);
        $this->adminUser->assignRole('Administrador');

        // Crear Cliente con 5 botellones fijos registrados
        $clientUser = User::factory()->create([
            'name' => 'Cliente Familia Morales',
            'email' => 'morales@cliente.com',
            'status' => 'active',
        ]);
        $clientUser->assignRole('Cliente');

        $this->customer = Customer::create([
            'user_id' => $clientUser->id,
            'name' => 'Familia Morales',
            'nickname' => 'Morales Fijos',
            'code' => 'CLI-MORALES',
            'fixed_bottles_count' => 5,
            'exchange_bottles_count' => 0,
            'status' => 'active',
        ]);

        CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'street' => 'Av. Independencia',
            'exterior_number' => '500',
            'neighborhood' => 'Centro',
            'delivery_zone' => 'Norte',
            'is_active' => true,
        ]);

        BottlePresentation::create([
            'name' => 'Garrafón 20L',
            'capacity' => 20.00,
            'container_type' => 'Garrafón 20L',
            'sale_price' => 90.00,
            'refill_price' => 15.00,
            'is_available_for_delivery' => true,
        ]);
    }

    /**
     * Helper para crear pedido con detalle de fijos.
     */
    protected function createFixedOrder(int $quantity = 3): Order
    {
        $order = Order::create([
            'customer_id' => $this->customer->id,
            'folio' => 'PED-TEST-' . uniqid(),
            'status' => Order::STATUS_CONFIRMED,
            'recipient_name' => 'Familia Morales',
            'delivery_street' => 'Av. Independencia',
            'delivery_exterior_number' => '500',
            'delivery_zone' => 'Norte',
            'address_snapshot' => ['street' => 'Av. Independencia', 'exterior_number' => '500'],
            'requires_invoice' => false,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'type' => OrderItem::TYPE_FIXED,
            'quantity' => $quantity,
            'unit_price' => 15.00,
            'status' => OrderItem::STATUS_PENDING,
        ]);

        return $order->fresh('items');
    }

    // =========================================================================
    // SECCIÓN 1: FIJOS Y CAMBIOS SIN LOCALIZAR (EXCLUSIÓN DE DISPONIBILIDAD)
    // =========================================================================

    public function test_fijos_sin_localizar_are_excluded_from_ready_to_load_availability(): void
    {
        // 1. Pedido de 3 fijos
        $order = $this->createFixedOrder(3);
        $item = $order->items->first();

        // 2. Repartidor recolecta los 3 fijos del cliente
        $this->custodyService->recordFixedBottlesCollected($order, 3, $this->driverUser);
        $item->refresh();
        $this->assertEquals(3, $item->collected_quantity);

        // 3. Planta recibe los 3 aptos
        $this->plantService->confirmFixedReception($this->operatorUser, $item, 3);
        $item->refresh();
        $this->assertEquals(3, $item->received_at_plant_quantity);

        // 4. Planta inicia y completa el lavado de los 3 fijos
        $this->plantService->startFixedWashing($this->operatorUser, $item, 3);
        $this->plantService->finishFixedPreparation($this->operatorUser, $item, 3);
        $item->refresh();

        $this->assertEquals(3, $item->prepared_quantity);
        $this->assertEquals(0, $item->reserved_quantity);
        $this->assertEquals(0, $item->loaded_quantity);
        $this->assertEquals(0, $item->unlocated_quantity);
        $this->assertEquals(3, $item->getReadyToLoadQuantity());

        // 5. Planta propone la carga de los 3 fijos al camión
        $movement = $this->truckService->proposePlantToTruckTransfer(
            $this->operatorUser,
            OrderItem::TYPE_FIXED,
            3,
            $this->customer->id,
            $order->id,
            'Propuesta de 3 fijos',
            $item->id
        );

        $item->refresh();
        $this->assertEquals(3, $item->reserved_quantity);
        $this->assertEquals(0, $item->getReadyToLoadQuantity(), 'Al estar reservados, la disponibilidad debe ser 0.');

        // 6. El Repartidor acepta 2, y 1 queda SIN LOCALIZAR (discrepancia)
        $this->truckService->acceptTransfer(
            $movement,
            $this->driverUser,
            2, // confirmedQuantity
            'No se encontró el tercer botellón en el andén de despacho',
            false, // retainedInPlant
            0, // retainedInPlantQuantity
            0, // damagedQuantity
            null,
            null,
            1  // unlocatedQuantity
        );

        $item->refresh();

        // Comprobación rigurosa de Seccion 1:
        // - Reserva se liberó por completo (0)
        // - Cargados al camión: 2
        // - Sin localizar registrado: 1
        // - Disponibles para volver a cargar: 0 (NO debe reaparecer como disponible)
        $this->assertEquals(0, $item->reserved_quantity, 'La reserva de la propuesta debe quedar en 0 tras aceptación.');
        $this->assertEquals(2, $item->loaded_quantity, 'Los cargados deben ser exactamente 2.');
        $this->assertEquals(1, $item->unlocated_quantity, 'El botellón no localizado debe quedar registrado de forma estructurada.');
        $this->assertEquals(0, $item->getReadyToLoadQuantity(), 'El botellón sin localizar NO debe reaparecer como preparado disponible.');
    }

    public function test_exchange_sin_localizar_are_tracked_and_excluded_from_plant_available(): void
    {
        // Línea base con conteo inicial
        $this->plantService->recordInitialCount($this->operatorUser, 50, 50, 'Conteo de apertura');
        $inv = $this->plantService->getOrCreatePlantInventory();
        $this->assertEquals(50, $inv->exchange_full_available);

        // Proponer carga de 10 botellones de cambio
        $movement = $this->truckService->proposePlantToTruckTransfer(
            $this->operatorUser,
            OrderItem::TYPE_EXCHANGE,
            10
        );

        $inv->refresh();
        $this->assertEquals(40, $inv->exchange_full_available);
        $this->assertEquals(10, $inv->exchange_full_reserved);

        // Chofer acepta 8, 2 sin localizar
        $this->truckService->acceptTransfer(
            $movement,
            $this->driverUser,
            8,
            'Faltaron 2 en el pallet entregado',
            false,
            0,
            0,
            null,
            null,
            2 // unlocated
        );

        $inv->refresh();
        $this->assertEquals(40, $inv->exchange_full_available, 'Los 2 faltantes no deben volver a disponibles.');
        $this->assertEquals(0, $inv->exchange_full_reserved, 'La reserva debe liberarse.');
        $this->assertEquals(2, $inv->exchange_unlocated, 'Los 2 faltantes deben quedar contabilizados en exchange_unlocated.');

        $truckInv = $this->truckService->getOrCreateTruckInventory($this->driverUser);
        $this->assertEquals(8, $truckInv->exchange_full_available, 'El camión solo debe tener los 8 aceptados.');
    }

    // =========================================================================
    // SECCIÓN 2: DAÑOS SEGÚN LA ETAPA SIN ALTERAR ACUMULADOS HISTÓRICOS
    // =========================================================================

    public function test_danos_across_all_five_stages_do_not_mutate_historical_accumulators_and_preserve_customer_count(): void
    {
        $initialCustomerBottles = $this->customer->fixed_bottles_count; // 5

        // Pedido de 5 fijos
        $order = $this->createFixedOrder(5);
        $item = $order->items->first();

        // 1. Recolección de 5 fijos
        $this->custodyService->recordFixedBottlesCollected($order, 5, $this->driverUser);
        $item->refresh();
        $this->assertEquals(5, $item->collected_quantity);

        // 2. Recepción en planta de los 5
        $this->plantService->confirmFixedReception($this->operatorUser, $item, 5);
        $item->refresh();
        $this->assertEquals(5, $item->received_at_plant_quantity);

        // --- ETAPA 1: Daño en Almacén de Vacíos (antes de lavar) ---
        // Se daña 1 envase recibido antes de iniciar lavado
        $this->plantService->recordBottleIncident($this->operatorUser, [
            'customer_id' => $this->customer->id,
            'order_item_id' => $item->id,
            'bottle_type' => OrderItem::TYPE_FIXED,
            'stage' => BottleIncident::STAGE_EMPTY_STORAGE,
            'damage_type' => BottleIncident::DAMAGE_CRACKED,
            'quantity' => 1,
            'notes' => 'Fisura en la base observada en almacén de vacíos',
        ]);
        $item->refresh();

        // Verificaciones Etapa 1:
        $this->assertEquals(5, $item->received_at_plant_quantity, 'received_at_plant_quantity histórico debe permanecer intacto.');
        $this->assertEquals(1, $item->getDamagedBeforeWashQuantity());
        $this->assertEquals(4, $item->getPendingToWashQuantity(), 'Pendientes de lavar debe descontar el dañado (5 - 0 - 1 = 4).');

        // --- ETAPA 2: Daño durante Lavado (en túnel) ---
        // Se ingresan los 4 aptos a túnel de lavado
        $this->plantService->startFixedWashing($this->operatorUser, $item, 4);
        $item->refresh();
        $this->assertEquals(4, $item->washing_quantity);

        // Se daña 1 envase durante el proceso de lavado/llenado
        $this->plantService->recordBottleIncident($this->operatorUser, [
            'customer_id' => $this->customer->id,
            'order_item_id' => $item->id,
            'bottle_type' => OrderItem::TYPE_FIXED,
            'stage' => BottleIncident::STAGE_WASHING,
            'damage_type' => BottleIncident::DAMAGE_DEFORMED,
            'quantity' => 1,
            'notes' => 'Envase deformado por calor durante sanitización',
        ]);
        $item->refresh();

        // Verificaciones Etapa 2:
        $this->assertEquals(4, $item->washing_quantity, 'washing_quantity histórico no debe modificarse.');
        $this->assertEquals(1, $item->getDamagedInWashingQuantity());
        $this->assertEquals(3, $item->getInWashingQuantity(), 'En túnel deben quedar 3 aptos (4 - 0 - 1 = 3).');

        // Se finaliza el lavado de los 3 aptos restantes
        $this->plantService->finishFixedPreparation($this->operatorUser, $item, 3);
        $item->refresh();
        $this->assertEquals(3, $item->prepared_quantity, 'prepared_quantity debe registrar los 3 exitosamente sellados.');
        $this->assertEquals(3, $item->getReadyToLoadQuantity());

        // --- ETAPA 3: Daño después de Preparar (Almacén de Producto Terminado) ---
        // Se daña 1 envase lleno almacenado en planta antes de cargarse
        $this->plantService->recordBottleIncident($this->operatorUser, [
            'customer_id' => $this->customer->id,
            'order_item_id' => $item->id,
            'bottle_type' => OrderItem::TYPE_FIXED,
            'stage' => BottleIncident::STAGE_PLANT_STORAGE,
            'damage_type' => BottleIncident::DAMAGE_BROKEN,
            'quantity' => 1,
            'notes' => 'Envase roto por caída accidental en estante de planta',
        ]);
        $item->refresh();

        // Verificaciones Etapa 3:
        $this->assertEquals(3, $item->prepared_quantity, 'prepared_quantity histórico no debe alterarse.');
        $this->assertEquals(2, $item->getReadyToLoadQuantity(), 'Listos para cargar debe descontar el dañado (3 - 0 - 0 - 0 - 1 = 2).');

        // --- ETAPA 4: Daño durante la Maniobra de Carga al Camión ---
        // Planta propone los 2 disponibles
        $loadMov = $this->truckService->proposePlantToTruckTransfer(
            $this->operatorUser,
            OrderItem::TYPE_FIXED,
            2,
            $this->customer->id,
            $order->id,
            'Propuesta de 2 fijos',
            $item->id
        );

        // Repartidor acepta 1 y reporta que 1 se dañó en la maniobra de carga
        $this->truckService->acceptTransfer(
            $loadMov,
            $this->driverUser,
            1, // 1 confirmado
            '1 botellón sufrió fisura al montarlo a la rejilla del camión',
            false,
            0,
            1, // 1 dañado en carga
            'fisura',
            'Fisura en hombro al montar'
        );
        $item->refresh();

        // Verificaciones Etapa 4:
        $this->assertEquals(1, $item->loaded_quantity, 'loaded_quantity histórico solo registra el que subió apto.');
        $this->assertEquals(1, $item->getInTruckQuantity(), 'En camión debe haber exactamente 1.');
        $this->assertEquals(0, $item->getReadyToLoadQuantity(), 'En planta disponibles para cargar debe ser 0.');

        // --- ETAPA 5: Daño en Camión durante el Trayecto hacia el Cliente ---
        // Se reporta que el botellón en el camión se dañó en tránsito
        $this->plantService->recordBottleIncident($this->driverUser, [
            'customer_id' => $this->customer->id,
            'order_item_id' => $item->id,
            'bottle_type' => OrderItem::TYPE_FIXED,
            'stage' => BottleIncident::STAGE_ON_TRUCK,
            'damage_type' => BottleIncident::DAMAGE_BROKEN,
            'quantity' => 1,
            'notes' => 'Rotura por bache pronunciado en trayecto',
        ]);
        $item->refresh();

        // Verificaciones Etapa 5:
        $this->assertEquals(1, $item->loaded_quantity, 'loaded_quantity histórico permanece en 1.');
        $this->assertEquals(1, $item->getDamagedInTruckQuantity());
        $this->assertEquals(0, $item->getInTruckQuantity(), 'En camión aptos para entregar debe ser 0.');

        // --- VERIFICACIÓN DE INVARIANZA DE PROPIEDAD DEL CLIENTE ---
        $this->customer->refresh();
        $this->assertEquals(
            $initialCustomerBottles,
            $this->customer->fixed_bottles_count,
            'El conteo personal de botellones del cliente NUNCA debe descontarse automáticamente por incidencias.'
        );

        // Comprobación de incidencias registradas con su etapa real
        $incidents = BottleIncident::where('order_item_id', $item->id)->get();
        $this->assertCount(5, $incidents, 'Deben existir 5 incidencias registradas en sus 5 etapas distintas.');
        $this->assertEquals([
            BottleIncident::STAGE_EMPTY_STORAGE,
            BottleIncident::STAGE_WASHING,
            BottleIncident::STAGE_PLANT_STORAGE,
            'carga_camion',
            BottleIncident::STAGE_ON_TRUCK,
        ], $incidents->pluck('stage')->toArray());
    }

    // =========================================================================
    // SECCIÓN 3: RECHAZO Y RETIRO DE PROPUESTAS CON DESGLOSE FÍSICO
    // =========================================================================

    public function test_rechazo_de_propuesta_con_desglose_fisico(): void
    {
        // Setup con 3 fijos preparados
        $order = $this->createFixedOrder(3);
        $item = $order->items->first();
        $this->custodyService->recordFixedBottlesCollected($order, 3, $this->driverUser);
        $this->plantService->confirmFixedReception($this->operatorUser, $item, 3);
        $this->plantService->startFixedWashing($this->operatorUser, $item, 3);
        $this->plantService->finishFixedPreparation($this->operatorUser, $item, 3);

        $mov = $this->truckService->proposePlantToTruckTransfer(
            $this->operatorUser,
            OrderItem::TYPE_FIXED,
            3,
            $this->customer->id,
            $order->id,
            'Propuesta de 3',
            $item->id
        );

        // Repartidor rechaza la propuesta con desglose estructurado:
        // 1 retenido en planta apto, 1 dañado en maniobra, 1 sin localizar
        $this->truckService->rejectTransfer(
            $mov,
            $this->driverUser,
            'Rechazo por discrepancias múltiples',
            1, // retenido apto
            1, // dañado
            'rotura',
            'Se rompió al manipular',
            1  // sin localizar
        );

        $item->refresh();
        $this->assertEquals(0, $item->reserved_quantity, 'La reserva debe estar liberada.');
        $this->assertEquals(1, $item->unlocated_quantity, '1 debe estar registrado como sin localizar.');
        $this->assertEquals(1, $item->getReadyToLoadQuantity(), 'Exactamente 1 (el retenido apto) debe quedar disponible para cargar.');
        $this->assertEquals(0, $item->loaded_quantity, 'Nada debe haberse cargado al camión.');
    }

    public function test_retiro_de_propuesta_por_planta_con_desglose_fisico(): void
    {
        // Setup con 3 fijos preparados
        $order = $this->createFixedOrder(3);
        $item = $order->items->first();
        $this->custodyService->recordFixedBottlesCollected($order, 3, $this->driverUser);
        $this->plantService->confirmFixedReception($this->operatorUser, $item, 3);
        $this->plantService->startFixedWashing($this->operatorUser, $item, 3);
        $this->plantService->finishFixedPreparation($this->operatorUser, $item, 3);

        $mov = $this->truckService->proposePlantToTruckTransfer(
            $this->operatorUser,
            OrderItem::TYPE_FIXED,
            3,
            $this->customer->id,
            $order->id,
            'Propuesta para retirar',
            $item->id
        );

        // Planta retira antes de respuesta del chofer:
        // 1 retenido apto, 1 dañado, 1 sin localizar
        $this->truckService->withdrawTransferProposal(
            $mov,
            $this->operatorUser,
            1, // retenido
            1, // dañado
            'fisura',
            'Fisura encontrada al preparar despacho',
            1  // sin localizar
        );

        $item->refresh();
        $this->assertEquals(0, $item->reserved_quantity);
        $this->assertEquals(1, $item->unlocated_quantity);
        $this->assertEquals(1, $item->getReadyToLoadQuantity(), 'Solo 1 debe quedar listo para volver a proponerse.');
        $this->assertEquals(BottleCustodyMovement::STATUS_CANCELLED, $mov->fresh()->status);
    }

    public function test_bloqueo_de_concurrencia_impide_doble_operacion(): void
    {
        $order = $this->createFixedOrder(1);
        $item = $order->items->first();
        $this->custodyService->recordFixedBottlesCollected($order, 1, $this->driverUser);
        $this->plantService->confirmFixedReception($this->operatorUser, $item, 1);
        $this->plantService->startFixedWashing($this->operatorUser, $item, 1);
        $this->plantService->finishFixedPreparation($this->operatorUser, $item, 1);

        $mov = $this->truckService->proposePlantToTruckTransfer(
            $this->operatorUser,
            OrderItem::TYPE_FIXED,
            1,
            $this->customer->id,
            $order->id,
            'Propuesta única',
            $item->id
        );

        // Planta retira
        $this->truckService->withdrawTransferProposal($mov, $this->operatorUser);

        // Chofer intenta aceptar la misma propuesta ya cancelada -> debe lanzar excepción
        $this->expectException(OrderBusinessRuleException::class);
        $this->truckService->acceptTransfer($mov, $this->driverUser, 1);
    }

    // =========================================================================
    // SECCIÓN 4: DEVOLUCIÓN DE FIJOS AL CLIENTE (ENTREGA PARCIAL Y CIERRE)
    // =========================================================================

    public function test_devolucion_de_fijos_restriccion_y_entrega_parcial_y_cierre(): void
    {
        // 1. Pedido de 2 fijos
        $order = $this->createFixedOrder(2);
        $item = $order->items->first();

        // 2. Recolección de 2 fijos
        $this->custodyService->recordFixedBottlesCollected($order, 2, $this->driverUser);
        $this->plantService->confirmFixedReception($this->operatorUser, $item, 2);
        $this->plantService->startFixedWashing($this->operatorUser, $item, 2);
        $this->plantService->finishFixedPreparation($this->operatorUser, $item, 2);

        // 3. Carga y aceptación de 2 fijos en el camión
        $loadMov = $this->truckService->proposePlantToTruckTransfer(
            $this->operatorUser,
            OrderItem::TYPE_FIXED,
            2,
            $this->customer->id,
            $order->id,
            'Carga de 2 fijos',
            $item->id
        );
        $this->truckService->acceptTransfer($loadMov, $this->driverUser, 2);

        $item->refresh();
        $this->assertEquals(2, $item->loaded_quantity);
        $this->assertEquals(2, $item->getInTruckQuantity());

        // 4. Se asigna a ruta del Repartidor
        $route = $this->routeService->startOrCreateSoleDriverRoute($this->driverUser);
        $stop = $this->routeService->addOrderToActiveRoute($route, $order, $this->driverUser);

        // 5. Intento de entregar MÁS unidades de las disponibles en el camión para este cliente
        try {
            $this->routeService->recordStopCompleted($stop, [
                'fixed_delivered' => 5, // intenta entregar 5 habiendo solo 2 en camión
            ], $this->driverUser);
            $this->fail('Debe rechazar la entrega de más fijos de los cargados para ese cliente.');
        } catch (OrderBusinessRuleException $e) {
            $this->assertStringContainsString('Solo hay 2 disponible(s) en el camión', $e->getMessage());
        }

        // 6. ENTREGA PARCIAL: Chofer entrega 1 de los 2 fijos
        $this->routeService->recordStopCompleted($stop, [
            'fixed_delivered' => 1,
            'units_paid_cash' => 1,
            'cash_tendered' => 15.00,
        ], $this->driverUser);

        $item->refresh();
        $order->refresh();

        // Comprobaciones de entrega parcial:
        $this->assertEquals(1, $item->delivered_quantity, 'Debe haberse registrado 1 entregado.');
        $this->assertEquals(1, $item->getInTruckQuantity(), 'En el camión debe quedar 1 fijo disponible.');
        $this->assertEquals(OrderItem::STATUS_OUT_FOR_DELIVERY, $item->status, 'El renglón debe continuar activo (no completado).');
        $this->assertEquals(Order::STATUS_IN_PROCESS, $order->status, 'El pedido debe permanecer en proceso al tener custodia pendiente.');
        $this->assertTrue($order->hasCollectedFixedCustody(), 'El pedido tiene custodia de 1 fijo pendiente.');

        // Propiedad del cliente intacta
        $this->customer->refresh();
        $this->assertEquals(5, $this->customer->fixed_bottles_count);

        // Cobro realizado de exactamente 1 botellón ($15.00)
        $this->assertEquals(15.00, (float) $order->charges()->sum('total_amount'));
        $this->assertEquals(15.00, (float) $order->payments()->sum('amount'));

        // 7. SEGUNDA PARADA EN NUEVA RUTA / ENTREGA FINAL DEL SALDO RESTANTE
        $route2 = DeliveryRoute::create([
            'driver_user_id' => $this->driverUser->id,
            'folio' => 'RUT-2-' . uniqid(),
            'date' => today(),
            'status' => DeliveryRoute::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);
        $secondStop = RouteStop::create([
            'delivery_route_id' => $route2->id,
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'stop_number' => 1,
            'status' => RouteStop::STATUS_PENDING,
        ]);

        $this->routeService->recordStopCompleted($secondStop, [
            'fixed_delivered' => 1,
            'units_paid_cash' => 1,
            'cash_tendered' => 15.00,
        ], $this->driverUser);

        $item->refresh();
        $order->refresh();

        // Comprobaciones de cierre definitivo:
        $this->assertEquals(2, $item->delivered_quantity);
        $this->assertEquals(0, $item->getInTruckQuantity(), 'En el camión deben quedar 0.');
        $this->assertEquals(OrderItem::STATUS_DELIVERED, $item->status);
        $this->assertEquals(Order::STATUS_DELIVERED, $order->status, 'El pedido debe quedar completamente ENTREGADO.');
        $this->assertFalse($order->hasCollectedFixedCustody(), 'Ya no hay custodia pendiente.');
        $this->assertEquals(30.00, (float) $order->charges()->sum('total_amount'), 'Total cobrado debe ser exactamente 2 x $15 = $30.');
    }

    // =========================================================================
    // SECCIÓN 5: PRUEBA COMPLETA DE CONSERVACIÓN FÍSICA (TABLA DE BALANCES)
    // =========================================================================

    public function test_conservacion_integral_de_saldos_fijos_y_cambios(): void
    {
        // -------------------------------------------------------------
        // CONSERVACIÓN DE CAMBIOS: Fondo inicial de cambio (40 vacíos + 60 llenos = 100)
        // Se registra al inicio como línea base antes de operaciones
        // -------------------------------------------------------------
        $this->plantService->recordInitialCount($this->operatorUser, 40, 60, 'Fondo inicial cambio');
        $initialFund = 100;

        // -------------------------------------------------------------
        // CONSERVACIÓN DE FIJOS: Pedido con 6 botellones fijos
        // -------------------------------------------------------------
        $order = $this->createFixedOrder(6);
        $item = $order->items->first();

        // Paso A: Recolección
        $this->custodyService->recordFixedBottlesCollected($order, 6, $this->driverUser);
        // Paso B: Recepción en planta de los 6
        $this->plantService->confirmFixedReception($this->operatorUser, $item, 6);
        // Paso C: Daño en almacén vacío (1)
        $this->plantService->recordBottleIncident($this->operatorUser, [
            'customer_id' => $this->customer->id,
            'order_item_id' => $item->id,
            'bottle_type' => OrderItem::TYPE_FIXED,
            'stage' => BottleIncident::STAGE_EMPTY_STORAGE,
            'damage_type' => BottleIncident::DAMAGE_CRACKED,
            'quantity' => 1,
            'notes' => 'Fisura vacio',
        ]);
        // Paso D: Lavado de los 5 aptos
        $this->plantService->startFixedWashing($this->operatorUser, $item, 5);
        // Paso E: Daño en lavado (1)
        $this->plantService->recordBottleIncident($this->operatorUser, [
            'customer_id' => $this->customer->id,
            'order_item_id' => $item->id,
            'bottle_type' => OrderItem::TYPE_FIXED,
            'stage' => BottleIncident::STAGE_WASHING,
            'damage_type' => BottleIncident::DAMAGE_DEFORMED,
            'quantity' => 1,
            'notes' => 'Deformado en lavado',
        ]);
        // Paso F: Terminar lavado de los 4 aptos
        $this->plantService->finishFixedPreparation($this->operatorUser, $item, 4);
        // Paso G: Proponer carga de 3
        $mov = $this->truckService->proposePlantToTruckTransfer(
            $this->operatorUser,
            OrderItem::TYPE_FIXED,
            3,
            $this->customer->id,
            $order->id,
            'Propuesta de 3',
            $item->id
        );
        // Paso H: Chofer acepta 2, 1 sin localizar
        $this->truckService->acceptTransfer(
            $mov,
            $this->driverUser,
            2,
            '1 sin localizar en andén',
            false,
            0,
            0,
            null,
            null,
            1 // unlocated
        );
        // Paso I: Chofer entrega 1 al cliente
        $route = $this->routeService->startOrCreateSoleDriverRoute($this->driverUser);
        $stop = $this->routeService->addOrderToActiveRoute($route, $order, $this->driverUser);
        $this->routeService->recordStopCompleted($stop, [
            'fixed_delivered' => 1,
            'units_paid_cash' => 1,
            'cash_tendered' => 15.00,
        ], $this->driverUser);

        $item->refresh();

        // Cálculo riguroso de cada compartimento físico mutuamente excluyente:
        $pendingWash   = $item->getPendingToWashQuantity();   // 0
        $inWashing     = $item->getInWashingQuantity();       // 0
        $readyToLoad   = $item->getReadyToLoadQuantity();     // 1 (4 preparados - 2 cargados - 1 unlocated = 1)
        $reservedLoad  = (int) $item->reserved_quantity;      // 0
        $inTruck       = $item->getInTruckQuantity();         // 1 (2 cargados - 1 entregado = 1)
        $unlocated     = (int) $item->unlocated_quantity;     // 1
        $damagedTotal  = (int) $item->incidents()->sum('quantity'); // 2 (1 vacio + 1 lavado)
        $delivered     = (int) $item->delivered_quantity;     // 1

        $totalFixedReconstructed = $pendingWash + $inWashing + $readyToLoad + $reservedLoad + $inTruck + $unlocated + $damagedTotal + $delivered;

        $this->assertEquals(6, $item->collected_quantity, 'Total recolectado de cliente.');
        $this->assertEquals(6, $totalFixedReconstructed, 'La suma de todas las etapas mutuamente excluyentes debe igualar exactamente los 6 botellones recogidos.');

        // -------------------------------------------------------------
        // CONSERVACIÓN DE CAMBIOS: Fondo inicial = 100 (40 vacíos + 60 llenos)
        // -------------------------------------------------------------
        // Iniciar lavado de 10 vacíos
        $batch = $this->plantService->startExchangeBatch($this->operatorUser, 10);
        // Daño en lote de 2
        $this->plantService->reportBatchDamage($this->operatorUser, $batch, 2, 'rotura', 'Roto en túnel');
        // Finalizar los 8 restantes del lote
        $this->plantService->finishExchangeBatch($this->operatorUser, $batch, 8);

        // Proponer carga de 20 llenos al camión
        $exchangeLoad = $this->truckService->proposePlantToTruckTransfer(
            $this->operatorUser,
            OrderItem::TYPE_EXCHANGE,
            20
        );
        // Chofer acepta 18, 2 sin localizar
        $this->truckService->acceptTransfer(
            $exchangeLoad,
            $this->driverUser,
            18,
            '2 sin localizar',
            false,
            0,
            0,
            null,
            null,
            2
        );

        $plantInv = $this->plantService->getOrCreatePlantInventory();
        $truckInv = $this->truckService->getOrCreateTruckInventory($this->driverUser);
        $damagedExchangeTotal = (int) BottleIncident::where('bottle_type', OrderItem::TYPE_EXCHANGE)->sum('quantity');

        $plantEmptyAvailable   = $plantInv->exchange_empty_available;
        $plantWashingFilling   = $plantInv->exchange_washing_filling;
        $plantFullAvailable    = $plantInv->exchange_full_available;
        $plantFullReserved     = $plantInv->exchange_full_reserved;
        $plantUnlocated        = $plantInv->exchange_unlocated;
        $truckFullAvailable    = $truckInv->exchange_full_available;
        $truckEmptyCollected   = $truckInv->exchange_empty_collected;

        $totalExchangeReconstructed = $plantEmptyAvailable
            + $plantWashingFilling
            + $plantFullAvailable
            + $plantFullReserved
            + $plantUnlocated
            + $truckFullAvailable
            + $truckEmptyCollected
            + $damagedExchangeTotal;

        $this->assertEquals(
            $initialFund,
            $totalExchangeReconstructed,
            "La suma de todas las etapas de botellones de cambio debe igualar exactamente el fondo inicial de {$initialFund}."
        );
    }
}
