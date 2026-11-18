<?php

use App\Http\Controllers\Admin\CustomerAddressController as AdminCustomerAddressController;
use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ExchangeInventoryController;
use App\Http\Controllers\Admin\GeneralSettingController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Cliente\AddressController as ClientAddressController;
use App\Http\Controllers\Cliente\BottleController as ClientBottleController;
use App\Http\Controllers\Cliente\DashboardController as ClientDashboardController;
use App\Http\Controllers\Cliente\OrderController as ClientOrderController;
use App\Http\Controllers\DashboardRedirectController;
use App\Http\Controllers\Planta\PlantCambiosController;
use App\Http\Controllers\Planta\PlantFixedBottlesController;
use App\Http\Controllers\Planta\PlantInventoryController;
use App\Http\Controllers\Planta\PlantLoadController;
use App\Http\Controllers\Planta\PlantProductionController;
use App\Http\Controllers\Planta\PlantQuickRefillController;
use App\Http\Controllers\Planta\PlantReceptionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Repartidor\DeliveryOperationController;
use App\Http\Controllers\Repartidor\RouteCustomerController;
use App\Http\Controllers\Repartidor\RouteExecutionController;
use App\Http\Controllers\Repartidor\TruckLoadController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas Públicas y Acceso Principal
|--------------------------------------------------------------------------
*/

Route::get('/', WelcomeController::class);

Route::get('/dashboard', DashboardRedirectController::class)
    ->middleware(['auth'])
    ->name('dashboard');

