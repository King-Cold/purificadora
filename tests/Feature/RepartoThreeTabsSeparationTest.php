<?php

namespace Tests\Feature;

use App\Models\BottlePresentation;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TruckInventory;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RepartoThreeTabsSeparationTest extends TestCase
{
    use RefreshDatabase;

    protected User $driverUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

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

        $this->driverUser = User::factory()->create(['email' => 'repartidor@tabs.test', 'status' => 'active']);
        $this->driverUser->assignRole('Repartidor');

        TruckInventory::updateOrCreate(
            ['driver_user_id' => $this->driverUser->id],
            ['exchange_full_available' => 10, 'exchange_empty_collected' => 0]
        );
    }

    public function test_reparto_separates_por_recoger_por_subir_and_por_entregar(): void
    {
        $custCollect = Customer::create(['nickname' => 'Cliente Recoger', 'fixed_bottles_count' => 0, 'exchange_bottles_count' => 0]);
        $custPlant = Customer::create(['nickname' => 'Cliente En Planta', 'fixed_bottles_count' => 0, 'exchange_bottles_count' => 0]);
        $custTruck = Customer::create(['nickname' => 'Cliente En Camión', 'fixed_bottles_count' => 0, 'exchange_bottles_count' => 0]);

        // 1. Fijo por recoger en domicilio
        $orderCollect = Order::create([
            'folio' => 'PED-TAB-001',
            'customer_id' => $custCollect->id,
            'created_by_user_id' => $this->driverUser->id,
            'recipient_name' => 'Cliente Recoger',
            'address_snapshot' => ['delivery_zone' => 'north'],
            'status' => Order::STATUS_PENDING,
        ]);
        OrderItem::create([
            'order_id' => $orderCollect->id,
            'type' => OrderItem::TYPE_FIXED,
            'quantity' => 2,
            'collected_quantity' => 0,
            'prepared_quantity' => 0,
            'loaded_quantity' => 0,
            'delivered_quantity' => 0,
        ]);

        // 2. Fijo ya llenado en Planta, listo para subir al camión
        $orderPlant = Order::create([
            'folio' => 'PED-TAB-002',
            'customer_id' => $custPlant->id,
            'created_by_user_id' => $this->driverUser->id,
            'recipient_name' => 'Cliente En Planta',
            'address_snapshot' => ['delivery_zone' => 'north'],
            'status' => Order::STATUS_IN_PROCESS,
        ]);
        OrderItem::create([
            'order_id' => $orderPlant->id,
            'type' => OrderItem::TYPE_FIXED,
            'quantity' => 3,
            'collected_quantity' => 3,
            'received_at_plant_quantity' => 3,
            'prepared_quantity' => 3,
            'loaded_quantity' => 0,
            'delivered_quantity' => 0,
        ]);

        // 3. Fijo ya subido al camión listo para entregar
        $orderTruck = Order::create([
            'folio' => 'PED-TAB-003',
            'customer_id' => $custTruck->id,
            'created_by_user_id' => $this->driverUser->id,
            'recipient_name' => 'Cliente En Camión',
            'address_snapshot' => ['delivery_zone' => 'north'],
            'status' => Order::STATUS_IN_PROCESS,
        ]);
        OrderItem::create([
            'order_id' => $orderTruck->id,
            'type' => OrderItem::TYPE_FIXED,
            'quantity' => 4,
            'collected_quantity' => 4,
            'received_at_plant_quantity' => 4,
            'prepared_quantity' => 4,
            'loaded_quantity' => 4,
            'delivered_quantity' => 0,
        ]);

        // Verificar pestaña Por recoger
        $this->actingAs($this->driverUser)->get(route('repartidor.reparto', ['tab' => 'por_recoger']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tabCounts.por_recoger', 1)
                ->where('tabCounts.por_recoger_planta', 1)
                ->where('tabCounts.por_entregar', 1)
                ->has('items', 1)
                ->where('items.0.id', $orderCollect->id)
            );

        // Verificar pestaña Por subir (en Planta)
        $this->actingAs($this->driverUser)->get(route('repartidor.reparto', ['tab' => 'por_recoger_planta']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('activeTab', 'por_recoger_planta')
                ->has('items', 1)
                ->where('items.0.id', $orderPlant->id)
            );

        // Verificar pestaña Por entregar (solo en camión, SIN el de Planta)
        $this->actingAs($this->driverUser)->get(route('repartidor.reparto', ['tab' => 'por_entregar']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('activeTab', 'por_entregar')
                ->has('items', 1)
                ->where('items.0.id', $orderTruck->id)
            );
    }
}
