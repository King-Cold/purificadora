<?php

namespace App\Http\Controllers\Planta;

use App\Http\Controllers\Controller;
use App\Models\BottleIncident;
use App\Models\OrderItem;
use App\Services\PlantInventoryService;
use Inertia\Inertia;
use Inertia\Response;

class PlantDashboardController extends Controller
{
    public function __construct(
        protected PlantInventoryService $plantInventoryService
    ) {}

    /**
     * Muestra el panel principal de Planta con datos operativos reales.
     */
    public function index(): Response
    {
        $state = $this->plantInventoryService->getInventoryState();
        $pendingReceptions = $this->plantInventoryService->getPendingReceptions();

        // Conteo de fijos activos en planta
        $fixedInPlantCount = 0;
        foreach ($state['fixed_in_plant_by_customer'] as $item) {
            $fixedInPlantCount += ($item['in_washing'] + $item['ready_full']);
        }

        return Inertia::render('Planta/Dashboard', [
            'initialCountPending' => $state['initial_count_pending'],
            'initialCountInfo' => $state['initial_count_info'],
            'balances' => $state['balances'],
            'fixedInPlantCount' => $fixedInPlantCount,
            'recentIncidentsCount' => count($state['incidents']),
            'pendingReceptionsCount' => $pendingReceptions['total_pending_count'],
            'recentMovements' => array_slice($state['recent_movements']->toArray(), 0, 5),
        ]);
    }
}