/*
|--------------------------------------------------------------------------
| Panel 1: Administrador y Super Admin
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:Super Admin|Administrador'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');

        // Usuarios
        Route::controller(UserController::class)
            ->prefix('users')
            ->name('users.')
            ->group(function () {
                Route::get('/', 'index')->name('index')->middleware('permission:users.view');
                Route::get('/create', 'create')->name('create')->middleware('permission:users.manage');
                Route::post('/', 'store')->name('store')->middleware('permission:users.manage');
                Route::get('/{user}', 'show')->name('show')->middleware('permission:users.view');
                Route::get('/{user}/edit', 'edit')->name('edit')->middleware('permission:users.manage');
                Route::put('/{user}', 'update')->name('update')->middleware('permission:users.manage');
                Route::patch('/{user}/toggle-status', 'toggleStatus')->name('toggle-status')->middleware('permission:users.manage');
            });

        // Clientes y domicilios de entrega
        Route::prefix('customers')
            ->name('customers.')
            ->group(function () {
                Route::controller(AdminCustomerController::class)->group(function () {
                    Route::get('/', 'index')->name('index')->middleware('permission:customers.view');
                    Route::get('/create', 'create')->name('create')->middleware('permission:customers.create');
                    Route::post('/', 'store')->name('store')->middleware('permission:customers.create');
                    Route::get('/{customer}', 'show')->name('show')->middleware('permission:customers.view');
                    Route::get('/{customer}/edit', 'edit')->name('edit')->middleware('permission:customers.update');
                    Route::put('/{customer}', 'update')->name('update')->middleware('permission:customers.update');
                    Route::delete('/{customer}', 'destroy')->name('destroy')->middleware('permission:customers.delete');
                });

                Route::controller(AdminCustomerAddressController::class)
                    ->prefix('{customer}/addresses')
                    ->name('addresses.')
                    ->group(function () {
                        Route::get('/create', 'create')->name('create')->middleware('permission:customers.create');
                        Route::post('/', 'store')->name('store')->middleware('permission:customers.create');
                        Route::get('/{address}/edit', 'edit')->name('edit')->middleware('permission:customers.update');
                        Route::put('/{address}', 'update')->name('update')->middleware('permission:customers.update');
                        Route::delete('/{address}', 'destroy')->name('destroy')->middleware('permission:customers.delete');
                    });
            });

        // Configuración general (horarios, límite de crédito, presentaciones y precios)
        Route::controller(GeneralSettingController::class)
            ->prefix('settings')
            ->name('settings.')
            ->group(function () {
                Route::get('/', 'index')->name('index')->middleware('permission:settings.view');

                Route::middleware('permission:settings.manage')->group(function () {
                    Route::put('/schedule', 'updateSchedule')->name('schedule.update');
                    Route::put('/debt-limit', 'updateDebtLimit')->name('debt-limit.update');
                    Route::put('/zones', 'updateDeliveryZones')->name('zones.update');
                    Route::post('/bottle-presentations', 'storeBottlePresentation')->name('bottle-presentations.store');
                    Route::put('/bottle-presentations/{bottlePresentation}', 'updateBottlePresentation')->name('bottle-presentations.update');
                    Route::patch('/bottle-presentations/{bottlePresentation}/toggle-status', 'toggleBottlePresentationStatus')->name('bottle-presentations.toggle-status');
                });
            });

        // Pedidos
        Route::controller(AdminOrderController::class)
            ->prefix('orders')
            ->name('orders.')
            ->group(function () {
                Route::get('/', 'index')->name('index')->middleware('permission:orders.view');
                Route::get('/{order}', 'show')->name('show')->middleware('permission:orders.view');
                Route::post('/{order}/confirm', 'confirm')->name('confirm')->middleware('permission:orders.confirm');
                Route::post('/{order}/cancel', 'cancel')->name('cancel')->middleware('permission:orders.cancel');
            });

        // Supervisión: inventario de cambios (botellones 20L)
        Route::controller(ExchangeInventoryController::class)
            ->prefix('supervision/inventario-cambios')
            ->name('exchange-inventory.')
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::put('/meta', 'updateTarget')->name('target.update');
                Route::post('/altas', 'storeNewBottles')->name('new-bottles.store');
                Route::post('/incidencias/{incident}/resolver', 'resolveIncident')->name('incidents.resolve');
                Route::post('/sin-localizar/resolver', 'resolveUnlocated')->name('unlocated.resolve');
                Route::post('/inconvenientes', 'reportInconvenience')->name('inconveniences.store');
            });
    });

/*
|--------------------------------------------------------------------------
| Panel 2: Cliente
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:Cliente'])
    ->prefix('cliente')
    ->group(function () {
        Route::get('/dashboard', ClientDashboardController::class)->name('cliente.dashboard');

        // Pedidos a domicilio
        Route::controller(ClientOrderController::class)
            ->prefix('pedidos')
            ->group(function () {
                Route::name('cliente.orders.')->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('/crear', 'create')->name('create');
                    Route::post('/', 'store')->name('store');
                    Route::get('/{order}', 'show')->name('show');
                    Route::post('/{order}/cancelar', 'cancel')->name('cancel');
                });
                Route::patch('/{order}/cancelar', 'cancel');
            });

        // Perfil y botellones
        Route::get('/perfil', [ProfileController::class, 'edit'])->name('cliente.profile.index');
        Route::put('/botellones', [ClientBottleController::class, 'update'])->name('cliente.bottles.update');

        // Domicilio único del cliente (y alias en plural por compatibilidad)
        Route::controller(ClientAddressController::class)->group(function () {
            Route::prefix('domicilio')
                ->name('cliente.addresses.')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('/crear', 'create')->name('create');
                    Route::post('/', 'store')->name('store');
                    Route::get('/editar', 'edit')->name('edit');
                    Route::put('/', 'update')->name('update');
                    Route::delete('/', 'destroy')->name('destroy');
                });

            Route::prefix('domicilios')->group(function () {
                Route::get('/', 'index');
                Route::get('/crear', 'create');
                Route::post('/', 'store');
                Route::get('/{address}/editar', 'edit');
                Route::put('/{address}', 'update');
                Route::delete('/{address}', 'destroy');
            });
        });
    });

/*
|--------------------------------------------------------------------------
| Panel 3: Repartidor
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:Repartidor|Administrador|Super Admin'])
    ->prefix('repartidor')
    ->name('repartidor.')
    ->group(function () {
        // Control de existencias del camión ("Mi Carga")
        Route::controller(TruckLoadController::class)
            ->prefix('mi-carga')
            ->name('truck-load.')
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/accept/{movement}', 'acceptTransfer')->name('accept');
                Route::post('/reject/{movement}', 'rejectTransfer')->name('reject');
                Route::post('/descargar', 'proposeUnload')->name('propose-unload');
                Route::post('/inconveniente', 'reportInconvenience')->name('report-inconvenience');
            });

        // Directorio y alta rápida de clientes
        Route::controller(RouteCustomerController::class)
            ->prefix('clientes')
            ->name('customers.')
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/nuevo', 'create')->name('create');
                Route::post('/nuevo', 'store')->name('store-quick');
            });

        // Ejecución de reparto, rutas, cobros y paradas
        Route::controller(RouteExecutionController::class)->group(function () {
            Route::get('/dashboard', 'dashboard')->name('dashboard');
            Route::get('/reparto', 'reparto')->name('reparto');

            // Fijos: por recoger, listos para cargar y por entregar
            Route::get('/por-recoger', 'porRecoger')->name('collections.index');
            Route::post('/orders/{order}/collect-fixed', 'collectFixed')->name('orders.collect-fixed');
            Route::post('/orders/{order}/cancel', 'cancelOrder')->name('orders.cancel');
            Route::post('/orders/{order}/incident', 'reportOrderIncident')->name('orders.incident');

            Route::prefix('listos-para-cargar')
                ->name('ready-to-load.')
                ->group(function () {
                    Route::get('/', 'listosParaCargar')->name('index');
                    Route::post('/aceptar', 'acceptTruckLoadFixed')->name('accept');
                    Route::post('/aceptar-todos', 'acceptAllTruckLoadFixed')->name('accept-all');
                    Route::post('/incidencia', 'reportFixedLoadIncident')->name('incident');
                });

            Route::get('/por-entregar', 'porEntregar')->name('deliveries.index');
            Route::post('/orders/{order}/deliver-fixed', 'deliverFixed')->name('orders.deliver-fixed');

            // Cobranza y liquidación de adeudos
            Route::get('/cobrar-pendientes', 'cobrarPendientes')->name('debt.index');
            Route::post('/customers/{customer}/settle-debt', 'settleDebt')->name('customers.settle-debt');

            // Atención directa de pedidos en ruta
            Route::post('/orders/{order}/add-to-route', 'addToRoute')->name('orders.add-to-route');
            Route::post('/orders/{order}/serve', 'serveOrder')->name('orders.serve');

            // Ciclo de vida de la ruta ("Mi Ruta")
            Route::get('/mi-ruta', 'index')->name('route.index');
            Route::prefix('routes')
                ->name('route.')
                ->group(function () {
                    Route::post('/start-today', 'startTodayRoute')->name('start-today');
                    Route::post('/{route}/start', 'start')->name('start');
                    Route::post('/{route}/breakdown', 'recordBreakdown')->name('breakdown');
                    Route::post('/{route}/resume', 'resume')->name('resume');
                    Route::post('/{route}/complete', 'complete')->name('complete');
                });

            // Servicio directo y atención de paradas
            Route::get('/servicio-directo', 'directService')->name('direct-service.create');
            Route::post('/servicio-directo', 'storeDirectService')->name('direct-service.store');
            Route::get('/stops/{stop}/serve', 'serve')->name('stops.serve');
            Route::post('/stops/{stop}/serve', 'processServe')->name('stops.process-serve');
            Route::post('/stops/{stop}/fail', 'failStop')->name('stops.fail');
        });

        // Operaciones API de apoyo en ruta
        Route::controller(DeliveryOperationController::class)->group(function () {
            Route::get('/customers/search', 'searchCustomers')->name('customers.search');
            Route::get('/customers/{customer}/traffic-light', 'trafficLight')->name('customers.traffic-light');
            Route::post('/customers', 'storeCustomer')->name('customers.store');
            Route::post('/orders/direct', 'storeDirectOrder')->name('orders.direct');
            Route::post('/stops/{stop}/complete', 'completeStop')->name('stops.complete');
        });
    });

/*
|--------------------------------------------------------------------------
| Panel 4: Operador Planta y Supervisión de Planta
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:Operador Planta|Administrador|Super Admin'])
    ->prefix('planta')
    ->name('planta.')
    ->group(function () {
        // Llenado rápido de mostrador (vista principal)
        Route::controller(PlantQuickRefillController::class)->group(function () {
            Route::get('/dashboard', 'index')->name('dashboard');
            Route::prefix('llenado-rapido')
                ->name('quick-refill.')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/', 'store')->name('store');
                    Route::delete('/{refill}', 'destroy')->name('destroy');
                });
        });

        // Módulo de Fijos
        Route::controller(PlantFixedBottlesController::class)
            ->prefix('fijos')
            ->name('fijos.')
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/marcar-llenados', 'markFilled')->name('mark-filled');
                Route::post('/marcar-todos-llenados', 'markAllFilled')->name('mark-all-filled');
                Route::post('/recibir-y-llenar', 'receiveAndFill')->name('receive-and-fill');
                Route::post('/recibir-y-llenar-todos', 'receiveAndFillAll')->name('receive-and-fill-all');
                Route::post('/incidencia', 'reportIncident')->name('report-incident');
            });

        // Módulo de Cambios
        Route::controller(PlantCambiosController::class)
            ->prefix('cambios')
            ->name('cambios.')
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/recibir-vacios', 'receiveEmpties')->name('receive-empties');
                Route::post('/llenar', 'fill')->name('fill');
                Route::post('/subir-camion', 'loadTruck')->name('load-truck');
                Route::post('/inconveniente', 'reportInconvenience')->name('report-inconvenience');
            });

        // Recepción desde camión
        Route::controller(PlantReceptionController::class)
            ->prefix('recepcion')
            ->name('reception.')
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/fijos', 'confirmFixed')->name('confirm-fixed');
                Route::post('/fijos/aceptar-todos', 'confirmAllFixed')->name('confirm-all-fixed');
                Route::post('/cambios', 'confirmExchange')->name('confirm-exchange');
            });

        // Existencias e inventario físico
        Route::controller(PlantInventoryController::class)
            ->name('inventory.')
            ->group(function () {
                Route::get('/existencias', 'index')->name('index');
                Route::post('/conteo-inicial', 'storeInitialCount')->name('initial-count');
                Route::post('/incidencias', 'storeIncident')->name('incidents.store');
                Route::post('/produccion/iniciar', 'startWashing')->name('production.start');
                Route::post('/produccion/finalizar', 'finishWashing')->name('production.finish');
            });

        // Mesa de trabajo: lavado y llenado
        Route::controller(PlantProductionController::class)
            ->prefix('produccion')
            ->name('production.')
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/fijos/iniciar', 'startFixed')->name('fixed.start');
                Route::post('/fijos/finalizar', 'finishFixed')->name('fixed.finish');
                Route::post('/fijos/incidencia', 'damageFixed')->name('fixed.damage');
                Route::post('/cambios/lote/iniciar', 'startExchangeBatch')->name('exchange.start');
                Route::post('/cambios/lote/finalizar', 'finishExchangeBatch')->name('exchange.finish');
                Route::post('/cambios/lote/incidencia', 'damageExchangeBatch')->name('exchange.damage');
            });

        // Cargas al repartidor
        Route::controller(PlantLoadController::class)
            ->prefix('cargas')
            ->name('loads.')
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/proponer', 'store')->name('store');
                Route::post('/{movement}/retirar', 'withdraw')->name('withdraw');
            });
    });

/*
|--------------------------------------------------------------------------
| Perfil de Usuario y Autenticación
|--------------------------------------------------------------------------
*/

Route::middleware('auth')
    ->controller(ProfileController::class)
    ->prefix('profile')
    ->name('profile.')
    ->group(function () {
        Route::get('/', 'edit')->name('edit');
        Route::patch('/', 'update')->name('update');
        Route::delete('/', 'destroy')->name('destroy');
    });

require __DIR__.'/auth.php';
