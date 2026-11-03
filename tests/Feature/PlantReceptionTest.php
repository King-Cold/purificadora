<?php

namespace Tests\Feature;

use App\Exceptions\OrderBusinessRuleException;
use App\Models\BottleCustodyMovement;
use App\Models\BottleIncident;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PlantInventory;
use App\Models\TruckInventory;
use App\Models\User;
use App\Services\OrderCustodyService;
use App\Services\PlantInventoryService;
use App\Services\TruckInventoryService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlantReceptionTest extends TestCase
{
    use RefreshDatabase;

    protected User $plantaUser;
    protected User $adminUser;
    protected User $repartidorUser;
    protected User $clientUser;
    protected Customer $customer1;
    protected Customer $customer2;
    protected PlantInventoryService $plantService;
    protected TruckInventoryService $truckService;
    protected OrderCustodyService $custodyService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->plantService = app(PlantInventoryService::class);
        $this->truckService = app(TruckInventoryService::class);
        $this->custodyService = app(OrderCustodyService::class);

        // Operador Planta
        $this->plantaUser = User::factory()->create([
            'name' => 'Llenador Puritronic',
            'email' => 'planta@puritronic.com',
        ]);
        $this->plantaUser->assignRole('Operador Planta');

        // Administrador
        $this->adminUser = User::factory()->create([
            'name' => 'Administrador General',
            'email' => 'admin@puritronic.com',
        ]);
        $this->adminUser->assignRole('Administrador');

        // Repartidor
        $this->repartidorUser = User::factory()->create([
            'name' => 'Repartidor Ruta',
            'email' => 'repartidor@puritronic.com',
        ]);
        $this->repartidorUser->assignRole('Repartidor');

        // Cliente 1
        $this->clientUser = User::factory()->create([
            'name' => 'Cliente Doña Sofía',
            'email' => 'sofia@gmail.com',
        ]);
        $this->clientUser->assignRole('Cliente');

        $this->customer1 = Customer::create([
            'user_id' => $this->clientUser->id,
            'code' => 'CLI-0001',
            'name' => 'Doña Sofía',
            'nickname' => 'Doña Sofía',
            'fixed_bottles_count' => 5,
            'exchange_bottles_count' => 2,
        ]);

        CustomerAddress::create([
            'customer_id' => $this->customer1->id,
            'street' => 'Av Hidalgo',
            'exterior_number' => '100',
            'neighborhood' => 'Centro',
            'city' => 'Hocabá',
            'postal_code' => '87000',
            'zone' => 'Norte',
            'is_active' => true,
        ]);

        // Cliente 2
        $this->customer2 = Customer::create([
            'user_id' => null,
            'code' => 'CLI-0002',
            'name' => 'Don Pancho',
            'nickname' => 'Don Pancho',
            'fixed_bottles_count' => 3,
            'exchange_bottles_count' => 0,
        ]);
    }

    /**
     * 1. Consulta del listado "Por Recibir" muestra fijos y cambios pendientes sin darlos por recibidos.
     */
    public function test_operador_can_view_pending_receptions_index(): void
    {
        // 1. Pedido de fijos con 2 recolectados por el chofer
        $order = Order::create([
            'folio' => 'PED-REC-01',
            'customer_id' => $this->customer1->id,
            'recipient_name' => 'Doña Sofía',
            'delivery_street' => 'Av Hidalgo',
            'delivery_zone' => 'Norte',
            'address_snapshot' => ['street' => 'Av Hidalgo'],
            'status' => Order::STATUS_IN_PROCESS,
        ]);

        $fixedItem = OrderItem::create([
            'order_id' => $order->id,
            'type' => OrderItem::TYPE_FIXED,
            'quantity' => 2,
            'collected_quantity' => 2,
            'received_at_plant_quantity' => 0,
            'status' => OrderItem::STATUS_TRANSPORTING_TO_PLANT,
        ]);

        // 2. Chofer propone descarga de 10 cambios vacíos
        $truckInv = $this->truckService->getOrCreateTruckInventory($this->repartidorUser);
        $truckInv->update(['exchange_empty_collected' => 10]);

        $exchangeMovement = $this->truckService->proposeTruckToPlantUnload(
            $this->repartidorUser,
            OrderItem::TYPE_EXCHANGE,
            BottleCustodyMovement::CONDITION_EMPTY,
            10,
            null,
            'Descarga matutina de vacíos'
        );

        // Operador consulta pantalla "Por Recibir"
        $response = $this->actingAs($this->plantaUser)->get(route('planta.reception.index'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Planta/Reception/Index')
            ->has('pendingReceptions.fixed_pending', 1)
            ->has('pendingReceptions.exchange_pending', 1)
            ->where('pendingReceptions.fixed_pending.0.pending_quantity', 2)
            ->where('pendingReceptions.exchange_pending.0.quantity_declared', 10)
        );

        // Verificar que los botellones NO se marcaron como recibidos en planta automáticamente
        $this->assertEquals(0, $fixedItem->fresh()->received_at_plant_quantity);
        $this->assertEquals(0, $this->plantService->getOrCreatePlantInventory()->exchange_empty_available);
    }

    /**
     * 2. Confirmación completa de fijos: recibe físicamente y pasa a lavado si se autoriza.
     */
    public function test_confirm_fixed_reception_full_and_start_washing(): void
    {
        $order = Order::create([
            'folio' => 'PED-REC-02',
            'customer_id' => $this->customer1->id,
            'recipient_name' => 'Doña Sofía',
            'delivery_street' => 'Av Hidalgo',
            'delivery_zone' => 'Norte',
            'address_snapshot' => ['street' => 'Av Hidalgo'],
            'status' => Order::STATUS_IN_PROCESS,
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'type' => OrderItem::TYPE_FIXED,
            'quantity' => 3,
            'collected_quantity' => 3,
            'received_at_plant_quantity' => 0,
            'status' => OrderItem::STATUS_TRANSPORTING_TO_PLANT,
        ]);

        // Operador confirma la recepción física completa (3) e inicia lavado
        $res = $this->actingAs($this->plantaUser)->post(route('planta.reception.confirm-fixed'), [
            'order_item_id' => $item->id,
            'received_quantity' => 3,
            'start_washing' => true,
        ]);

        $res->assertSessionHasNoErrors();
        $res->assertSessionHas('receipt');

        $item->refresh();
        $this->assertEquals(3, $item->received_at_plant_quantity);
        $this->assertEquals(OrderItem::STATUS_WASHING, $item->status);

        // Bitácora de custodia registrada con éxito
        $this->assertDatabaseHas('bottle_custody_movements', [
            'order_id' => $order->id,
            'movement_type' => BottleCustodyMovement::TYPE_PLANT_RECEPTION_FIXED,
            'quantity' => 3,
            'status' => BottleCustodyMovement::STATUS_ACCEPTED,
            'confirmed_by_user_id' => $this->plantaUser->id,
        ]);
    }

    /**
     * 3. Recepción parcial de fijos con faltantes: exige motivo y mantiene la discrepancia sin asignarla a planta.
     */
    public function test_confirm_fixed_reception_partial_with_shortage_requires_reason(): void
    {
        $order = Order::create([
            'folio' => 'PED-REC-03',
            'customer_id' => $this->customer1->id,
            'recipient_name' => 'Doña Sofía',
            'delivery_street' => 'Av Hidalgo',
            'delivery_zone' => 'Norte',
            'address_snapshot' => ['street' => 'Av Hidalgo'],
            'status' => Order::STATUS_IN_PROCESS,
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'type' => OrderItem::TYPE_FIXED,
            'quantity' => 3,
            'collected_quantity' => 3,
            'received_at_plant_quantity' => 0,
            'status' => OrderItem::STATUS_TRANSPORTING_TO_PLANT,
        ]);

        // 1. Intento sin motivo obligatorio ante faltante debe fallar
        $resFail = $this->actingAs($this->plantaUser)->post(route('planta.reception.confirm-fixed'), [
            'order_item_id' => $item->id,
            'received_quantity' => 2, // Faltó 1
            'difference_reason' => '',
        ]);
        $resFail->assertSessionHasErrors(['difference_reason']);

        // 2. Con motivo obligatorio de discrepancia
        $resOk = $this->actingAs($this->plantaUser)->post(route('planta.reception.confirm-fixed'), [
            'order_item_id' => $item->id,
            'received_quantity' => 2,
            'difference_reason' => 'Chofer reporta 1 botellón que no fue entregado en planta (extraviado en ruta)',
            'start_washing' => true,
        ]);
        $resOk->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertEquals(2, $item->received_at_plant_quantity);

        // La discrepancia queda asentada en el movimiento sin ubicación física inventada
        $this->assertDatabaseHas('bottle_custody_movements', [
            'order_id' => $order->id,
            'movement_type' => BottleCustodyMovement::TYPE_PLANT_RECEPTION_FIXED,
            'quantity' => 2,
            'status' => 'accepted_with_discrepancy',
            'difference_reason' => 'Chofer reporta 1 botellón que no fue entregado en planta (extraviado en ruta)',
        ]);
    }

    /**
     * 4. Recepción con envases dañados detectados al recibir:
     * - Registra incidencia vinculada al cliente y pedido.
     * - Separa los dañados de los que entran a lavado.
     * - No cobra rellenado ni descuenta conteos personales del cliente.
     */
    public function test_confirm_fixed_reception_with_damaged_bottles_creates_incident_and_separates(): void
    {
        $order = Order::create([
            'folio' => 'PED-REC-04',
            'customer_id' => $this->customer1->id,
            'recipient_name' => 'Doña Sofía',
            'delivery_street' => 'Av Hidalgo',
            'delivery_zone' => 'Norte',
            'address_snapshot' => ['street' => 'Av Hidalgo'],
            'status' => Order::STATUS_IN_PROCESS,
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'type' => OrderItem::TYPE_FIXED,
            'quantity' => 2,
            'collected_quantity' => 2,
            'received_at_plant_quantity' => 0,
            'status' => OrderItem::STATUS_TRANSPORTING_TO_PLANT,
        ]);

        // Chofer trajo 2: 1 apto y 1 roto en cuello detectado en recepción
        $res = $this->actingAs($this->plantaUser)->post(route('planta.reception.confirm-fixed'), [
            'order_item_id' => $item->id,
            'received_quantity' => 1,
            'damaged_quantity' => 1,
            'damage_type' => BottleIncident::DAMAGE_BROKEN,
            'damage_notes' => 'Quebradura en boca del envase detectada al descargar en planta',
            'start_washing' => true,
        ]);
        $res->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertEquals(1, $item->received_at_plant_quantity);
        $this->assertEquals(1, $item->damaged_quantity);

        // Incidencia registrada en etapa recepción
        $this->assertDatabaseHas('bottle_incidents', [
            'customer_id' => $this->customer1->id,
            'order_id' => $order->id,
            'quantity' => 1,
            'stage' => BottleIncident::STAGE_RECEPTION,
            'status' => BottleIncident::STATUS_PENDING,
        ]);

        // Conteos del cliente intactos
        $this->assertEquals(5, $this->customer1->fresh()->fixed_bottles_count);
    }

    /**
     * 5. Fijos de distintos clientes no se mezclan en la recepción.
     */
    public function test_fijos_from_different_customers_do_not_mix(): void
    {
        // Pedido Cliente 1 (Sofía: 2 fijos)
        $order1 = Order::create([
            'folio' => 'PED-SOFIA-01',
            'customer_id' => $this->customer1->id,
            'recipient_name' => 'Doña Sofía',
            'delivery_street' => 'Av Hidalgo',
            'delivery_zone' => 'Norte',
            'address_snapshot' => ['street' => 'Av Hidalgo'],
            'status' => Order::STATUS_IN_PROCESS,
        ]);
        $item1 = OrderItem::create([
            'order_id' => $order1->id,
            'type' => OrderItem::TYPE_FIXED,
            'quantity' => 2,
            'collected_quantity' => 2,
            'received_at_plant_quantity' => 0,
            'status' => OrderItem::STATUS_TRANSPORTING_TO_PLANT,
        ]);

        // Pedido Cliente 2 (Pancho: 3 fijos)
        $order2 = Order::create([
            'folio' => 'PED-PANCHO-01',
            'customer_id' => $this->customer2->id,
            'recipient_name' => 'Don Pancho',
            'delivery_street' => 'Calle 50',
            'delivery_zone' => 'Sur',
            'address_snapshot' => ['street' => 'Calle 50'],
            'status' => Order::STATUS_IN_PROCESS,
        ]);
        $item2 = OrderItem::create([
            'order_id' => $order2->id,
            'type' => OrderItem::TYPE_FIXED,
            'quantity' => 3,
            'collected_quantity' => 3,
            'received_at_plant_quantity' => 0,
            'status' => OrderItem::STATUS_TRANSPORTING_TO_PLANT,
        ]);

        // Confirmar recepción solo de Sofía
        $this->actingAs($this->plantaUser)->post(route('planta.reception.confirm-fixed'), [
            'order_item_id' => $item1->id,
            'received_quantity' => 2,
            'start_washing' => true,
        ]);

        $this->assertEquals(2, $item1->fresh()->received_at_plant_quantity);
        $this->assertEquals(0, $item2->fresh()->received_at_plant_quantity);
        $this->assertEquals(OrderItem::STATUS_TRANSPORTING_TO_PLANT, $item2->fresh()->status);
    }

    /**
     * 6. Recepción de cambios vacíos incrementa planta y descuenta camión una sola vez.
     */
    public function test_confirm_exchange_reception_increments_plant_and_decrements_truck(): void
    {
        // Conteo inicial de planta: 10 vacíos, 20 llenos
        $this->plantService->recordInitialCount($this->plantaUser, 10, 20);

        // Camión tiene 15 vacíos recogidos en ruta
        $truckInv = $this->truckService->getOrCreateTruckInventory($this->repartidorUser);
        $truckInv->update(['exchange_empty_collected' => 15]);

        // Chofer propone descarga de 15 vacíos
        $movement = $this->truckService->proposeTruckToPlantUnload(
            $this->repartidorUser,
            OrderItem::TYPE_EXCHANGE,
            BottleCustodyMovement::CONDITION_EMPTY,
            15
        );

        // Operador planta confirma la recepción de los 15 vacíos
        $res = $this->actingAs($this->plantaUser)->post(route('planta.reception.confirm-exchange'), [
            'movement_id' => $movement->id,
            'received_quantity' => 15,
        ]);
        $res->assertSessionHasNoErrors();

        // 1. Planta incrementó en 15 vacíos (10 + 15 = 25)
        $plantState = $this->plantService->getInventoryState();
        $this->assertEquals(25, $plantState['balances']['exchange_empty_available']);

        // 2. Camión descontó los 15 vacíos (15 - 15 = 0)
        $this->assertEquals(0, $truckInv->fresh()->exchange_empty_collected);

        // 3. Transferencia marcada como aceptada
        $this->assertEquals(BottleCustodyMovement::STATUS_ACCEPTED, $movement->fresh()->status);
    }

    /**
     * 7. Recepción parcial de cambios con dañados y discrepancia:
     * - No ingresa dañados a disponibles de planta.
     * - Mantiene discrepancia identificada.
     */
    public function test_confirm_exchange_reception_partial_with_damage_separates_damage(): void
    {
        $this->plantService->recordInitialCount($this->plantaUser, 0, 50);

        $truckInv = $this->truckService->getOrCreateTruckInventory($this->repartidorUser);
        $truckInv->update(['exchange_empty_collected' => 20]);

        $movement = $this->truckService->proposeTruckToPlantUnload(
            $this->repartidorUser,
            OrderItem::TYPE_EXCHANGE,
            BottleCustodyMovement::CONDITION_EMPTY,
            20
        );

        // De los 20: 16 aptos recibidos, 2 rotos en descarga, 2 faltantes con motivo
        $res = $this->actingAs($this->plantaUser)->post(route('planta.reception.confirm-exchange'), [
            'movement_id' => $movement->id,
            'received_quantity' => 16,
            'damaged_quantity' => 2,
            'damage_type' => BottleIncident::DAMAGE_BROKEN,
            'damage_notes' => '2 garrafones con cuarteadura en base',
            'difference_reason' => '2 botellones no se bajaron del camión / merma en ruta',
        ]);
        $res->assertSessionHasNoErrors();

        // Planta: solo 16 vacíos aptos ingresaron
        $plantState = $this->plantService->getInventoryState();
        $this->assertEquals(16, $plantState['balances']['exchange_empty_available']);
        $this->assertEquals(2, $plantState['balances']['exchange_damaged_total']);

        // Camión: se descontaron 18 unidades que bajaron del camión (16 + 2 dañados)
        $this->assertEquals(2, $truckInv->fresh()->exchange_empty_collected);
    }

    /**
     * 8. El chofer NO puede confirmarse recepciones a sí mismo en planta.
     */
    public function test_driver_cannot_confirm_receptions_for_self(): void
    {
        $truckInv = $this->truckService->getOrCreateTruckInventory($this->repartidorUser);
        $truckInv->update(['exchange_empty_collected' => 5]);

        $movement = $this->truckService->proposeTruckToPlantUnload(
            $this->repartidorUser,
            OrderItem::TYPE_EXCHANGE,
            BottleCustodyMovement::CONDITION_EMPTY,
            5
        );

        // Chofer intenta llamar al endpoint de planta
        $res = $this->actingAs($this->repartidorUser)->post(route('planta.reception.confirm-exchange'), [
            'movement_id' => $movement->id,
            'received_quantity' => 5,
        ]);
        $res->assertForbidden();
    }

    /**
     * 9. El conteo inicial físico se BLOQUEA si ya existen movimientos de custodia en planta.
     */
    public function test_initial_count_blocked_if_prior_plant_movements_exist(): void
    {
        // Registrar un movimiento previo de custodia con planta
        BottleCustodyMovement::create([
            'user_id' => $this->plantaUser->id,
            'movement_type' => BottleCustodyMovement::TYPE_PLANT_RECEPTION_FIXED,
            'bottle_type' => OrderItem::TYPE_FIXED,
            'bottle_condition' => BottleCustodyMovement::CONDITION_EMPTY,
            'quantity' => 4,
            'origin' => 'truck',
            'destination' => 'plant',
            'status' => BottleCustodyMovement::STATUS_ACCEPTED,
            'confirmed_at' => now(),
        ]);

        // Intentar registrar conteo inicial debe ser bloqueado exigiendo conciliación previa
        $this->expectException(OrderBusinessRuleException::class);
        $this->expectExceptionMessage('movimiento(s) de custodia de planta previos sin conciliar');

        $this->plantService->recordInitialCount($this->plantaUser, 10, 20);
    }
}

