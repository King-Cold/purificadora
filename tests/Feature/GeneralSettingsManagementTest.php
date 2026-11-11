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
use LogicException;
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

        foreach ($days as $dateTimeStr) {
            Carbon::setTestNow(Carbon::parse($dateTimeStr, StoreScheduleService::TIMEZONE));
            $res = $this->scheduleService->checkOrderReception();
            $this->assertTrue($res['can_receive'], "Falló en día: {$dateTimeStr}");
            $this->assertEquals('allowed', $res['reason']);
        }

        Carbon::setTestNow();
    }

    /**
     * 3. Actualización de horario por el Administrador y recálculo automático de la hora límite.
     */
    public function test_admin_can_update_schedule_and_calculations_change(): void
    {
        $payload = [
            'opening_time' => '07:30',
            'break_start_time' => '12:30',
            'break_end_time' => '14:00',
            'closing_time' => '20:00',
            'order_cutoff_advance_minutes' => 90,
        ];

        $response = $this->actingAs($this->adminUser)->put(route('admin.settings.schedule.update'), $payload);
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $schedule = BranchSchedule::current();
        $this->assertEquals('07:30', substr($schedule->opening_time, 0, 5));
        $this->assertEquals('12:30', substr($schedule->break_start_time, 0, 5));
        $this->assertEquals('14:00', substr($schedule->break_end_time, 0, 5));
        $this->assertEquals('20:00', substr($schedule->closing_time, 0, 5));
        $this->assertEquals(90, $schedule->order_cutoff_advance_minutes);
        $this->assertEquals('18:30', $this->scheduleService->getCutoffTime($schedule));

        // 18:29: permitido (antes de 18:30)
        $res1829 = $this->scheduleService->checkOrderReception('18:29', $schedule);
        $this->assertTrue($res1829['can_receive']);

        // 18:30: bloqueado por hora límite
        $res1830 = $this->scheduleService->checkOrderReception('18:30', $schedule);
        $this->assertFalse($res1830['can_receive']);
        $this->assertEquals('orders_closed_for_today', $res1830['reason']);
    }

    /**
     * 4. Validación de coherencia en el horario: descansos invertidos, anticipación desfasada.
     */
    public function test_incoherent_schedules_are_rejected(): void
    {
        // Caso A: Descanso antes de apertura
        $resA = $this->actingAs($this->adminUser)->put(route('admin.settings.schedule.update'), [
            'opening_time' => '10:00',
            'break_start_time' => '09:00',
            'break_end_time' => '11:00',
            'closing_time' => '19:00',
            'order_cutoff_advance_minutes' => 60,
        ]);
        $resA->assertSessionHasErrors(['break_start_time']);

        // Caso B: Fin de descanso anterior a inicio de descanso
        $resB = $this->actingAs($this->adminUser)->put(route('admin.settings.schedule.update'), [
            'opening_time' => '08:00',
            'break_start_time' => '14:00',
            'break_end_time' => '13:00',
            'closing_time' => '19:00',
            'order_cutoff_advance_minutes' => 60,
        ]);
        $resB->assertSessionHasErrors(['break_end_time']);

        // Caso C: Cierre anterior o igual a fin de descanso
        $resC = $this->actingAs($this->adminUser)->put(route('admin.settings.schedule.update'), [
            'opening_time' => '08:00',
            'break_start_time' => '13:00',
            'break_end_time' => '15:00',
            'closing_time' => '15:00',
            'order_cutoff_advance_minutes' => 60,
        ]);
        $resC->assertSessionHasErrors(['closing_time']);

        // Caso D: Anticipación invade el periodo de descanso (cierre 19:00 - 300 min = 14:00 <= 15:00)
        $resD = $this->actingAs($this->adminUser)->put(route('admin.settings.schedule.update'), [
            'opening_time' => '08:00',
            'break_start_time' => '13:00',
            'break_end_time' => '15:00',
            'closing_time' => '19:00',
            'order_cutoff_advance_minutes' => 300,
        ]);
        $resD->assertSessionHasErrors(['order_cutoff_advance_minutes']);
    }

    /**
     * 5. Configuración de botellón: la vista de configuración provee el botellón único de 20 litros.
     */
    public function test_settings_index_provides_single_twenty_liters_presentation(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.settings.index'));
        $response->assertOk();

        $bottle = BottlePresentation::getTwentyLiters();
        $this->assertEquals(20.00, (float) $bottle->capacity);
        $this->assertEquals('Litros', $bottle->unit);
        $this->assertTrue($bottle->is_active);
    }

    /**
     * 6. Edición de precios y tarifas del botellón de 20 litros.
     */
    public function test_updating_twenty_liters_bottle_prices(): void
    {
        $bottle = BottlePresentation::getTwentyLiters();

        $updateRes = $this->actingAs($this->adminUser)->put(
            route('admin.settings.bottle-presentations.update', $bottle->id),
            [
                'name' => 'Botellón 20 Litros Oficial',
                'capacity' => 20,
                'unit' => 'Litros',
                'store_price' => '12.50',
                'refill_price' => '18.00',
                'new_bottle_price' => '95.00',
                'is_available_for_delivery' => true,
            ]
        );

        $updateRes->assertSessionHasNoErrors();
        $fresh = $bottle->fresh();
        $this->assertEquals('Botellón 20 Litros Oficial', $fresh->name);
        $this->assertEquals('12.50', $fresh->store_price);
        $this->assertEquals('18.00', $fresh->refill_price);
        $this->assertEquals('95.00', $fresh->new_bottle_price);
        $this->assertTrue($fresh->is_available_for_delivery);
    }

    /**
     * 7. Precios sin configurar: se permiten valores null explícitos (nunca se sustituyen por 0).
     */
    public function test_presentation_allows_unconfigured_prices_without_substituting_with_zero(): void
    {
        $bottle = BottlePresentation::getTwentyLiters();

        $response = $this->actingAs($this->adminUser)->put(
            route('admin.settings.bottle-presentations.update', $bottle->id),
            [
                'name' => 'Botellón 20 Litros',
                'capacity' => 20,
                'unit' => 'Litros',
                'refill_price' => null, // Sin configurar
                'store_price' => null, // Sin configurar
                'new_bottle_price' => null, // Sin configurar
            ]
        );

        $response->assertSessionHasNoErrors();

        $fresh = $bottle->fresh();
        $this->assertNull($fresh->refill_price);
        $this->assertNull($fresh->store_price);
        $this->assertNull($fresh->new_bottle_price);
        $this->assertFalse($fresh->has_refill_price);
        $this->assertFalse($fresh->has_store_price);

        // La consulta centralizada debe devolver null explícito
        $activeList = $this->scheduleService->getActivePresentations();
        $item = $activeList->firstWhere('id', $bottle->id);
        $this->assertNull($item['refill_price']);
        $this->assertNull($item['store_price']);
        $this->assertNull($item['new_bottle_price']);
    }

    /**
     * 8. Rechazo al intentar agregar nuevas presentaciones: el sistema opera exclusivamente con 20L.
     */
    public function test_rejects_creating_additional_presentations(): void
    {
        $res = $this->actingAs($this->adminUser)->post(route('admin.settings.bottle-presentations.store'), [
            'name' => 'Garrafón Extra 10L',
            'capacity' => 10,
            'unit' => 'Litros',
            'refill_price' => 10.00,
        ]);

        $res->assertSessionHasErrors(['presentation']);
    }

    /**
     * 9. Validación de precios inválidos (precios negativos o más de 2 decimales).
     */
    public function test_validates_prices_invalid_values(): void
    {
        $bottle = BottlePresentation::getTwentyLiters();

        // Precio <= 0
        $res1 = $this->actingAs($this->adminUser)->put(route('admin.settings.bottle-presentations.update', $bottle->id), [
            'refill_price' => '0',
        ]);
        $res1->assertSessionHasErrors(['refill_price']);

        // Precio con más de 2 decimales
        $res2 = $this->actingAs($this->adminUser)->put(route('admin.settings.bottle-presentations.update', $bottle->id), [
            'store_price' => '12.555',
        ]);
        $res2->assertSessionHasErrors(['store_price']);
    }

    /**
     * 10. Protección de permanencia: el botellón de 20 litros no puede ser eliminado del sistema.
     */
    public function test_twenty_liters_bottle_cannot_be_deleted(): void
    {
        $bottle = BottlePresentation::getTwentyLiters();

        $this->expectException(LogicException::class);
        $bottle->delete();
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

        $bottle = BottlePresentation::getTwentyLiters();

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

            // PUT bottle presentation
            $resUpdate = $this->actingAs($user)->put(route('admin.settings.bottle-presentations.update', $bottle->id), [
                'refill_price' => 20.00,
            ]);
            $resUpdate->assertForbidden();
        }

        // Usuario no autenticado es bloqueado (403 Forbidden por middleware de roles)
        $guestRes = $this->get(route('admin.settings.index'));
        $guestRes->assertForbidden();
    }

    /**
     * 12. Independencia: editar precios de botellón no altera horario y preserva conteos de clientes.
     */
    public function test_bottle_and_schedule_operations_are_independent_and_preserve_customers(): void
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
        $bottle = BottlePresentation::getTwentyLiters();

        // 1. Modificar precios de botellón
        $this->actingAs($this->adminUser)->put(route('admin.settings.bottle-presentations.update', $bottle->id), [
            'store_price' => '11.00',
            'refill_price' => '16.50',
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

        // Verificar que el botellón sigue teniendo sus precios
        $freshBottle = $bottle->fresh();
        $this->assertEquals('11.00', $freshBottle->store_price);
        $this->assertEquals('16.50', $freshBottle->refill_price);

        // Verificar que los conteos de botellones del cliente siguen inalterados
        $freshCustomer = $customer->fresh();
        $this->assertEquals(3, $freshCustomer->fixed_bottles_count);
        $this->assertEquals(2, $freshCustomer->exchange_bottles_count);
    }

    /**
     * 13. Habilitar y suspender servicio a domicilio como interruptor comercial de negocio.
     */
    public function test_toggle_delivery_service_as_business_switch(): void
    {
        $bottle = BottlePresentation::getTwentyLiters();
        $bottle->update(['is_available_for_delivery' => true]);

        // Alternar a suspendido
        $response = $this->actingAs($this->adminUser)->patch(
            route('admin.settings.bottle-presentations.toggle-status', $bottle->id)
        );
        $response->assertRedirect();
        $this->assertFalse($bottle->fresh()->is_available_for_delivery);

        // Alternar a habilitado
        $this->actingAs($this->adminUser)->patch(
            route('admin.settings.bottle-presentations.toggle-status', $bottle->id)
        );
        $this->assertTrue($bottle->fresh()->is_available_for_delivery);
    }

    /**
     * 14. Se rechaza alterar la capacidad a un valor distinto de 20 litros.
     */
    public function test_rejects_updating_capacity_to_different_than_twenty_liters(): void
    {
        $bottle = BottlePresentation::getTwentyLiters();

        $response = $this->actingAs($this->adminUser)->put(
            route('admin.settings.bottle-presentations.update', $bottle->id),
            [
                'capacity' => 10,
                'unit' => 'Litros',
            ]
        );

        $response->assertSessionHasErrors(['capacity']);
    }

    /**
     * 15. Consulta centralizada: getActivePresentations devuelve el botellón único de 20 litros.
     */
    public function test_active_presentations_returns_twenty_liters_bottle(): void
    {
        $bottle = BottlePresentation::getTwentyLiters();
        $active = $this->scheduleService->getActivePresentations();

        $this->assertCount(1, $active);
        $item = $active->first();
        $this->assertEquals($bottle->id, $item['id']);
        $this->assertEquals(20.00, $item['capacity']);
        $this->assertEquals('Litros', $item['unit']);
    }

    /**
     * 16. La ejecución del seeder no sobrescribe los precios previamente editados por el usuario.
     */
    public function test_seeder_does_not_overwrite_edited_presentation_prices(): void
    {
        // Obtener o configurar botellón de 20L con precios personalizados
        $bottle = BottlePresentation::getTwentyLiters();
        $bottle->update([
            'refill_price' => 19.50,
            'store_price' => 13.00,
            'new_bottle_price' => 110.00,
            'is_available_for_delivery' => true,
        ]);

        // Ejecutar el Seeder
        $seeder = new \Database\Seeders\DatabaseSeeder();
        $seeder->run();

        // Verificar que los precios editados se mantuvieron exactamente iguales
        $fresh = $bottle->fresh();
        $this->assertEquals('19.50', $fresh->refill_price);
        $this->assertEquals('13.00', $fresh->store_price);
        $this->assertEquals('110.00', $fresh->new_bottle_price);
        $this->assertTrue($fresh->is_available_for_delivery);
    }

    /**
     * 17. Tanto Administrador como Super Admin tienen acceso y permisos para ver y modificar configuración.
     */
    public function test_both_administrator_and_super_admin_can_access_and_update_settings(): void
    {
        $superAdminUser = User::factory()->create([
            'name' => 'Super Administrador',
            'email' => 'superadmin@purificadora.com',
            'status' => 'active',
        ]);
        $superAdminUser->assignRole('Super Admin');

        $bottle = BottlePresentation::getTwentyLiters();

        foreach ([$this->adminUser, $superAdminUser] as $admin) {
            // Ver configuración
            $resGet = $this->actingAs($admin)->get(route('admin.settings.index'));
            $resGet->assertOk();
            $resGet->assertInertia(fn ($page) => $page
                ->component('Admin/Settings/Index')
                ->has('schedule')
                ->has('bottlePresentation')
                ->where('bottlePresentation.capacity', 20)
            );

            // Modificar horario
            $resSchedule = $this->actingAs($admin)->put(route('admin.settings.schedule.update'), [
                'opening_time' => '07:30',
                'break_start_time' => '13:00',
                'break_end_time' => '15:00',
                'closing_time' => '18:30',
                'order_cutoff_advance_minutes' => 45,
            ]);
            $resSchedule->assertRedirect();
            $resSchedule->assertSessionHas('success');

            // Modificar precios del botellón de 20L
            $resBottle = $this->actingAs($admin)->put(route('admin.settings.bottle-presentations.update', $bottle->id), [
                'name' => 'Botellón 20 Litros',
                'store_price' => 12.50,
                'refill_price' => 18.00,
                'new_bottle_price' => 100.00,
                'is_available_for_delivery' => true,
            ]);
            $resBottle->assertRedirect();
            $resBottle->assertSessionHas('success');
        }
    }
}
