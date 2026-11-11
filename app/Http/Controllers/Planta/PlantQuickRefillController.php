<?php

namespace App\Http\Controllers\Planta;

use App\Http\Controllers\Controller;
use App\Models\PlantCounterRefill;
use App\Services\SettingService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlantQuickRefillController extends Controller
{
    public function __construct(
        protected SettingService $settingService
    ) {}

    /**
     * Muestra la pantalla de Llenado Rápido de mostrador en Planta.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        if (!$user->hasRole(['Operador Planta', 'Administrador', 'Super Admin']) && !$user->can('bottles.manage')) {
            abort(403, 'No autorizado para acceder al módulo de Planta.');
        }

        $unitPrice = $this->settingService->getCurrentStorePrice();

        $today = Carbon::today();
        $todayRefills = PlantCounterRefill::with('operator:id,name')
            ->whereDate('created_at', $today)
            ->orderBy('id', 'desc')
            ->limit(20)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'quantity' => (int) $r->quantity,
                'unit_price' => (float) $r->unit_price,
                'total_amount' => (float) $r->total_amount,
                'operator_name' => $r->operator?->name ?? 'Operador',
                'time' => $r->created_at->format('H:i'),
                'created_at' => $r->created_at->format('d/m/Y H:i'),
                'notes' => $r->notes,
            ]);

        $todayQuantity = (int) PlantCounterRefill::whereDate('created_at', $today)->sum('quantity');
        $todayTotalAmount = (float) PlantCounterRefill::whereDate('created_at', $today)->sum('total_amount');
        $todayServicesCount = (int) PlantCounterRefill::whereDate('created_at', $today)->count();

        return Inertia::render('Planta/QuickRefill/Index', [
            'unitPrice' => $unitPrice,
            'capacity' => 20,
            'todaySummary' => [
                'bottles_refilled' => $todayQuantity,
                'total_amount' => $todayTotalAmount,
                'services_count' => $todayServicesCount,
            ],
            'recentRefills' => $todayRefills,
            'isOperator' => $user->hasRole('Operador Planta') || $user->hasRole('Super Admin'),
            'isAdmin' => $user->hasRole('Administrador'),
        ]);
    }

    /**
     * Registra un servicio de llenado de mostrador para clientes que acuden a Planta.
     * Calcula el importe en servidor y persiste de manera inmutable el precio aplicado.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasRole('Administrador')) {
            // Permitir que Administrador supervise y pueda registrar si es necesario, o restringir según política
        }

        if (!$user->hasRole(['Operador Planta', 'Administrador', 'Super Admin'])) {
            abort(403, 'Solo el personal autorizado de Planta puede registrar llenados.');
        }

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'quantity.required' => 'La cantidad de botellones es obligatoria.',
            'quantity.integer' => 'La cantidad debe ser un número entero.',
            'quantity.min' => 'Debe registrar al menos 1 botellón llenado.',
            'quantity.max' => 'La cantidad máxima por registro es de 999 botellones.',
        ]);

        $quantity = (int) $validated['quantity'];
        $unitPrice = $this->settingService->getCurrentStorePrice();
        $totalAmount = round($quantity * $unitPrice, 2);

        $refill = PlantCounterRefill::create([
            'operator_id' => $user->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_amount' => $totalAmount,
            'notes' => $validated['notes'] ?? null,
        ]);

        $formattedTotal = number_format($totalAmount, 2);
        return back()->with('success', "Se registró el llenado de {$quantity} botellón(es) de 20L por un total de \${$formattedTotal} MXN.");
    }

    /**
     * Elimina un registro de llenado de mostrador por error de captura.
     */
    public function destroy(Request $request, PlantCounterRefill $refill): RedirectResponse
    {
        $user = $request->user();

        if (!$user->hasRole(['Operador Planta', 'Administrador', 'Super Admin'])) {
            abort(403, 'No autorizado para eliminar registros de Planta.');
        }

        $qty = $refill->quantity;
        $amount = number_format($refill->total_amount, 2);

        $refill->delete();

        return back()->with('success', "Se eliminó el registro de {$qty} botellón(es) (\${$amount} MXN) correctamente.");
    }
}

