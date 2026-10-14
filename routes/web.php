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

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
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
});

// Panel 2: Cliente
Route::middleware(['auth', 'role:Cliente'])->group(function () {
    Route::get('/cliente/dashboard', function () {
        return Inertia::render('Cliente/Dashboard');
    })->name('cliente.dashboard');
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
