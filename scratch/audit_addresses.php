<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Support\Facades\DB;

echo "=== AUDITORIA DE DOMICILIOS EN LA BASE DE DATOS ===" . PHP_EOL;

$duplicates = CustomerAddress::whereNull('deleted_at')
    ->select('customer_id', DB::raw('count(*) as total'))
    ->groupBy('customer_id')
    ->having('total', '>', 1)
    ->get();

echo "Clientes con más de 1 domicilio activo: " . $duplicates->count() . PHP_EOL;
foreach ($duplicates as $dup) {
    $c = Customer::find($dup->customer_id);
    echo "- Cliente ID: " . $dup->customer_id . " (" . ($c ? ($c->name ?: $c->nickname) : 'N/A') . "): " . $dup->total . " domicilios activos" . PHP_EOL;
    $addrs = CustomerAddress::where('customer_id', $dup->customer_id)->whereNull('deleted_at')->get();
    foreach ($addrs as $a) {
        echo "    * ID #{$a->id} | {$a->label} | {$a->street} #{$a->exterior_number} | Default: " . ($a->is_default ? 'YES' : 'NO') . PHP_EOL;
    }
}

$totalActive = CustomerAddress::whereNull('deleted_at')->count();
echo PHP_EOL . "Total domicilios activos en sistema: " . $totalActive . PHP_EOL;

$allActive = CustomerAddress::whereNull('deleted_at')->get();
foreach ($allActive as $a) {
    $c = Customer::find($a->customer_id);
    echo "Addr #{$a->id} | Cust #{$a->customer_id} (" . ($c ? ($c->name ?: $c->nickname) : 'N/A') . ") | {$a->label} | {$a->street} #{$a->exterior_number} | City: {$a->city} | Zone: " . ($a->delivery_zone ?: 'NULL') . PHP_EOL;
}

echo PHP_EOL . "=== COLUMNAS EN customer_addresses ===" . PHP_EOL;
$columns = \Illuminate\Support\Facades\Schema::getColumnListing('customer_addresses');
print_r($columns);
