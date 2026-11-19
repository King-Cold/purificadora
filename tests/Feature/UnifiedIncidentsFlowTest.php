<?php

namespace Tests\Feature;

use App\Models\BottleIncident;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PlantInventory;
use App\Models\TruckInventory;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnifiedIncidentsFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $plantaUser;
    private User $driverUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->adminUser = User::factory()->create(['status' => 'active']);
        $this->adminUser->assignRole('Administrador');

        $this->plantaUser = User::factory()->create(['status' => 'active']);
        $this->plantaUser->assignRole('Operador Planta');

        $this->driverUser = User::factory()->create(['status' => 'active']);
        $this->driverUser->assignRole('Repartidor');
    }

    public function test_planta_inventory_incidents_and_history_are_separate_pages(): void
    {
        $this->actingAs($this->plantaUser)
            ->get(route('planta.inventory.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Planta/Inventory/Index'));

        $this->actingAs($this->plantaUser)
            ->get(route('planta.incidents.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Planta/Incidents/Index'));

        $this->actingAs($this->plantaUser)
            ->get(route('planta.history.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Planta/History/Index')
                ->has('movements')
            );
    }

    public function test_unified_exchange_and_fixed_incident_reporting_and_admin_resolution(): void
    {
        PlantInventory::create([
            'id' => 1,
            'exchange_empty_available' => 10,
            'exchange_washing_filling' => 0,
            'exchange_full_available' => 5,
            'exchange_full_reserved' => 0,
            'initial_count_completed_at' => now(),
            'initial_count_user_id' => $this->plantaUser->id,
            'initial_empty_count' => 10,
            'initial_full_count' => 5,
        ]);

        TruckInventory::create([
            'driver_user_id' => $this->driverUser->id,
            'exchange_full_available' => 6,
            'exchange_empty_collected' => 0,
        ]);

        $customer = Customer::create([
            'code' => 'CLI-9100',
            'name' => 'Cliente Fijo Prueba',
            'nickname' => 'Doña Lupita',
            'phone' => '9991234567',
            'has_account' => true,
            'fixed_bottles_count' => 4,
            'status' => 'active',
        ]);

        $order = Order::create([
            'folio' => 'PED-9100',
            'customer_id' => $customer->id,
            'created_by_user_id' => $this->adminUser->id,
            'recipient_name' => $customer->name,
            'recipient_phone' => $customer->phone,
            'address_snapshot' => 'Calle 21 #15, Centro, Hocabá',
            'status' => Order::STATUS_IN_PROCESS,
            'total_bottles' => 2,
            'unit_price' => 17,
            'estimated_total' => 34,
        ]);

        $fixedItem = OrderItem::create([
            'order_id' => $order->id,
            'type' => OrderItem::TYPE_FIXED,
            'quantity' => 2,
            'collected_quantity' => 2,
            'received_at_plant_quantity' => 2,
            'unit_price' => 17,
        ]);

        // 1. Planta reporta 1 Cambio dañado en vacíos de Planta
        $this->actingAs($this->plantaUser)
            ->post(route('planta.inventory.incidents.store'), [
                'bottle_type' => 'cambio',
                'quantity' => 1,
                'stage' => 'almacen_vacio',
                'bottle_condition' => 'empty',
                'location_status' => 'separado',
                'damage_type' => 'rotura',
                'notes' => 'Se rompió el fondo al mover tarima',
            ])
            ->assertRedirect(route('planta.incidents.index'));

        $inv = PlantInventory::find(1);
        $this->assertSame(9, (int) $inv->exchange_empty_available);

        // 2. Planta reporta 1 Cambio sin localizar en Camión
        $this->actingAs($this->plantaUser)
            ->post(route('planta.inventory.incidents.store'), [
                'bottle_type' => 'cambio',
                'quantity' => 1,
                'stage' => 'en_camion',
                'bottle_condition' => 'full',
                'location_status' => 'sin_localizar',
                'damage_type' => 'otro',
                'notes' => 'Faltó un garrafón al cuadrar ruta',
            ])
            ->assertRedirect(route('planta.incidents.index'));

        $inv->refresh();
        $truck = TruckInventory::where('driver_user_id', $this->driverUser->id)->first();
        $this->assertSame(5, (int) $truck->exchange_full_available);
        $this->assertSame(1, (int) $inv->exchange_unlocated);

        // 3. Planta reporta 1 Fijo dañado del cliente Doña Lupita
        $this->actingAs($this->plantaUser)
            ->post(route('planta.inventory.incidents.store'), [
                'bottle_type' => 'fijo',
                'customer_id' => $customer->id,
                'order_item_id' => $fixedItem->id,
                'quantity' => 1,
                'stage' => 'recepcion',
                'damage_type' => 'fisura',
                'notes' => 'Botellón fijo llegó con fisura lateral',
            ])
            ->assertRedirect(route('planta.incidents.index'));

        $fixedItem->refresh();
        $customer->refresh();
        $this->assertSame(1, (int) $fixedItem->damaged_quantity);
        $this->assertSame(4, (int) $customer->fixed_bottles_count);

        $exchangeIncident = BottleIncident::where('bottle_type', 'cambio')->firstOrFail();
        $fixedIncident = BottleIncident::where('bottle_type', 'fijo')->firstOrFail();

        // 4. Administrador ve tanto Cambios como Fijos en Admin -> Cambios
        $this->actingAs($this->adminUser)
            ->get(route('admin.exchange-inventory.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/ExchangeInventory/Index')
                ->has('pendingIncidents', 1)
                ->has('pendingFixedIncidents', 1)
                ->where('summary.unlocated_in_review', 1)
            );

        // 5. Admin resuelve Cambio (recuperación a llenos en Planta), sin localizar (baja) y Fijo (aclarado)
        $this->actingAs($this->adminUser)
            ->post(route('admin.exchange-inventory.incidents.resolve', $exchangeIncident->id), [
                'resolution_type' => 'recuperacion',
                'recovery_location' => 'plant_full',
                'notes' => 'Estaba intacto, era etiqueta raspada',
            ])
            ->assertRedirect();

        $this->actingAs($this->adminUser)
            ->post(route('admin.exchange-inventory.unlocated.resolve'), [
                'quantity' => 1,
                'resolution_type' => 'baja',
                'notes' => 'No apareció tras revisión',
            ])
            ->assertRedirect();

        $this->actingAs($this->adminUser)
            ->post(route('admin.exchange-inventory.incidents.resolve', $fixedIncident->id), [
                'resolution_type' => 'aclarado',
                'notes' => 'Cliente llevó repuesto propio',
            ])
            ->assertRedirect();

        $inv->refresh();
        $exchangeIncident->refresh();
        $fixedIncident->refresh();
        $this->assertSame(6, (int) $inv->exchange_full_available);
        $this->assertSame(0, (int) $inv->exchange_unlocated);
        $this->assertSame(BottleIncident::STATUS_RESOLVED, $exchangeIncident->status);
        $this->assertSame(BottleIncident::STATUS_RESOLVED, $fixedIncident->status);
    }
}
