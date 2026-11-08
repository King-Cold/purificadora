<?php

use App\Http\Controllers\DashboardRedirectController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function (\App\Services\StoreScheduleService $scheduleService) {
    $schedule = null;
    $scheduleStatus = null;
    $presentations = [];

    try {
        if (\Illuminate\Support\Facades\Schema::hasTable('branch_schedules')) {
            $schedule = $scheduleService->getScheduleSummary();
            $scheduleStatus = $scheduleService->checkOrderReception();
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('bottle_presentations')) {
            $presentations = $scheduleService->getActivePresentations();
        }
    } catch (\Throwable $e) {
        // Fallback defensivo si la tabla no existe en entornos de prueba en memoria
    }

    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
        'schedule' => $schedule,
        'scheduleStatus' => $scheduleStatus,
        'presentations' => $presentations,
    ]);
});

// Redirección automática al panel correspondiente según el rol del usuario autenticado
Route::get('/dashboard', DashboardRedirectController::class)
    ->middleware(['auth'])
    ->name('dashboard');

// Panel 1: Administrador y Super Admin (Técnico)
Route::middleware(['auth', 'role:Super Admin|Administrador'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::get('/users', [\App\Http\Controllers\Admin\UserController::class, 'index'])
        ->name('users.index')
        ->middleware('permission:users.view');
    Route::get('/users/create', [\App\Http\Controllers\Admin\UserController::class, 'create'])
        ->name('users.create')
        ->middleware('permission:users.manage');
    Route::post('/users', [\App\Http\Controllers\Admin\UserController::class, 'store'])
        ->name('users.store')
        ->middleware('permission:users.manage');
    Route::get('/users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'show'])
        ->name('users.show')
        ->middleware('permission:users.view');
    Route::get('/users/{user}/edit', [\App\Http\Controllers\Admin\UserController::class, 'edit'])
        ->name('users.edit')
        ->middleware('permission:users.manage');
    Route::put('/users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'update'])
        ->name('users.update')
        ->middleware('permission:users.manage');
    Route::patch('/users/{user}/toggle-status', [\App\Http\Controllers\Admin\UserController::class, 'toggleStatus'])
        ->name('users.toggle-status')
        ->middleware('permission:users.manage');

    // Módulo de Gestión de Clientes (Consulta, Listado y Registro Administrativo)
    Route::get('/customers', [\App\Http\Controllers\Admin\CustomerController::class, 'index'])
        ->name('customers.index')
        ->middleware('permission:customers.view');
    Route::get('/customers/create', [\App\Http\Controllers\Admin\CustomerController::class, 'create'])
        ->name('customers.create')
        ->middleware('permission:customers.create');
    Route::post('/customers', [\App\Http\Controllers\Admin\CustomerController::class, 'store'])
        ->name('customers.store')
        ->middleware('permission:customers.create');
    Route::get('/customers/{customer}', [\App\Http\Controllers\Admin\CustomerController::class, 'show'])
        ->name('customers.show')
        ->middleware('permission:customers.view');
    Route::get('/customers/{customer}/edit', [\App\Http\Controllers\Admin\CustomerController::class, 'edit'])
        ->name('customers.edit')
        ->middleware('permission:customers.update');
    Route::put('/customers/{customer}', [\App\Http\Controllers\Admin\CustomerController::class, 'update'])
        ->name('customers.update')
        ->middleware('permission:customers.update');

    // Gestión del Domicilio de Entrega del Cliente
    Route::get('/customers/{customer}/addresses/create', [\App\Http\Controllers\Admin\CustomerAddressController::class, 'create'])
        ->name('customers.addresses.create')
        ->middleware('permission:customers.create');
    Route::post('/customers/{customer}/addresses', [\App\Http\Controllers\Admin\CustomerAddressController::class, 'store'])
        ->name('customers.addresses.store')
        ->middleware('permission:customers.create');
    Route::get('/customers/{customer}/addresses/{address}/edit', [\App\Http\Controllers\Admin\CustomerAddressController::class, 'edit'])
        ->name('customers.addresses.edit')
        ->middleware('permission:customers.update');
    Route::put('/customers/{customer}/addresses/{address}', [\App\Http\Controllers\Admin\CustomerAddressController::class, 'update'])
        ->name('customers.addresses.update')
        ->middleware('permission:customers.update');
    Route::delete('/customers/{customer}/addresses/{address}', [\App\Http\Controllers\Admin\CustomerAddressController::class, 'destroy'])
        ->name('customers.addresses.destroy')
        ->middleware('permission:customers.delete');

    // Configuración General (Envases y Precios, Horario de Sucursal)
    Route::get('/settings', [\App\Http\Controllers\Admin\GeneralSettingController::class, 'index'])
        ->name('settings.index')
        ->middleware('permission:settings.view');
    Route::put('/settings/schedule', [\App\Http\Controllers\Admin\GeneralSettingController::class, 'updateSchedule'])
        ->name('settings.schedule.update')
        ->middleware('permission:settings.manage');
    Route::put('/settings/debt-limit', [\App\Http\Controllers\Admin\GeneralSettingController::class, 'updateDebtLimit'])
        ->name('settings.debt-limit.update')
        ->middleware('permission:settings.manage');
    Route::post('/settings/bottle-presentations', [\App\Http\Controllers\Admin\GeneralSettingController::class, 'storeBottlePresentation'])
        ->name('settings.bottle-presentations.store')
        ->middleware('permission:settings.manage');
    Route::put('/settings/bottle-presentations/{bottlePresentation}', [\App\Http\Controllers\Admin\GeneralSettingController::class, 'updateBottlePresentation'])
        ->name('settings.bottle-presentations.update')
        ->middleware('permission:settings.manage');
    Route::patch('/settings/bottle-presentations/{bottlePresentation}/toggle-status', [\App\Http\Controllers\Admin\GeneralSettingController::class, 'toggleBottlePresentationStatus'])
        ->name('settings.bottle-presentations.toggle-status')
        ->middleware('permission:settings.manage');

    // Módulo de Gestión de Pedidos (Administrador)
    Route::get('/orders', [\App\Http\Controllers\Admin\OrderController::class, 'index'])
        ->name('orders.index')
        ->middleware('permission:orders.view');
    Route::get('/orders/search-customers', [\App\Http\Controllers\Admin\OrderController::class, 'searchCustomers'])
        ->name('orders.search-customers')
        ->middleware('permission:orders.create');
    Route::get('/orders/create', [\App\Http\Controllers\Admin\OrderController::class, 'create'])
        ->name('orders.create')
        ->middleware('permission:orders.create');
    Route::post('/orders', [\App\Http\Controllers\Admin\OrderController::class, 'store'])
        ->name('orders.store')
        ->middleware('permission:orders.create');
    Route::get('/orders/{order}', [\App\Http\Controllers\Admin\OrderController::class, 'show'])
        ->name('orders.show')
        ->middleware('permission:orders.view');
    Route::post('/orders/{order}/confirm', [\App\Http\Controllers\Admin\OrderController::class, 'confirm'])
        ->name('orders.confirm')
        ->middleware('permission:orders.confirm');
    Route::post('/orders/{order}/cancel', [\App\Http\Controllers\Admin\OrderController::class, 'cancel'])
        ->name('orders.cancel')
        ->middleware('permission:orders.cancel');

    // Módulo de Gestión de Hojas de Ruta (Administrador)
    Route::get('/routes', [\App\Http\Controllers\Admin\DeliveryRouteController::class, 'index'])
        ->name('routes.index')
        ->middleware('permission:routes.view');
    Route::get('/routes/create', [\App\Http\Controllers\Admin\DeliveryRouteController::class, 'create'])
        ->name('routes.create')
        ->middleware('permission:routes.assign');
    Route::post('/routes', [\App\Http\Controllers\Admin\DeliveryRouteController::class, 'store'])
        ->name('routes.store')
        ->middleware('permission:routes.assign');
    Route::get('/routes/{route}', [\App\Http\Controllers\Admin\DeliveryRouteController::class, 'show'])
        ->name('routes.show')
        ->middleware('permission:routes.view');
    Route::post('/routes/{route}/resume', [\App\Http\Controllers\Admin\DeliveryRouteController::class, 'resume'])
        ->name('routes.resume')
        ->middleware('permission:routes.assign');
});

// Panel 2: Cliente
Route::middleware(['auth', 'role:Cliente'])->group(function () {
    Route::get('/cliente/dashboard', function (
        \App\Services\OrderService $orderService,
        \App\Services\DebtAndCreditService $debtService,
        \App\Services\WeeklyPromotionService $promoService
    ) {
        $user = \Illuminate\Support\Facades\Auth::user();
        $customer = $user?->customer;
        $address = $customer?->address;
        $activeOrder = $customer ? $orderService->getActiveOrderForCustomer($customer) : null;
        if ($activeOrder) {
            $activeOrder->loadMissing(['items', 'deliveryRoute']);
        }

        $pendingDebtBottles = $customer ? $debtService->getPendingDebtBottles($customer) : 0;
        $pendingDebtAmount = $customer ? $debtService->getPendingDebtAmount($customer) : 0.0;
        $weeklyPromo = $customer ? $promoService->getWeeklyProgress($customer) : null;

        // Historial de pagos del cliente
        $paymentHistory = $customer ? \App\Models\OrderPayment::where('customer_id', $customer->id)
            ->with('order')
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'amount' => (float) $p->amount,
                'cash_tendered' => (float) $p->cash_tendered,
                'change_returned' => (float) $p->change_returned,
                'bottles_count' => (int) $p->bottles_covered_count,
                'order_folio' => $p->order?->folio,
                'date' => $p->created_at?->setTimezone('America/Merida')->format('d/m/Y H:i'),
            ]) : [];

        // Historial de entregas gratuitas
        $freeDeliveries = $customer ? \App\Models\OrderCharge::where('customer_id', $customer->id)
            ->where('status', \App\Models\OrderCharge::STATUS_WAIVED_PROMOTION)
            ->with('order')
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'order_folio' => $c->order?->folio,
                'bottles_count' => (int) $c->quantity_delivered,
                'date' => $c->created_at?->setTimezone('America/Merida')->format('d/m/Y H:i'),
            ]) : [];

        return Inertia::render('Cliente/Dashboard', [
            'customer' => $customer ? [
                'code' => $customer->code,
                'nickname' => $customer->nickname,
                'can_place_orders' => true,
                'fixed_bottles_count' => (int) $customer->fixed_bottles_count,
                'exchange_bottles_count' => (int) $customer->exchange_bottles_count,
                'total_bottles' => (int) $customer->total_bottles,
                'has_address' => !is_null($address),
                'address_summary' => $address ? ($address->street . ($address->exterior_number ? ' #' . $address->exterior_number : '')) : null,
                'between_streets' => $address?->between_streets,
                'reference' => $address?->reference,
                'zone_label' => $address?->zone_label,
            ] : null,
            'activeOrder' => $activeOrder ? [
                'id' => $activeOrder->id,
                'folio' => $activeOrder->folio,
                'status' => $activeOrder->status,
                'status_label' => $activeOrder->status_label,
                'total_bottles' => (int) $activeOrder->total_bottles,
                'fixed_quantity' => (int) $activeOrder->fixed_quantity,
                'exchange_quantity' => (int) $activeOrder->exchange_quantity,
                'operational_progress' => $activeOrder->getOperationalProgress(),
            ] : null,
            'debt' => [
                'pending_bottles' => $pendingDebtBottles,
                'pending_debt_bottles' => $pendingDebtBottles,
                'pending_amount' => $pendingDebtAmount,
                'pending_debt_amount' => $pendingDebtAmount,
                'disclaimer' => 'Son botellones pendientes de pago, no envases por devolver.',
                'clarification_note' => 'Son botellones pendientes de pago, no envases por devolver.',
            ],
            'weekly_promo' => $weeklyPromo,
            'payment_history' => $paymentHistory,
            'free_deliveries' => $freeDeliveries,
            'last_updated_at' => now('America/Merida')->format('d/m/Y H:i:s'),
        ]);
    })->name('cliente.dashboard');

    // Pedidos a domicilio del cliente
    Route::get('/cliente/pedidos', [\App\Http\Controllers\Cliente\OrderController::class, 'index'])
        ->name('cliente.orders.index');
    Route::get('/cliente/pedidos/crear', [\App\Http\Controllers\Cliente\OrderController::class, 'create'])
        ->name('cliente.orders.create');
    Route::post('/cliente/pedidos', [\App\Http\Controllers\Cliente\OrderController::class, 'store'])
        ->name('cliente.orders.store');
    Route::get('/cliente/pedidos/{order}', [\App\Http\Controllers\Cliente\OrderController::class, 'show'])
        ->name('cliente.orders.show');
    Route::post('/cliente/pedidos/{order}/cancelar', [\App\Http\Controllers\Cliente\OrderController::class, 'cancel'])
        ->name('cliente.orders.cancel');
    Route::patch('/cliente/pedidos/{order}/cancelar', [\App\Http\Controllers\Cliente\OrderController::class, 'cancel']);

    // Mi perfil de cliente
    Route::get('/cliente/perfil', [\App\Http\Controllers\ProfileController::class, 'edit'])
        ->name('cliente.profile.index');
    Route::put('/cliente/botellones', [\App\Http\Controllers\Cliente\BottleController::class, 'update'])
        ->name('cliente.bottles.update');

    // Gestión del domicilio único del cliente autenticado (autoservicio)
    Route::get('/cliente/domicilio', [\App\Http\Controllers\Cliente\AddressController::class, 'index'])
        ->name('cliente.addresses.index');
    Route::get('/cliente/domicilio/crear', [\App\Http\Controllers\Cliente\AddressController::class, 'create'])
        ->name('cliente.addresses.create');
    Route::post('/cliente/domicilio', [\App\Http\Controllers\Cliente\AddressController::class, 'store'])
        ->name('cliente.addresses.store');
    Route::get('/cliente/domicilio/editar', [\App\Http\Controllers\Cliente\AddressController::class, 'edit'])
        ->name('cliente.addresses.edit');
    Route::put('/cliente/domicilio', [\App\Http\Controllers\Cliente\AddressController::class, 'update'])
        ->name('cliente.addresses.update');
    Route::delete('/cliente/domicilio', [\App\Http\Controllers\Cliente\AddressController::class, 'destroy'])
        ->name('cliente.addresses.destroy');

    // Rutas de compatibilidad con parámetros / plural
    Route::get('/cliente/domicilios', [\App\Http\Controllers\Cliente\AddressController::class, 'index']);
    Route::get('/cliente/domicilios/crear', [\App\Http\Controllers\Cliente\AddressController::class, 'create']);
    Route::post('/cliente/domicilios', [\App\Http\Controllers\Cliente\AddressController::class, 'store']);
    Route::get('/cliente/domicilios/{address}/editar', [\App\Http\Controllers\Cliente\AddressController::class, 'edit']);
    Route::put('/cliente/domicilios/{address}', [\App\Http\Controllers\Cliente\AddressController::class, 'update']);
    Route::delete('/cliente/domicilios/{address}', [\App\Http\Controllers\Cliente\AddressController::class, 'destroy']);
});

