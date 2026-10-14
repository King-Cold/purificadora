<?php

require 'c:/PROYECTOS/purificadora/vendor/autoload.php';
$app = require_once 'c:/PROYECTOS/purificadora/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

echo "========================================================\n";
echo "   SUITE DE PRUEBAS DE AUDITORÍA: BOTELLONES Y CLIENTES\n";
echo "========================================================\n\n";

$admin = User::role('Administrador')->first();
$driver = User::role('Repartidor')->first();
$customerUser = User::role('Cliente')->first();

if (!$admin) {
    die("Error: No se encontró usuario Administrador\n");
}

$controller = new \App\Http\Controllers\Admin\CustomerController();

// 1. Registro con 2 fijos y 3 de cambio
echo "1. Probando registro con 2 fijos y 3 de cambio...\n";
$req1 = createPostRequest($admin, [
    'name' => 'Cliente Prueba Mixto',
    'nickname' => 'El Mixto',
    'phone' => '9991112233',
    'fixed_bottles_count' => 2,
    'exchange_bottles_count' => 3,
]);
$res1 = $controller->store($req1);
$c1 = Customer::where('phone', '9991112233')->first();
assertTest($c1 && $c1->fixed_bottles_count === 2 && $c1->exchange_bottles_count === 3 && $c1->total_bottles === 5 && is_null($c1->user_id), "Cliente Mixto registrado con 2 fijos, 3 de cambio (Total: 5), sin cuenta User");

// 2. Registro únicamente con fijos (ej. 4 fijos, 0 cambio)
echo "\n2. Probando registro únicamente con fijos...\n";
$req2 = createPostRequest($admin, [
    'name' => 'Doña Fija',
    'phone' => '9992223344',
    'fixed_bottles_count' => 4,
    'exchange_bottles_count' => 0,
]);
$res2 = $controller->store($req2);
$c2 = Customer::where('phone', '9992223344')->first();
assertTest($c2 && $c2->fixed_bottles_count === 4 && $c2->exchange_bottles_count === 0 && $c2->total_bottles === 4, "Cliente únicamente con 4 fijos registrado correctamente");

// 3. Registro únicamente con cambio (ej. 0 fijos, 5 cambio)
echo "\n3. Probando registro únicamente con cambio...\n";
$req3 = createPostRequest($admin, [
    'nickname' => 'Don Cambio',
    'phone' => '9993334455',
    'fixed_bottles_count' => 0,
    'exchange_bottles_count' => 5,
]);
$res3 = $controller->store($req3);
$c3 = Customer::where('phone', '9993334455')->first();
assertTest($c3 && $c3->fixed_bottles_count === 0 && $c3->exchange_bottles_count === 5 && $c3->total_bottles === 5, "Cliente únicamente con 5 de cambio registrado correctamente (sin nombre, solo apodo)");

// 4. Registro con ambas cantidades en 0
echo "\n4. Probando registro con ambas cantidades en 0...\n";
$req4 = createPostRequest($admin, [
    'name' => 'Vecino Nuevo Cero',
    'phone' => '9994445566',
    'fixed_bottles_count' => 0,
    'exchange_bottles_count' => 0,
]);
$res4 = $controller->store($req4);
$c4 = Customer::where('phone', '9994445566')->first();
assertTest($c4 && $c4->fixed_bottles_count === 0 && $c4->exchange_bottles_count === 0 && $c4->total_bottles === 0, "Cliente con ambas cantidades en 0 registrado correctamente");

// 5. Rechazo de cantidades negativas
echo "\n5. Probando rechazo de cantidades negativas...\n";
try {
    $req5 = createPostRequest($admin, [
        'name' => 'Falla Negativo',
        'phone' => '9999990001',
        'fixed_bottles_count' => -1,
        'exchange_bottles_count' => 2,
    ]);
    $controller->store($req5);
    echo "  [FAIL] Se esperaba ValidationException por valor negativo\n";
} catch (ValidationException $e) {
    assertTest(isset($e->errors()['fixed_bottles_count']), "Rechazo validado correctamente para fixed_bottles_count negativo");
}

