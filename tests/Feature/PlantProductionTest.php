<?php

namespace Tests\Feature;

use App\Models\BottleCustodyMovement;
use App\Models\BottleIncident;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PlantInventory;
use App\Models\PlantProductionBatch;
use App\Models\User;
use App\Services\PlantInventoryService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlantProductionTest extends TestCase
{
    use RefreshDatabase;

    protected User $operatorUser;
    protected User $adminUser;
    protected User $driverUser;
    protected Customer $customer;
    protected PlantInventoryService $plantService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->plantService = app(PlantInventoryService::class);

        // Operador Planta
        $this->operatorUser = User::factory()->create([
            'name' => 'Operador Puritronic',
            'email' => 'operador@puritronic.com',
        ]);
        $this->operatorUser->assignRole('Operador Planta');

        // Administrador
        $this->adminUser = User::factory()->create([
            'name' => 'Administrador Planta',
            'email' => 'admin@puritronic.com',
        ]);
        $this->adminUser->assignRole('Administrador');

        // Repartidor
        $this->driverUser = User::factory()->create([
            'name' => 'Repartidor Ruta',
            'email' => 'repartidor@puritronic.com',
        ]);
        $this->driverUser->assignRole('Repartidor');

        // Cliente
        $clientUser = User::factory()->create([
            'name' => 'Cliente Fijo',
            'email' => 'fijo@cliente.com',
        ]);
        $clientUser->assignRole('Cliente');

        $this->customer = Customer::create([
            'user_id' => $clientUser->id,
            'code' => 'CLI-1001',
            'name' => 'Don Fernando',
            'nickname' => 'Don Fer',
            'fixed_bottles_count' => 5,
            'exchange_bottles_count' => 0,
        ]);

        CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'street' => 'Calle 50',
            'exterior_number' => '123',
            'neighborhood' => 'Centro',
            'city' => 'Hocabá',
            'postal_code' => '87000',
            'zone' => 'Norte',
            'is_active' => true,
        ]);

        // Registrar conteo inicial para activar validaciones estrictas
        $this->plantService->recordInitialCount($this->operatorUser, 50, 20, 'Línea base para pruebas');
    }

    /**
     * Helper para crear pedido con renglón fijo recibido en planta.
     */
    protected function createOrderWithFixedReceived(int $receivedQuantity = 5): array
    {
        $order = Order::create([
            'customer_id' => $this->customer->id,
            'folio' => 'ORD-FIJO-' . uniqid(),
            'status' => Order::STATUS_IN_PROCESS,
            'recipient_name' => 'Don Fer',
            'delivery_street' => 'Calle 50 #123',
            'address_snapshot' => ['street' => 'Calle 50', 'number' => '123'],
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'type' => OrderItem::TYPE_FIXED,
            'quantity' => $receivedQuantity,
            'collected_quantity' => $receivedQuantity,
            'received_at_plant_quantity' => $receivedQuantity,
            'washing_quantity' => 0,
            'prepared_quantity' => 0,
            'damaged_quantity' => 0,
            'loaded_quantity' => 0,
            'status' => OrderItem::STATUS_TRANSPORTING_TO_PLANT,
            'unit_price' => 20.0,
        ]);

        return [$order, $item];
    }

    public function test_operator_and_admin_can_access_production_index_but_driver_cannot(): void
    {
        // Operador puede ver la pantalla
        $response = $this->actingAs($this->operatorUser)->get(route('planta.production.index'));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Planta/Production/Index')
            ->has('state')
            ->where('isOperator', true)
            ->where('isAdmin', false)
        );

        // Administrador puede ver la pantalla en modo supervisión
        $responseAdmin = $this->actingAs($this->adminUser)->get(route('planta.production.index'));
        $responseAdmin->assertStatus(200);
        $responseAdmin->assertInertia(fn ($page) => $page->component('Planta/Production/Index')
            ->where('isOperator', false)
            ->where('isAdmin', true)
        );

        // Repartidor no tiene acceso
        $responseDriver = $this->actingAs($this->driverUser)->get(route('planta.production.index'));
        $responseDriver->assertStatus(403);
    }

    public function test_admin_cannot_execute_physical_production_operations(): void
    {
        [$order, $item] = $this->createOrderWithFixedReceived(5);

        // Administrador intenta iniciar lavado
        $response = $this->actingAs($this->adminUser)->post(route('planta.production.fixed.start'), [
            'order_item_id' => $item->id,
            'quantity' => 3,
        ]);

        $response->assertStatus(403);
        $this->assertEquals(0, $item->fresh()->washing_quantity);

        // Administrador intenta iniciar lote de cambio
        $responseBatch = $this->actingAs($this->adminUser)->post(route('planta.production.exchange.start'), [
            'quantity' => 10,
        ]);

        $responseBatch->assertStatus(403);
    }

    public function test_fixed_washing_initiation_and_partial_progression(): void
    {
        [$order, $item] = $this->createOrderWithFixedReceived(5);

        // Iniciar lavado parcial: 3 de 5
        $response = $this->actingAs($this->operatorUser)->post(route('planta.production.fixed.start'), [
            'order_item_id' => $item->id,
            'quantity' => 3,
        ]);

        $response->assertRedirect();
        $item->refresh();

        $this->assertEquals(3, $item->washing_quantity);
        $this->assertEquals(OrderItem::STATUS_WASHING, $item->status);

        // Verificar movimiento de custodia
        $this->assertDatabaseHas('bottle_custody_movements', [
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'movement_type' => BottleCustodyMovement::TYPE_PLANT_WASHING_STARTED,
            'bottle_type' => OrderItem::TYPE_FIXED,
            'quantity' => 3,
        ]);

        // Intentar lavar más de los restantes (quedan 2 pendientes, intentamos lavar 3)
        $responseOverflow = $this->actingAs($this->operatorUser)->post(route('planta.production.fixed.start'), [
            'order_item_id' => $item->id,
            'quantity' => 3,
        ]);

        $responseOverflow->assertSessionHasErrors('fixed');
        $this->assertEquals(3, $item->fresh()->washing_quantity);

        // Lavar los 2 restantes
        $this->actingAs($this->operatorUser)->post(route('planta.production.fixed.start'), [
            'order_item_id' => $item->id,
            'quantity' => 2,
        ]);

        $this->assertEquals(5, $item->fresh()->washing_quantity);
    }

    public function test_fixed_preparation_finalization_marks_ready_for_delivery_when_completed(): void
    {
        [$order, $item] = $this->createOrderWithFixedReceived(4);

        // Meter 4 a lavado
        $this->actingAs($this->operatorUser)->post(route('planta.production.fixed.start'), [
            'order_item_id' => $item->id,
            'quantity' => 4,
        ]);

        // Finalizar parcial: 2 de 4
        $responsePartial = $this->actingAs($this->operatorUser)->post(route('planta.production.fixed.finish'), [
            'order_item_id' => $item->id,
            'quantity' => 2,
        ]);

        $responsePartial->assertRedirect();
        $item->refresh();

        $this->assertEquals(2, $item->prepared_quantity);
        $this->assertEquals(OrderItem::STATUS_WASHING, $item->status); // Todavía hay 2 en lavado

        // Finalizar los 2 restantes
        $responseFinal = $this->actingAs($this->operatorUser)->post(route('planta.production.fixed.finish'), [
            'order_item_id' => $item->id,
            'quantity' => 2,
        ]);

        $responseFinal->assertRedirect();
        $item->refresh();

        $this->assertEquals(4, $item->prepared_quantity);
        $this->assertEquals(OrderItem::STATUS_READY_FOR_DELIVERY, $item->status); // Todos listos

        $this->assertDatabaseHas('bottle_custody_movements', [
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'movement_type' => BottleCustodyMovement::TYPE_PLANT_PREPARED_FIXED,
            'bottle_type' => OrderItem::TYPE_FIXED,
            'quantity' => 2,
        ]);
    }

    public function test_fixed_damage_during_washing_creates_incident_and_separates_from_aptos(): void
    {
        [$order, $item] = $this->createOrderWithFixedReceived(3);

        // Meter 3 a lavado
        $this->actingAs($this->operatorUser)->post(route('planta.production.fixed.start'), [
            'order_item_id' => $item->id,
            'quantity' => 3,
        ]);

        // Reportar 1 dañado en túnel
        $responseDamage = $this->actingAs($this->operatorUser)->post(route('planta.production.fixed.damage'), [
            'order_item_id' => $item->id,
            'quantity' => 1,
            'damage_type' => 'fuga_cuerpo',
            'notes' => 'Fisura en fondo al aplicar presión de llenado',
        ]);

        $responseDamage->assertRedirect();
        $item->refresh();

        $this->assertEquals(1, $item->damaged_quantity);
        $this->assertEquals(3, $item->received_at_plant_quantity); // Acumulado histórico de recepción intacto
        $this->assertEquals(3, $item->washing_quantity); // Acumulado histórico de ingreso a túnel intacto
        $this->assertEquals(2, $item->getInWashingQuantity()); // 2 unidades físicas restantes en túnel

        // Comprobar incidencia registrada
        $this->assertDatabaseHas('bottle_incidents', [
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'stage' => 'lavado',
            'damage_type' => 'fuga_cuerpo',
            'quantity' => 1,
            'reported_by_user_id' => $this->operatorUser->id,
            'status' => BottleIncident::STATUS_PENDING,
        ]);

        // Finalizar los 2 restantes aptos
        $this->actingAs($this->operatorUser)->post(route('planta.production.fixed.finish'), [
            'order_item_id' => $item->id,
            'quantity' => 2,
        ]);

        $item->refresh();
        $this->assertEquals(2, $item->prepared_quantity);
        $this->assertEquals(OrderItem::STATUS_READY_FOR_DELIVERY, $item->status);
    }

    public function test_exchange_batch_lifecycle_start_partial_finish_and_complete(): void
    {
        $inv = PlantInventory::first();
        $initialEmpty = $inv->exchange_empty_available; // 50
        $initialFull = $inv->exchange_full_available;   // 20

        // Iniciar un lote de 15 botellones
        $responseStart = $this->actingAs($this->operatorUser)->post(route('planta.production.exchange.start'), [
            'quantity' => 15,
            'notes' => 'Turno matutino',
        ]);

        $responseStart->assertRedirect();
        $inv->refresh();

        $this->assertEquals($initialEmpty - 15, $inv->exchange_empty_available);
        $this->assertEquals(15, $inv->exchange_washing_filling);
        $this->assertEquals($initialFull, $inv->exchange_full_available); // No se incrementó prematuramente

        $batch = PlantProductionBatch::where('status', PlantProductionBatch::STATUS_IN_PROCESS)->first();
        $this->assertNotNull($batch);
        $this->assertStringStartsWith('LOT-', $batch->batch_number);
        $this->assertEquals(15, $batch->initial_quantity);
        $this->assertEquals(0, $batch->finished_quantity);
        $this->assertEquals(15, $batch->getInProcessQuantity());

        // Finalizar parcial: 10 de 15
        $responseFinish1 = $this->actingAs($this->operatorUser)->post(route('planta.production.exchange.finish'), [
            'batch_id' => $batch->id,
            'quantity' => 10,
        ]);

        $responseFinish1->assertRedirect();
        $inv->refresh();
        $batch->refresh();

        $this->assertEquals(5, $inv->exchange_washing_filling);
        $this->assertEquals($initialFull + 10, $inv->exchange_full_available);
        $this->assertEquals(10, $batch->finished_quantity);
        $this->assertEquals(5, $batch->getInProcessQuantity());
        $this->assertEquals(PlantProductionBatch::STATUS_IN_PROCESS, $batch->status);

        // Finalizar los 5 restantes
        $responseFinish2 = $this->actingAs($this->operatorUser)->post(route('planta.production.exchange.finish'), [
            'batch_id' => $batch->id,
            'quantity' => 5,
        ]);

        $responseFinish2->assertRedirect();
        $inv->refresh();
        $batch->refresh();

        $this->assertEquals(0, $inv->exchange_washing_filling);
        $this->assertEquals($initialFull + 15, $inv->exchange_full_available);
        $this->assertEquals(15, $batch->finished_quantity);
        $this->assertEquals(0, $batch->getInProcessQuantity());
        $this->assertEquals(PlantProductionBatch::STATUS_COMPLETED, $batch->status);
        $this->assertNotNull($batch->completed_at);
    }

    public function test_exchange_batch_damage_reporting(): void
    {
        $inv = PlantInventory::first();
        $initialFull = $inv->exchange_full_available;

        // Iniciar un lote de 10 botellones
        $this->actingAs($this->operatorUser)->post(route('planta.production.exchange.start'), [
            'quantity' => 10,
        ]);

        $batch = PlantProductionBatch::where('status', PlantProductionBatch::STATUS_IN_PROCESS)->first();

        // Reportar 2 dañados en el lote
        $responseDamage = $this->actingAs($this->operatorUser)->post(route('planta.production.exchange.damage'), [
            'batch_id' => $batch->id,
            'quantity' => 2,
            'damage_type' => 'golpe_fondo',
            'notes' => 'Roto durante lavado automático',
        ]);

        $responseDamage->assertRedirect();
        $inv->refresh();
        $batch->refresh();

        $this->assertEquals(8, $inv->exchange_washing_filling); // 10 - 2 = 8
        $this->assertEquals($initialFull, $inv->exchange_full_available); // No se suma a llenos
        $this->assertEquals(2, $batch->damaged_quantity);
        $this->assertEquals(8, $batch->getInProcessQuantity());

        $this->assertDatabaseHas('bottle_incidents', [
            'bottle_type' => OrderItem::TYPE_EXCHANGE,
            'stage' => 'lavado',
            'damage_type' => 'golpe_fondo',
            'quantity' => 2,
            'reported_by_user_id' => $this->operatorUser->id,
        ]);

        // Finalizar los 8 restantes completa el lote
        $this->actingAs($this->operatorUser)->post(route('planta.production.exchange.finish'), [
            'batch_id' => $batch->id,
            'quantity' => 8,
        ]);

        $batch->refresh();
        $this->assertEquals(PlantProductionBatch::STATUS_COMPLETED, $batch->status);
        $this->assertEquals(8, $batch->finished_quantity);
        $this->assertEquals(2, $batch->damaged_quantity);
        $this->assertEquals(0, $batch->getInProcessQuantity());
    }
}