// Panel 3: Repartidor
Route::middleware(['auth', 'role:Repartidor|Administrador|Super Admin'])->prefix('repartidor')->name('repartidor.')->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\Repartidor\RouteExecutionController::class, 'dashboard'])
        ->name('dashboard');

    // Pantalla unificada Reparto (Hub operativo móvil)
    Route::get('/reparto', [\App\Http\Controllers\Repartidor\RouteExecutionController::class, 'reparto'])
        ->name('reparto');

    // Control de existencias del camión: "Mi Carga"
    Route::get('/mi-carga', [\App\Http\Controllers\Repartidor\TruckLoadController::class, 'index'])
        ->name('truck-load.index');
    Route::post('/mi-carga/accept/{movement}', [\App\Http\Controllers\Repartidor\TruckLoadController::class, 'acceptTransfer'])
        ->name('truck-load.accept');
    Route::post('/mi-carga/reject/{movement}', [\App\Http\Controllers\Repartidor\TruckLoadController::class, 'rejectTransfer'])
        ->name('truck-load.reject');
    Route::post('/mi-carga/descargar', [\App\Http\Controllers\Repartidor\TruckLoadController::class, 'proposeUnload'])
        ->name('truck-load.propose-unload');

    // Accesos operativos dedicados: Por recoger, Por entregar, Cobrar pendientes
    Route::get('/por-recoger', [\App\Http\Controllers\Repartidor\RouteExecutionController::class, 'porRecoger'])
        ->name('collections.index');
    Route::post('/orders/{order}/collect-fixed', [\App\Http\Controllers\Repartidor\RouteExecutionController::class, 'collectFixed'])
        ->name('orders.collect-fixed');
    Route::get('/por-entregar', [\App\Http\Controllers\Repartidor\RouteExecutionController::class, 'porEntregar'])
        ->name('deliveries.index');
    Route::get('/cobrar-pendientes', [\App\Http\Controllers\Repartidor\RouteExecutionController::class, 'cobrarPendientes'])
        ->name('debt.index');

    // Incorporar y atender pedidos directamente a la ruta
    Route::post('/orders/{order}/add-to-route', [\App\Http\Controllers\Repartidor\RouteExecutionController::class, 'addToRoute'])
        ->name('orders.add-to-route');
    Route::post('/orders/{order}/serve', [\App\Http\Controllers\Repartidor\RouteExecutionController::class, 'serveOrder'])
        ->name('orders.serve');

    // Panel móvil "Mi Ruta"
    Route::get('/mi-ruta', [\App\Http\Controllers\Repartidor\RouteExecutionController::class, 'index'])
        ->name('route.index');
    Route::post('/routes/start-today', [\App\Http\Controllers\Repartidor\RouteExecutionController::class, 'startTodayRoute'])
        ->name('route.start-today');
    Route::post('/routes/{route}/start', [\App\Http\Controllers\Repartidor\RouteExecutionController::class, 'start'])
        ->name('route.start');
    Route::post('/routes/{route}/breakdown', [\App\Http\Controllers\Repartidor\RouteExecutionController::class, 'recordBreakdown'])
        ->name('route.breakdown');
    Route::post('/routes/{route}/resume', [\App\Http\Controllers\Repartidor\RouteExecutionController::class, 'resume'])
        ->name('route.resume');
    Route::post('/routes/{route}/complete', [\App\Http\Controllers\Repartidor\RouteExecutionController::class, 'complete'])
        ->name('route.complete');

    // Directorio y alta rápida de clientes
    Route::get('/clientes', [\App\Http\Controllers\Repartidor\RouteCustomerController::class, 'index'])
        ->name('customers.index');
    Route::get('/clientes/nuevo', [\App\Http\Controllers\Repartidor\RouteCustomerController::class, 'create'])
        ->name('customers.create');
    Route::post('/clientes/nuevo', [\App\Http\Controllers\Repartidor\RouteCustomerController::class, 'store'])
        ->name('customers.store-quick');
    Route::get('/customers/search', [\App\Http\Controllers\Repartidor\DeliveryOperationController::class, 'searchCustomers'])
        ->name('customers.search');
    Route::get('/customers/{customer}/traffic-light', [\App\Http\Controllers\Repartidor\DeliveryOperationController::class, 'trafficLight'])
        ->name('customers.traffic-light');
    Route::post('/customers', [\App\Http\Controllers\Repartidor\DeliveryOperationController::class, 'storeCustomer'])
        ->name('customers.store');
    Route::post('/customers/{customer}/settle-debt', [\App\Http\Controllers\Repartidor\RouteExecutionController::class, 'settleDebt'])
        ->name('customers.settle-debt');
    Route::post('/orders/direct', [\App\Http\Controllers\Repartidor\DeliveryOperationController::class, 'storeDirectOrder'])
        ->name('orders.direct');
    Route::get('/servicio-directo', [\App\Http\Controllers\Repartidor\RouteExecutionController::class, 'directService'])
        ->name('direct-service.create');
    Route::post('/servicio-directo', [\App\Http\Controllers\Repartidor\RouteExecutionController::class, 'storeDirectService'])
        ->name('direct-service.store');
    Route::get('/stops/{stop}/serve', [\App\Http\Controllers\Repartidor\RouteExecutionController::class, 'serve'])
        ->name('stops.serve');
    Route::post('/stops/{stop}/serve', [\App\Http\Controllers\Repartidor\RouteExecutionController::class, 'processServe'])
        ->name('stops.process-serve');
    Route::post('/stops/{stop}/complete', [\App\Http\Controllers\Repartidor\DeliveryOperationController::class, 'completeStop'])
        ->name('stops.complete');
    Route::post('/stops/{stop}/fail', [\App\Http\Controllers\Repartidor\RouteExecutionController::class, 'failStop'])
        ->name('stops.fail');
});

