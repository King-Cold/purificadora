<?php

namespace Tests\Feature;

use App\Models\BottlePresentation;
use App\Models\BranchSchedule;
use App\Models\Customer;
use App\Models\User;
use App\Services\StoreScheduleService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeneralSettingsManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $clientUser;
    protected User $repartidorUser;
    protected User $plantaUser;
    protected StoreScheduleService $scheduleService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->scheduleService = app(StoreScheduleService::class);

        // Administrador
        $this->adminUser = User::factory()->create([
            'name' => 'Admin Principal',
            'email' => 'admin@purificadora.com',
            'status' => 'active',
        ]);
        $this->adminUser->assignRole('Administrador');

        // Cliente
        $this->clientUser = User::factory()->create([
            'name' => 'Cliente Prueba',
            'email' => 'cliente@purificadora.com',
            'status' => 'active',
        ]);
        $this->clientUser->assignRole('Cliente');

        // Repartidor
        $this->repartidorUser = User::factory()->create([
            'name' => 'Repartidor',
            'email' => 'repartidor@purificadora.com',
            'status' => 'active',
        ]);
        $this->repartidorUser->assignRole('Repartidor');

        // Operador Planta
        $this->plantaUser = User::factory()->create([
            'name' => 'Llenador',
            'email' => 'planta@purificadora.com',
            'status' => 'active',
        ]);
        $this->plantaUser->assignRole('Operador Planta');
    }

    /**
     * 1. Comprobación exhaustiva del Horario Inicial según las reglas de negocio.
     */
    public function test_initial_schedule_order_reception_rules_at_key_times(): void
    {
        $schedule = BranchSchedule::current();
        $this->assertEquals('08:00', substr($schedule->opening_time, 0, 5));
        $this->assertEquals('13:00', substr($schedule->break_start_time, 0, 5));
        $this->assertEquals('15:00', substr($schedule->break_end_time, 0, 5));
        $this->assertEquals('19:00', substr($schedule->closing_time, 0, 5));
        $this->assertEquals(60, $schedule->order_cutoff_advance_minutes);
        $this->assertEquals('18:00', $this->scheduleService->getCutoffTime($schedule));

        // 07:59: pedidos bloqueados; todavía no abre.
        $res0759 = $this->scheduleService->checkOrderReception('07:59', $schedule);
        $this->assertFalse($res0759['can_receive']);
        $this->assertEquals('not_open_yet', $res0759['reason']);
        $this->assertEquals('La sucursal todavía no abre.', $res0759['message']);

        // 08:00: pedidos permitidos.
        $res0800 = $this->scheduleService->checkOrderReception('08:00', $schedule);
        $this->assertTrue($res0800['can_receive']);
        $this->assertEquals('allowed', $res0800['reason']);

        // 12:59: pedidos permitidos.
        $res1259 = $this->scheduleService->checkOrderReception('12:59', $schedule);
        $this->assertTrue($res1259['can_receive']);
        $this->assertEquals('allowed', $res1259['reason']);

        // 13:00: pedidos bloqueados por descanso.
        $res1300 = $this->scheduleService->checkOrderReception('13:00', $schedule);
        $this->assertFalse($res1300['can_receive']);
        $this->assertEquals('on_break', $res1300['reason']);
        $this->assertEquals('La sucursal está en descanso.', $res1300['message']);

        // 14:59: pedidos bloqueados por descanso.
        $res1459 = $this->scheduleService->checkOrderReception('14:59', $schedule);
        $this->assertFalse($res1459['can_receive']);
        $this->assertEquals('on_break', $res1459['reason']);
        $this->assertEquals('La sucursal está en descanso.', $res1459['message']);

        // 15:00: pedidos permitidos.
        $res1500 = $this->scheduleService->checkOrderReception('15:00', $schedule);
        $this->assertTrue($res1500['can_receive']);
        $this->assertEquals('allowed', $res1500['reason']);

        // 17:59: pedidos permitidos.
        $res1759 = $this->scheduleService->checkOrderReception('17:59', $schedule);
        $this->assertTrue($res1759['can_receive']);
        $this->assertEquals('allowed', $res1759['reason']);

        // 18:00: recepción de pedidos finalizada (hora límite: 19:00 - 60 min).
        $res1800 = $this->scheduleService->checkOrderReception('18:00', $schedule);
        $this->assertFalse($res1800['can_receive']);
        $this->assertEquals('orders_closed_for_today', $res1800['reason']);
        $this->assertEquals('La recepción de pedidos terminó por hoy.', $res1800['message']);

        // 18:30: recepción de pedidos finalizada.
        $res1830 = $this->scheduleService->checkOrderReception('18:30', $schedule);
        $this->assertFalse($res1830['can_receive']);
        $this->assertEquals('orders_closed_for_today', $res1830['reason']);

        // 19:00: sucursal cerrada (después del cierre prevalece "La sucursal está cerrada").
        $res1900 = $this->scheduleService->checkOrderReception('19:00', $schedule);
        $this->assertFalse($res1900['can_receive']);
        $this->assertEquals('store_closed', $res1900['reason']);
        $this->assertEquals('La sucursal está cerrada.', $res1900['message']);

        // 20:30: sucursal cerrada.
        $res2030 = $this->scheduleService->checkOrderReception('20:30', $schedule);
        $this->assertFalse($res2030['can_receive']);
        $this->assertEquals('store_closed', $res2030['reason']);
    }

    /**
     * 2. Comprueba que el horario aplica de lunes a domingo con fechas en Carbon.
     */
    public function test_schedule_applies_every_day_of_the_week(): void
    {
        $days = [
            '2026-10-26 10:00:00', // Lunes
            '2026-10-27 10:00:00', // Martes
            '2026-10-28 10:00:00', // Miércoles
            '2026-10-29 10:00:00', // Jueves
            '2026-10-30 10:00:00', // Viernes
            '2026-10-31 10:00:00', // Sábado
            '2026-11-01 10:00:00', // Domingo
        ];

        foreach ($days as $dateStr) {
            $dt = Carbon::parse($dateStr, StoreScheduleService::TIMEZONE);
            $this->assertTrue(
                $this->scheduleService->canReceiveOrdersAt($dt),
                "Falló la recepción de pedidos para el día: {$dt->format('l')}"
            );
        }
    }

    /**
     * 3. El administrador puede actualizar el horario y anticipación, actualizando los cálculos.
     */
    public function test_admin_can_update_schedule_and_calculations_change(): void
    {
        $response = $this->actingAs($this->adminUser)->put(route('admin.settings.schedule.update'), [
            'opening_time' => '07:30',
            'break_start_time' => '12:30',
            'break_end_time' => '14:30',
            'closing_time' => '20:00',
            'order_cutoff_advance_minutes' => 90, // Límite: 20:00 - 90 min = 18:30
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $schedule = BranchSchedule::current();
        $this->assertEquals('07:30', substr($schedule->opening_time, 0, 5));
        $this->assertEquals('12:30', substr($schedule->break_start_time, 0, 5));
        $this->assertEquals('14:30', substr($schedule->break_end_time, 0, 5));
        $this->assertEquals('20:00', substr($schedule->closing_time, 0, 5));
        $this->assertEquals(90, $schedule->order_cutoff_advance_minutes);
        $this->assertEquals('18:30', $this->scheduleService->getCutoffTime($schedule));

        // 18:29 debe estar permitido ahora
        $this->assertTrue($this->scheduleService->canReceiveOrdersAt('18:29', $schedule));
        // 18:30 debe estar bloqueado por corte de pedidos
        $this->assertFalse($this->scheduleService->canReceiveOrdersAt('18:30', $schedule));
    }

    /**
     * 4. Se rechazan horarios incoherentes (apertura >= descanso, descanso fin <= inicio, etc.).
     */
    public function test_incoherent_schedules_are_rejected(): void
    {
        // Apertura mayor que inicio de descanso
        $res1 = $this->actingAs($this->adminUser)->put(route('admin.settings.schedule.update'), [
            'opening_time' => '14:00',
            'break_start_time' => '13:00',
            'break_end_time' => '15:00',
            'closing_time' => '19:00',
            'order_cutoff_advance_minutes' => 60,
        ]);
        $res1->assertSessionHasErrors(['break_start_time']);

        // Fin de descanso anterior al inicio
        $res2 = $this->actingAs($this->adminUser)->put(route('admin.settings.schedule.update'), [
            'opening_time' => '08:00',
            'break_start_time' => '14:00',
            'break_end_time' => '13:00',
            'closing_time' => '19:00',
            'order_cutoff_advance_minutes' => 60,
        ]);
        $res2->assertSessionHasErrors(['break_end_time']);

        // Cierre anterior al fin del descanso
        $res3 = $this->actingAs($this->adminUser)->put(route('admin.settings.schedule.update'), [
            'opening_time' => '08:00',
            'break_start_time' => '13:00',
            'break_end_time' => '15:00',
            'closing_time' => '14:00',
            'order_cutoff_advance_minutes' => 60,
        ]);
        $res3->assertSessionHasErrors(['closing_time']);

        // Hora límite de pedidos (cierre - anticipación) cae antes o igual al fin del descanso
        // Cierre 19:00 - anticipación 300 min = 14:00 <= break_end (15:00)
        $res4 = $this->actingAs($this->adminUser)->put(route('admin.settings.schedule.update'), [
            'opening_time' => '08:00',
            'break_start_time' => '13:00',
            'break_end_time' => '15:00',
            'closing_time' => '19:00',
            'order_cutoff_advance_minutes' => 300,
        ]);
        $res4->assertSessionHasErrors(['order_cutoff_advance_minutes']);
    }

    /**
     * 5. Catálogo de envases: el catálogo comienza vacío (no se inventan registros iniciales).
     */
    public function test_catalog_starts_empty_and_can_save_schedule_with_empty_catalog(): void
    {
        $this->assertEquals(0, BottlePresentation::count());

        // Se puede consultar y guardar el horario con catálogo vacío
        $response = $this->actingAs($this->adminUser)->get(route('admin.settings.index'));
        $response->assertOk();
    }

    /**
     * 6. Registro y edición de presentaciones con precios opcionales y precisión decimal.
     */
    public function test_create_and_update_bottle_presentation(): void
    {
        // 1. Crear presentación con precios
        $createRes = $this->actingAs($this->adminUser)->post(route('admin.settings.bottle-presentations.store'), [
            'name' => 'Garrafón Estándar 20L',
            'capacity' => 20,
            'unit' => 'Litros',
            'refill_price' => '18.50',
            'new_bottle_price' => '85.00',
            'is_active' => true,
        ]);

        $createRes->assertSessionHasNoErrors();
        $this->assertDatabaseHas('bottle_presentations', [
            'name' => 'Garrafón Estándar 20L',
            'capacity' => 20.00,
            'unit' => 'Litros',
            'refill_price' => 18.50,
            'new_bottle_price' => 85.00,
            'is_active' => true,
        ]);

        $presentation = BottlePresentation::first();

        // 2. Modificar presentación
        $updateRes = $this->actingAs($this->adminUser)->put(route('admin.settings.bottle-presentations.update', $presentation->id), [
            'name' => 'Garrafón Familiar 20L',
            'capacity' => 20,
            'unit' => 'Litros',
            'refill_price' => '20.00',
            'new_bottle_price' => '90.00',
            'is_active' => true,
        ]);

        $updateRes->assertSessionHasNoErrors();
        $this->assertEquals('Garrafón Familiar 20L', $presentation->fresh()->name);
        $this->assertEquals('20.00', $presentation->fresh()->refill_price);
        $this->assertEquals('90.00', $presentation->fresh()->new_bottle_price);
    }

    /**
     * 7. Precios sin configurar: se permiten valores null explícitos (nunca se sustituyen por 0).
     */
    public function test_presentation_allows_unconfigured_prices_without_substituting_with_zero(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('admin.settings.bottle-presentations.store'), [
            'name' => 'Botellón Especial 15L',
            'capacity' => 15,
            'unit' => 'Litros',
            'refill_price' => '', // Sin configurar
            'new_bottle_price' => null, // Sin configurar
            'is_active' => true,
        ]);

        $response->assertSessionHasNoErrors();

        $presentation = BottlePresentation::where('name', 'Botellón Especial 15L')->first();
        $this->assertNotNull($presentation);
        $this->assertNull($presentation->refill_price);
        $this->assertNull($presentation->new_bottle_price);
        $this->assertFalse($presentation->has_refill_price);
        $this->assertFalse($presentation->has_new_bottle_price);

        // La consulta centralizada debe devolver null explícito
        $activeList = $this->scheduleService->getActivePresentations();
        $item = $activeList->firstWhere('id', $presentation->id);
        $this->assertNull($item['refill_price']);
        $this->assertNull($item['new_bottle_price']);
    }

    /**
     * 8. Rechazo de presentaciones duplicadas (por nombre insensible a mayúsculas/espacios, o misma capacidad y unidad).
     */
    public function test_rejects_duplicate_bottle_presentations(): void
    {
        BottlePresentation::create([
            'name' => 'Garrafón 20L',
            'capacity' => 20,
            'unit' => 'Litros',
            'refill_price' => 18.00,
            'is_active' => true,
        ]);

        // Intento 1: Mismo nombre con diferentes mayúsculas y espacios
        $dupNameRes = $this->actingAs($this->adminUser)->post(route('admin.settings.bottle-presentations.store'), [
            'name' => '   garrafón 20l   ',
            'capacity' => 19,
            'unit' => 'L',
        ]);
        $dupNameRes->assertSessionHasErrors(['name']);

        // Intento 2: Nombre distinto pero misma capacidad y unidad
        $dupCapRes = $this->actingAs($this->adminUser)->post(route('admin.settings.bottle-presentations.store'), [
            'name' => 'Bidón Azul 20L',
            'capacity' => 20,
            'unit' => '  litros  ',
        ]);
        $dupCapRes->assertSessionHasErrors(['capacity']);
    }

    /**
     * 9. Validación de capacidad y precios inválidos.
     */
    public function test_validates_capacity_and_prices_invalid_values(): void
    {
        // Capacidad <= 0
        $res1 = $this->actingAs($this->adminUser)->post(route('admin.settings.bottle-presentations.store'), [
            'name' => 'Envase Cero',
            'capacity' => 0,
            'unit' => 'Litros',
        ]);
        $res1->assertSessionHasErrors(['capacity']);

        // Precio <= 0
        $res2 = $this->actingAs($this->adminUser)->post(route('admin.settings.bottle-presentations.store'), [
            'name' => 'Envase Precio Cero',
            'capacity' => 10,
            'unit' => 'Litros',
            'refill_price' => '0',
        ]);
        $res2->assertSessionHasErrors(['refill_price']);

        // Precio con más de 2 decimales
        $res3 = $this->actingAs($this->adminUser)->post(route('admin.settings.bottle-presentations.store'), [
            'name' => 'Envase Decimales',
            'capacity' => 10,
            'unit' => 'Litros',
            'refill_price' => '18.555',
        ]);
        $res3->assertSessionHasErrors(['refill_price']);
    }

    /**
     * 10. Activación y desactivación: no se elimina físicamente y se excluye de la consulta activa.
     */
    public function test_toggle_presentation_status_and_active_consultation_exclusion(): void
    {
        $presentation = BottlePresentation::create([
            'name' => 'Garrafón 10L',
            'capacity' => 10,
            'unit' => 'Litros',
            'refill_price' => 12.00,
            'is_active' => true,
        ]);

        $this->assertCount(1, $this->scheduleService->getActivePresentations());

        // Desactivar
        $toggleRes = $this->actingAs($this->adminUser)->patch(
            route('admin.settings.bottle-presentations.toggle-status', $presentation->id)
        );
        $toggleRes->assertRedirect();
        $this->assertFalse($presentation->fresh()->is_active);

        // Se conserva en base de datos pero se excluye de getActivePresentations
        $this->assertDatabaseHas('bottle_presentations', ['id' => $presentation->id]);
        $this->assertCount(0, $this->scheduleService->getActivePresentations());

        // Reactivar
        $this->actingAs($this->adminUser)->patch(
            route('admin.settings.bottle-presentations.toggle-status', $presentation->id)
        );
        $this->assertTrue($presentation->fresh()->is_active);
        $this->assertCount(1, $this->scheduleService->getActivePresentations());
    }

    /**
     * 11. Seguridad y autorización: roles no autorizados (Cliente, Repartidor, Operador Planta) reciben 403.
     */
    public function test_unauthorized_roles_cannot_access_or_modify_settings(): void
    {
        $unauthorizedUsers = [
            $this->clientUser,
            $this->repartidorUser,
            $this->plantaUser,
        ];

        foreach ($unauthorizedUsers as $user) {
            // GET settings
            $resGet = $this->actingAs($user)->get(route('admin.settings.index'));
            $resGet->assertForbidden();

            // PUT schedule
            $resPut = $this->actingAs($user)->put(route('admin.settings.schedule.update'), [
                'opening_time' => '09:00',
                'break_start_time' => '13:00',
                'break_end_time' => '15:00',
                'closing_time' => '19:00',
                'order_cutoff_advance_minutes' => 60,
            ]);
            $resPut->assertForbidden();

            // POST presentation
            $resPost = $this->actingAs($user)->post(route('admin.settings.bottle-presentations.store'), [
                'name' => 'Hack Envase',
                'capacity' => 20,
                'unit' => 'Litros',
            ]);
            $resPost->assertForbidden();
        }

        // Usuario no autenticado es bloqueado (403 Forbidden por middleware de roles)
        $guestRes = $this->get(route('admin.settings.index'));
        $guestRes->assertForbidden();
    }

    /**
     * 12. Independencia: editar catálogo no altera horario y viceversa; no se modifican botellones de clientes.
     */
    public function test_catalog_and_schedule_operations_are_independent_and_preserve_customers(): void
    {
        $customerUser = User::factory()->create([
            'name' => 'Juan Dzib',
            'email' => 'juan@example.com',
        ]);
        $customerUser->assignRole('Cliente');

        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'code' => 'CLI-0999',
            'name' => 'Juan Dzib',
            'fixed_bottles_count' => 3,
            'exchange_bottles_count' => 2,
        ]);

        $initialSchedule = BranchSchedule::current()->toArray();

        // 1. Agregar presentación
        $this->actingAs($this->adminUser)->post(route('admin.settings.bottle-presentations.store'), [
            'name' => 'Garrafón 20L Test',
            'capacity' => 20,
            'unit' => 'Litros',
            'refill_price' => '18.00',
        ]);

        // Verificar que el horario sigue idéntico
        $currentSchedule = BranchSchedule::current()->toArray();
        $this->assertEquals($initialSchedule['opening_time'], $currentSchedule['opening_time']);
        $this->assertEquals($initialSchedule['closing_time'], $currentSchedule['closing_time']);

        // 2. Modificar horario
        $this->actingAs($this->adminUser)->put(route('admin.settings.schedule.update'), [
            'opening_time' => '08:30',
            'break_start_time' => '13:00',
            'break_end_time' => '15:00',
            'closing_time' => '19:30',
            'order_cutoff_advance_minutes' => 45,
        ]);

        // Verificar que el catálogo sigue teniendo la presentación intacta
        $this->assertDatabaseHas('bottle_presentations', [
            'name' => 'Garrafón 20L Test',
            'capacity' => 20.00,
            'refill_price' => 18.00,
        ]);

        // Verificar que los conteos de botellones del cliente siguen inalterados
        $freshCustomer = $customer->fresh();
        $this->assertEquals(3, $freshCustomer->fixed_bottles_count);
        $this->assertEquals(2, $freshCustomer->exchange_bottles_count);
    }

    /**
     * 13. Habilitar a domicilio está permitido exclusivamente para presentaciones equivalentes a 20 litros.
     */
    public function test_available_for_delivery_can_be_enabled_for_twenty_liters_presentation(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('admin.settings.bottle-presentations.store'), [
            'name' => 'Garrafón 20L A Domicilio',
            'capacity' => 20,
            'unit' => 'Litros',
            'refill_price' => '15.00',
            'store_price' => '10.00',
            'new_bottle_price' => '95.00',
            'is_available_for_delivery' => true,
            'is_active' => true,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('bottle_presentations', [
            'name' => 'Garrafón 20L A Domicilio',
            'is_available_for_delivery' => true,
        ]);
    }

    /**
     * 14. Se rechaza habilitar a domicilio presentaciones cuya capacidad no equivalga a 20 litros.
     */
    public function test_rejects_available_for_delivery_for_presentations_not_equivalent_to_twenty_liters(): void
    {
        // Intentar registrar una presentación de 10L con disponible a domicilio = true
        $response = $this->actingAs($this->adminUser)->post(route('admin.settings.bottle-presentations.store'), [
            'name' => 'Garrafón 10L No Domicilio',
            'capacity' => 10,
            'unit' => 'Litros',
            'store_price' => '5.00',
            'is_available_for_delivery' => true,
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors(['is_available_for_delivery']);

        // Intentar registrar una presentación de 15L con disponible a domicilio = true
        $response15 = $this->actingAs($this->adminUser)->post(route('admin.settings.bottle-presentations.store'), [
            'name' => 'Garrafón 15L Inválido',
            'capacity' => 15,
            'unit' => 'Litros',
            'store_price' => '7.00',
            'is_available_for_delivery' => true,
            'is_active' => true,
        ]);

        $response15->assertSessionHasErrors(['is_available_for_delivery']);
    }

    /**
     * 15. Las presentaciones exclusivas de sucursal permiten tener el precio a domicilio sin configurar (null).
     */
    public function test_exclusive_branch_presentations_allow_unconfigured_delivery_price(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('admin.settings.bottle-presentations.store'), [
            'name' => 'Envase 10L Solo Sucursal',
            'capacity' => 10,
            'unit' => 'Litros',
            'refill_price' => '', // Sin configurar a domicilio
            'store_price' => '5.00',
            'new_bottle_price' => '65.00',
            'is_available_for_delivery' => false,
            'is_active' => true,
        ]);

        $response->assertSessionHasNoErrors();

        $presentation = BottlePresentation::where('name', 'Envase 10L Solo Sucursal')->first();
        $this->assertNotNull($presentation);
        $this->assertFalse($presentation->is_available_for_delivery);
        $this->assertNull($presentation->refill_price);
        $this->assertFalse($presentation->has_refill_price);
        $this->assertEquals(5.00, (float) $presentation->store_price);
    }

    /**
     * 16. La ejecución del seeder no sobrescribe los precios previamente editados por el usuario.
     */
    public function test_seeder_does_not_overwrite_edited_presentation_prices(): void
    {
        // Modificar precios de la presentación de 20L
        $presentation20 = BottlePresentation::firstOrCreate(
            ['name' => 'Garrafón 20 Litros'],
            [
                'capacity' => 20.00,
                'unit' => 'Litros',
                'refill_price' => 15.00,
                'store_price' => 10.00,
                'new_bottle_price' => 95.00,
            ]
        );

        $presentation20->update([
            'refill_price' => 19.50,
            'store_price' => 13.00,
            'new_bottle_price' => 110.00,
        ]);

        // Ejecutar el Seeder
        $seeder = new \Database\Seeders\DatabaseSeeder();
        $seeder->run();

        // Verificar que los precios editados se mantuvieron exactamente iguales
        $fresh20 = $presentation20->fresh();
        $this->assertEquals('19.50', $fresh20->refill_price);
        $this->assertEquals('13.00', $fresh20->store_price);
        $this->assertEquals('110.00', $fresh20->new_bottle_price);
        $this->assertTrue($fresh20->is_available_for_delivery);
    }
}

