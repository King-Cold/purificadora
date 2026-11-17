<?php

namespace App\Http\Controllers;

use App\Services\StoreScheduleService;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class WelcomeController extends Controller
{
    /**
     * Muestra la portada pública con horarios y presentaciones activas.
     */
    public function __invoke(StoreScheduleService $scheduleService): Response
    {
        $schedule = null;
        $scheduleStatus = null;
        $presentations = [];

        try {
            if (Schema::hasTable('branch_schedules')) {
                $schedule = $scheduleService->getScheduleSummary();
                $scheduleStatus = $scheduleService->checkOrderReception();
            }
            if (Schema::hasTable('bottle_presentations')) {
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
    }
}