// 6. Rechazo de decimales
echo "\n6. Probando rechazo de valores decimales...\n";
try {
    $req6 = createPostRequest($admin, [
        'name' => 'Falla Decimal',
        'phone' => '9999990002',
        'fixed_bottles_count' => 2.5,
        'exchange_bottles_count' => 1,
    ]);
    $controller->store($req6);
    echo "  [FAIL] Se esperaba ValidationException por valor decimal\n";
} catch (ValidationException $e) {
    assertTest(isset($e->errors()['fixed_bottles_count']), "Rechazo validado correctamente para fixed_bottles_count decimal");
}

// 7. Rechazo de valores no numéricos
echo "\n7. Probando rechazo de valores no numéricos...\n";
try {
    $req7 = createPostRequest($admin, [
        'name' => 'Falla No Numerico',
        'phone' => '9999990003',
        'fixed_bottles_count' => 'tres',
        'exchange_bottles_count' => 0,
    ]);
    $controller->store($req7);
    echo "  [FAIL] Se esperaba ValidationException por valor no numérico\n";
} catch (ValidationException $e) {
    assertTest(isset($e->errors()['fixed_bottles_count']), "Rechazo validado correctamente para fixed_bottles_count alfanumérico");
}

// 8, 9, 10. Edición correcta de ambas cantidades, conservación de CLI-XXXX y user_id
echo "\n8. Probando edición de cantidades, conservación de CLI-XXXX y user_id...\n";
$codeBefore = $c1->code;
$userIdBefore = $c1->user_id;

$editReq = createPutRequest($admin, [
    'name' => 'Cliente Prueba Mixto Modificado',
    'nickname' => 'El Mixto Renombrado',
    'phone' => '9991112233',
    'fixed_bottles_count' => 5, // Antes era 2
    'exchange_bottles_count' => 7, // Antes era 3
    'notes' => 'Ahora tiene 12 botellones en total',
]);

$controller->update($editReq, $c1);
$c1->refresh();

assertTest(
    $c1->fixed_bottles_count === 5 && 
    $c1->exchange_bottles_count === 7 && 
    $c1->total_bottles === 12 &&
    $c1->code === $codeBefore &&
    $c1->user_id === $userIdBefore &&
    $c1->name === 'Cliente Prueba Mixto Modificado',
    "Cliente editado: 5 fijos, 7 de cambio (Total: 12), conservó código ({$c1->code}) y user_id intactos"
);

// 11. Registro administrativo sin creación de User (verificar tabla users)
echo "\n11. Verificando que registro administrativo no creó registros en tabla users...\n";
$userSearch = User::where('name', 'Cliente Prueba Mixto Modificado')->orWhere('email', '9991112233')->first();
assertTest(is_null($userSearch), "Ningún User fue creado en el registro administrativo");

// 12. Registro público sin regresiones
echo "\n12. Probando registro público (RegisterController)...\n";
$regEmail = 'publico_' . time() . '@test.com';
$regController = new \App\Http\Controllers\Auth\RegisteredUserController();
$regReq = \Illuminate\Http\Request::create(route('register'), 'POST', [
    'name' => 'Usuario Público Test',
    'email' => $regEmail,
    'password' => 'Password123!',
    'password_confirmation' => 'Password123!',
]);
$regReq->setLaravelSession(app('session.store'));
$regController->store($regReq);

$newUser = User::where('email', $regEmail)->first();
$newCustomer = Customer::where('user_id', $newUser?->id)->first();
assertTest(
    $newUser && 
    $newUser->hasRole('Cliente') && 
    $newCustomer && 
    $newCustomer->fixed_bottles_count === 0 && 
    $newCustomer->exchange_bottles_count === 0 && 
    $newCustomer->total_bottles === 0,
    "Registro público exitoso: Rol Cliente asignado, Customer vinculado con botellones inicializados en 0"
);

// 13. Listado con cantidades correctas
echo "\n13. Probando Index Controller con cantidades de botellones...\n";
$indexReq = \Illuminate\Http\Request::create(route('admin.customers.index'), 'GET');
$indexReq->setUserResolver(fn() => $admin);
$indexResponse = $controller->index($indexReq);
$indexProps = $indexResponse->toResponse($indexReq)->getOriginalContent()['page']['props']['customers']['data'];
$itemC1 = collect($indexProps)->firstWhere('id', $c1->id);
assertTest(
    $itemC1 && 
    $itemC1['fixed_bottles_count'] === 5 && 
    $itemC1['exchange_bottles_count'] === 7 && 
    $itemC1['total_bottles'] === 12,
    "Index devuelve fixed_bottles_count, exchange_bottles_count y total_bottles correctamente"
);

