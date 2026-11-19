<?php

namespace Tests\Feature;

use App\Models\BottleCustodyMovement;
use App\Models\PlantInventory;
use App\Models\TruckInventory;
use App\Models\User;
use App\Services\SettingService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExchangeInventoryRecountTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $plantaUser;
    private User $driverUser;
    private User $clientUser;

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

        $this->clientUser = User::factory()->create(['status' => 'active']);
        $this->clientUser->assignRole('Cliente');
    }

    public function test_admin_can_add_new_bottles_and_sync_target(): void
    {
        app(SettingService::class)->setExchangeBottlesTarget(30, $this->adminUser->name);

        PlantInventory::create([
            'id' => 1,
            'exchange_empty_available' => 10,
            'exchange_washing_filling' => 0,
            'exchange_full_available' => 10,
            'exchange_full_reserved' => 0,
            'initial_count_completed_at' => now(),
            'initial_count_user_id' => $this->plantaUser->id,
            'initial_empty_count' => 10,
            'initial_full_count' => 10,
        ]);

        $this->actingAs($this->adminUser)
            ->post(route('admin.exchange-inventory.new-bottles.store'), [
                'quantity' => 2,
                'bottle_condition' => 'empty',
                'reason' => 'Compra de lote nuevo',
                'sync_target' => true,
            ])
            ->assertRedirect();

        $inv = PlantInventory::find(1);
        $this->assertSame(12, (int) $inv->exchange_empty_available);
        $this->assertSame(32, app(SettingService::class)->getExchangeBottlesTarget());

        $this->assertDatabaseHas('bottle_custody_movements', [
            'movement_type' => BottleCustodyMovement::TYPE_EXCHANGE_NEW_ENTRY,
            'quantity' => 2,
        ]);
    }

    public function test_admin_and_planta_can_adjust_or_reset_plant_and_truck_while_only_admin_changes_target(): void
    {
        app(SettingService::class)->setExchangeBottlesTarget(40, $this->adminUser->name);

        PlantInventory::create([
            'id' => 1,
            'exchange_empty_available' => 12,
            'exchange_washing_filling' => 0,
            'exchange_full_available' => 8,
            'exchange_full_reserved' => 0,
            'initial_count_completed_at' => now()->subDay(),
            'initial_count_user_id' => $this->plantaUser->id,
            'initial_empty_count' => 12,
            'initial_full_count' => 8,
        ]);

        TruckInventory::create([
            'driver_user_id' => $this->driverUser->id,
            'exchange_full_available' => 15,
            'exchange_empty_collected' => 5,
        ]);

        // 1. Admin ajusta hacia abajo tanto Planta como Camión y sincroniza Meta
        $this->actingAs($this->adminUser)
            ->post(route('admin.exchange-inventory.reconcile'), [
                'empty_count' => 10,
                'full_count' => 5,
                'truck_full_count' => 8,
                'truck_empty_count' => 2,
                'reason' => 'Ajuste de inventario físico',
                'sync_target' => true,
            ])
            ->assertRedirect();

        $inv = PlantInventory::find(1);
        $truck = TruckInventory::where('driver_user_id', $this->driverUser->id)->first();
        $this->assertSame(10, (int) $inv->exchange_empty_available);
        $this->assertSame(5, (int) $inv->exchange_full_available);
        $this->assertSame(8, (int) $truck->exchange_full_available);
        $this->assertSame(2, (int) $truck->exchange_empty_collected);
        // 15 + 10 = 25 -> Meta sincronizada por Admin
        $this->assertSame(25, app(SettingService::class)->getExchangeBottlesTarget());

        // 2. Operador de Planta también puede reiniciar / ajustar Planta y Camión (incluso a 0), pero NO cambia la Meta
        $this->actingAs($this->plantaUser)
            ->post(route('planta.inventory.reconcile'), [
                'empty_count' => 6,
                'full_count' => 4,
                'truck_full_count' => 3,
                'truck_empty_count' => 1,
                'reason' => 'Reinicio total de conteo',
                'sync_target' => true, // debe ignorarse para Operador Planta
            ])
            ->assertRedirect();

        $inv->refresh();
        $truck->refresh();
        $this->assertSame(6, (int) $inv->exchange_empty_available);
        $this->assertSame(4, (int) $inv->exchange_full_available);
        $this->assertSame(3, (int) $truck->exchange_full_available);
        $this->assertSame(1, (int) $truck->exchange_empty_collected);
        // Meta intacta en 25 (solo Admin puede cambiar Meta)
        $this->assertSame(25, app(SettingService::class)->getExchangeBottlesTarget());

        // 3. Existencias oculta Meta al Operador Planta pero muestra el total real (10 + 4 = 14)
        $this->actingAs($this->plantaUser)
            ->get(route('planta.inventory.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Planta/Inventory/Index')
                ->where('canEditTarget', false)
                ->where('inventoryState.balances.plant_active_exchange', 10)
                ->where('inventoryState.balances.truck_exchange_total', 4)
                ->where('inventoryState.balances.circuit_active_total', 14)
            );
    }

    public function test_unauthorized_roles_cannot_reconcile_inventory(): void
    {
        $payload = [
            'empty_count' => 5,
            'full_count' => 5,
            'reason' => 'Intento no autorizado',
        ];

        $this->actingAs($this->clientUser)
            ->post(route('planta.inventory.reconcile'), $payload)
            ->assertForbidden();

        $this->actingAs($this->driverUser)
            ->post(route('planta.inventory.reconcile'), $payload)
            ->assertForbidden();

        $this->actingAs($this->plantaUser)
            ->post(route('admin.exchange-inventory.reconcile'), $payload)
            ->assertForbidden();
    }
}
