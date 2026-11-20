<?php

namespace App\Http\Controllers;

use App\Models\OrderReview;
use App\Services\SettingService;
use App\Services\StoreScheduleService;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class WelcomeController extends Controller
{
    /**
     * Muestra la portada pública con horarios, presentaciones activas y comentarios reales aprobados.
     */
    public function __invoke(StoreScheduleService $scheduleService, SettingService $settingService): Response
    {
        $schedule = null;
        $scheduleStatus = null;
        $presentations = [];
        $reviews = [];
        $reviewsMeta = [
            'average' => 0.0,
            'count' => 0,
        ];

        try {
            if (Schema::hasTable('branch_schedules')) {
                $schedule = $scheduleService->getScheduleSummary();
                $scheduleStatus = $scheduleService->checkOrderReception();
            }
            if (Schema::hasTable('bottle_presentations')) {
                $presentations = $scheduleService->getActivePresentations();
            }
            if (Schema::hasTable('order_reviews')) {
                $zonesMap = $settingService->getDeliveryZonesMap();
                $avatarPalette = [
                    'bg-[#0071BA]',
                    'bg-[#26845B]',
                    'bg-[#1E1545]',
                    'bg-[#D9266F]',
                ];

                $featured = OrderReview::with([
                    'customer:id,name,nickname',
                    'order:id,delivery_zone',
                ])
                    ->where('show_on_landing', true)
                    ->orderByDesc('featured_at')
                    ->orderByDesc('id')
                    ->get();

                $reviews = $featured->values()->map(function (OrderReview $review, int $idx) use ($zonesMap, $settingService, $avatarPalette) {
                    $displayName = trim((string) ($review->customer?->nickname ?: $review->customer?->name ?: 'Cliente Puritronic'));
                    $words = preg_split('/\s+/u', $displayName, -1, PREG_SPLIT_NO_EMPTY) ?: ['CP'];
                    $initials = count($words) >= 2
                        ? mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1))
                        : mb_strtoupper(mb_substr($words[0], 0, 2));

                    $zoneKey = $settingService->normalizeDeliveryZoneKey($review->order?->delivery_zone ?? '');
                    $zoneLabel = $zonesMap[$zoneKey] ?? null;

                    $relativeTime = $review->created_at
                        ? ucfirst($review->created_at->copy()->setTimezone('America/Merida')->locale('es')->diffForHumans())
                        : 'Reciente';

                    return [
                        'id' => $review->id,
                        'name' => $displayName,
                        'role' => $zoneLabel ? "Cliente · {$zoneLabel}" : 'Cliente verificado',
                        'avatarText' => $initials,
                        'avatarBg' => $avatarPalette[$idx % count($avatarPalette)],
                        'rating' => (int) $review->rating,
                        'time' => $relativeTime,
                        'comment' => $review->comment,
                        'verified' => true,
                    ];
                })->all();

                $count = count($reviews);
                $reviewsMeta = [
                    'average' => $count > 0 ? round((float) $featured->avg('rating'), 1) : 0.0,
                    'count' => $count,
                ];
            }
        } catch (\Throwable $e) {
            // Fallback defensivo si las tablas aún no existen en memoria
        }

        return Inertia::render('Welcome', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'laravelVersion' => Application::VERSION,
            'phpVersion' => PHP_VERSION,
            'schedule' => $schedule,
            'scheduleStatus' => $scheduleStatus,
            'presentations' => $presentations,
            'reviews' => $reviews,
            'reviewsMeta' => $reviewsMeta,
        ]);
    }
}