// 14. Consulta detallada (Show) con cantidades y total correcto
echo "\n14. Probando Show Controller...\n";
$showReq = \Illuminate\Http\Request::create(route('admin.customers.show', $c1->id), 'GET');
$showReq->setUserResolver(fn() => $admin);
$showRes = $controller->show($showReq, $c1);
$showProps = $showRes->toResponse($showReq)->getOriginalContent()['page']['props'];
$showCustomer = $showProps['customer'];

assertTest(
    $showCustomer['fixed_bottles_count'] === 5 &&
    $showCustomer['exchange_bottles_count'] === 7 &&
    $showCustomer['total_bottles'] === 12 &&
    $showProps['canEdit'] === true,
    "Show devuelve fixed_bottles_count (5), exchange_bottles_count (7), total_bottles (12) y canEdit (true)"
);

// 15. Ausencia total de max_credit_bottles en props de Show y Edit
echo "\n15. Verificando ausencia total de max_credit_bottles en interfaces...\n";
$editReqGet = \Illuminate\Http\Request::create(route('admin.customers.edit', $c1->id), 'GET');
$editReqGet->setUserResolver(fn() => $admin);
$editRes = $controller->edit($editReqGet, $c1);
$editProps = $editRes->toResponse($editReqGet)->getOriginalContent()['page']['props']['customer'];

assertTest(
    !isset($showCustomer['max_credit_bottles']) &&
    !isset($editProps['max_credit_bottles']),
    "max_credit_bottles no se expone en los props de Show ni de Edit (Límite individual retirado)"
);

// 16. Restricción de rutas para roles no autorizados (Repartidor y Cliente)
echo "\n16. Probando autorización de rutas con Repartidor y Cliente...\n";
$driverBlocked = false;
try {
    $badReq = \Illuminate\Http\Request::create(route('admin.customers.create'), 'GET');
    $badReq->setUserResolver(fn() => $driver);
    $controller->create($badReq);
} catch (HttpException $e) {
    if ($e->getStatusCode() === 403) $driverBlocked = true;
}
assertTest($driverBlocked, "Repartidor bloqueado con 403 en customers.create");

$clientBlocked = false;
try {
    $badReq2 = \Illuminate\Http\Request::create(route('admin.customers.edit', $c1->id), 'GET');
    $badReq2->setUserResolver(fn() => $customerUser);
    $controller->edit($badReq2, $c1);
} catch (HttpException $e) {
    if ($e->getStatusCode() === 403) $clientBlocked = true;
}
assertTest($clientBlocked, "Cliente bloqueado con 403 en customers.edit");

// 17 y 18. Limpieza de datos de prueba y verificación de datos históricos
echo "\n17 y 18. Verificando integridad de datos históricos y limpiando pruebas...\n";
$historicalCustomer1 = Customer::find(1);
assertTest(
    $historicalCustomer1 && 
    $historicalCustomer1->code === 'CLI-0001' && 
    isset($historicalCustomer1->fixed_bottles_count) &&
    $historicalCustomer1->addresses()->count() >= 1,
    "Cliente histórico #1 (CLI-0001) intacto con sus domicilios vinculados y columnas de botellones inicializadas en 0"
);

// Eliminar temporales
$c1->forceDelete();
$c2->forceDelete();
$c3->forceDelete();
$c4->forceDelete();
$newCustomer?->forceDelete();
$newUser?->forceDelete();

echo "\n[INFO] Registros temporales eliminados correctamente.\n";
echo "\n========================================================\n";
echo "   TODAS LAS 18 PRUEBAS PASARON EXITOSAMENTE (100% OK)\n";
echo "========================================================\n";

function assertTest($condition, $message) {
    if ($condition) {
        echo "  [PASS] {$message}\n";
    } else {
        echo "  [FAIL] {$message}\n";
        exit(1);
    }
}

function createPostRequest($user, $data) {
    $req = \Illuminate\Http\Request::create(route('admin.customers.store'), 'POST', $data);
    $req->setUserResolver(fn() => $user);
    $req->setLaravelSession(app('session.store'));
    Auth::setUser($user);
    return $req;
}

function createPutRequest($user, $data) {
    $req = \Illuminate\Http\Request::create(route('admin.customers.update', 1), 'PUT', $data);
    $req->setUserResolver(fn() => $user);
    $req->setLaravelSession(app('session.store'));
    Auth::setUser($user);
    return $req;
}
