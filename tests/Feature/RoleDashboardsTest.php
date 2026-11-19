<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PlantCounterRefill;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleDashboardsTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $driverUser;
    private User $plantaUser;
    private User $clientUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->adminUser = User::factory()->create(['status' => 'active']);
        $this->adminUser->assignRole('Administrador');

        $this->driverUser = User::factory()->create(['status' => 'active', 'name' => 'Chofer Prueba']);
        $this->driverUser->assignRole('Repartidor');

        $this->plantaUser = User::factory()->create(['status' => 'active', 'name' => 'Operador Prueba']);
        $this->plantaUser->assignRole('Operador Planta');

        $this->clientUser = User::factory()->create(['status' => 'active']);
        $this->clientUser->assignRole('Cliente');
    }

    public function test_dashboard_redirector_routes_repartidor_and_planta_to_their_dashboards(): void
    {
        $this->actingAs($this->driverUser)
            ->get(route('dashboard'))
            ->assertRedirect(route('repartidor.dashboard'));

        $this->actingAs($this->plantaUser)
            ->get(route('dashboard'))
            ->assertRedirect(route('planta.dashboard'));
    }

    public function test_repartidor_dashboard_loads_with_three_tab_counters_and_summary(): void
    {
        $customer = Customer::create([
            'code' => 'CLI-9001',
            'name' => 'Cliente Fijo Prueba',
            'nickname' => 'Tienda El Sol',
            'phone' => '9991112233',
            'has_account' => true,
            'fixed_bottles_owned' => 3,
            'status' => 'active',
        ]);

        // Pedido pendiente de recoger (Por recoger = 1)
        $orderCollect = Order::create([
            'folio' => 'PED-9001',
            'customer_id' => $customer->id,
            'created_by_user_id' => $this->adminUser->id,
            'recipient_name' => $customer->name,
            'recipient_phone' => $customer->phone,
            'address_snapshot' => 'Calle 20 #10, Centro, Hocabá',
            'status' => Order::STATUS_CONFIRMED,
            'total_bottles' => 2,
            'unit_price' => 17,
            'estimated_total' => 34,
        ]);
        OrderItem::create([
            'order_id' => $orderCollect->id,
            'type' => OrderItem::TYPE_FIXED,
            'quantity' => 2,
            'unit_price' => 17,
        ]);

        // Pedido listo en planta para subir al camión (Por subir = 1)
        $orderReadyPlant = Order::create([
            'folio' => 'PED-9002',
            'customer_id' => $customer->id,
            'created_by_user_id' => $this->adminUser->id,
            'recipient_name' => $customer->name,
            'recipient_phone' => $customer->phone,
            'address_snapshot' => 'Calle 20 #10, Centro, Hocabá',
            'status' => Order::STATUS_IN_PROCESS,
            'total_bottles' => 1,
            'unit_price' => 17,
            'estimated_total' => 17,
        ]);
        OrderItem::create([
            'order_id' => $orderReadyPlant->id,
            'type' => OrderItem::TYPE_FIXED,
            'quantity' => 1,
            'collected_quantity' => 1,
            'received_at_plant_quantity' => 1,
            'prepared_quantity' => 1,
            'loaded_quantity' => 0,
            'unit_price' => 17,
        ]);

        $this->actingAs($this->driverUser)
            ->get(route('repartidor.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Repartidor/Dashboard')
                ->where('counters.por_recoger', 1)
                ->where('counters.por_subir', 1)
                ->where('counters.por_entregar', 0)
                ->has('todaySummary')
                ->has('truckInventory')
            );

        // Administrador también puede inspeccionar el dashboard del Repartidor
        $this->actingAs($this->adminUser)
            ->get(route('repartidor.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Repartidor/Dashboard'));
    }

    public function test_planta_dashboard_loads_operational_kpis_and_enforces_roles(): void
    {
        PlantCounterRefill::create([
            'operator_id' => $this->plantaUser->id,
            'quantity' => 4,
            'unit_price' => 15,
            'total_amount' => 60,
        ]);

        $this->actingAs($this->plantaUser)
            ->get(route('planta.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Planta/Dashboard')
                ->where('quickRefill.bottles_today', 4)
                ->where('quickRefill.amount_today', 60)
                ->where('quickRefill.services_today', 1)
                ->has('fixedPipeline')
                ->has('exchange')
                ->has('alerts')
                ->has('recentMovements')
            );

        $this->actingAs($this->adminUser)
            ->get(route('planta.dashboard'))
            ->assertOk();

        $this->actingAs($this->clientUser)
            ->get(route('planta.dashboard'))
            ->assertForbidden();

        $this->actingAs($this->driverUser)
            ->get(route('planta.dashboard'))
            ->assertForbidden();
    }
}
