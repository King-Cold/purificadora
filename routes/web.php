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
});

// Panel 2: Cliente
Route::middleware(['auth', 'role:Cliente'])->group(function () {
    Route::get('/cliente/dashboard', function (\App\Services\OrderService $orderService) {
        $user = \Illuminate\Support\Facades\Auth::user();
        $customer = $user?->customer;
        $address = $customer?->address;
        $activeOrder = $customer ? $orderService->getActiveOrderForCustomer($customer) : null;
        if ($activeOrder) {
            $activeOrder->loadMissing('items');
        }

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
            ] : null,
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
Route::middleware(['auth', 'role:Repartidor'])->group(function () {
    Route::get('/repartidor/dashboard', function () {
        return Inertia::render('Repartidor/Dashboard');
    })->name('repartidor.dashboard');
});

// Panel 4: Operador Planta
Route::middleware(['auth', 'role:Operador Planta'])->group(function () {
    Route::get('/planta/dashboard', function () {
        return Inertia::render('Planta/Dashboard');
    })->name('planta.dashboard');
});

// Rutas de gestión de perfil
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