// Panel 4: Operador Planta & Supervisión de Planta
Route::middleware(['auth', 'role:Operador Planta|Administrador|Super Admin'])->prefix('planta')->name('planta.')->group(function () {
    // Vista Principal: Llenado rápido de mostrador (Abre al entrar)
    Route::get('/dashboard', [\App\Http\Controllers\Planta\PlantQuickRefillController::class, 'index'])->name('dashboard');
    Route::get('/llenado-rapido', [\App\Http\Controllers\Planta\PlantQuickRefillController::class, 'index'])->name('quick-refill.index');
    Route::post('/llenado-rapido', [\App\Http\Controllers\Planta\PlantQuickRefillController::class, 'store'])->name('quick-refill.store');

    // Módulo Fijos unificado (Por recibir, Por llenar, Listos para enviar)
    Route::get('/fijos', [\App\Http\Controllers\Planta\PlantFixedBottlesController::class, 'index'])->name('fijos.index');

    // Módulo Cambios (Recibir vacíos, Enviar llenos, Preparar cambios)
    Route::get('/cambios', [\App\Http\Controllers\Planta\PlantCambiosController::class, 'index'])->name('cambios.index');

    Route::get('/recepcion', [\App\Http\Controllers\Planta\PlantReceptionController::class, 'index'])->name('reception.index');
    Route::post('/recepcion/fijos', [\App\Http\Controllers\Planta\PlantReceptionController::class, 'confirmFixed'])->name('reception.confirm-fixed');
    Route::post('/recepcion/cambios', [\App\Http\Controllers\Planta\PlantReceptionController::class, 'confirmExchange'])->name('reception.confirm-exchange');
    Route::get('/existencias', [\App\Http\Controllers\Planta\PlantInventoryController::class, 'index'])->name('inventory.index');
    Route::post('/conteo-inicial', [\App\Http\Controllers\Planta\PlantInventoryController::class, 'storeInitialCount'])->name('inventory.initial-count');
    Route::post('/incidencias', [\App\Http\Controllers\Planta\PlantInventoryController::class, 'storeIncident'])->name('inventory.incidents.store');
    Route::post('/produccion/iniciar', [\App\Http\Controllers\Planta\PlantInventoryController::class, 'startWashing'])->name('inventory.production.start');
    Route::post('/produccion/finalizar', [\App\Http\Controllers\Planta\PlantInventoryController::class, 'finishWashing'])->name('inventory.production.finish');

    // Mesa de trabajo: Lavado y Llenado
    Route::get('/produccion', [\App\Http\Controllers\Planta\PlantProductionController::class, 'index'])->name('production.index');
    Route::post('/produccion/fijos/iniciar', [\App\Http\Controllers\Planta\PlantProductionController::class, 'startFixed'])->name('production.fixed.start');
    Route::post('/produccion/fijos/finalizar', [\App\Http\Controllers\Planta\PlantProductionController::class, 'finishFixed'])->name('production.fixed.finish');
    Route::post('/produccion/fijos/incidencia', [\App\Http\Controllers\Planta\PlantProductionController::class, 'damageFixed'])->name('production.fixed.damage');
    Route::post('/produccion/cambios/lote/iniciar', [\App\Http\Controllers\Planta\PlantProductionController::class, 'startExchangeBatch'])->name('production.exchange.start');
    Route::post('/produccion/cambios/lote/finalizar', [\App\Http\Controllers\Planta\PlantProductionController::class, 'finishExchangeBatch'])->name('production.exchange.finish');
    Route::post('/produccion/cambios/lote/incidencia', [\App\Http\Controllers\Planta\PlantProductionController::class, 'damageExchangeBatch'])->name('production.exchange.damage');

    // Cargas al Repartidor
    Route::get('/cargas', [\App\Http\Controllers\Planta\PlantLoadController::class, 'index'])->name('loads.index');
    Route::post('/cargas/proponer', [\App\Http\Controllers\Planta\PlantLoadController::class, 'store'])->name('loads.store');
    Route::post('/cargas/{movement}/retirar', [\App\Http\Controllers\Planta\PlantLoadController::class, 'withdraw'])->name('loads.withdraw');
});

// Rutas de gestión de perfil
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
