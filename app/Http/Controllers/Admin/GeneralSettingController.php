<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBottlePresentationRequest;
use App\Http\Requests\Admin\UpdateBottlePresentationRequest;
use App\Http\Requests\Admin\UpdateScheduleRequest;
use App\Models\BottlePresentation;
use App\Services\SettingService;
use App\Services\StoreScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class GeneralSettingController extends Controller
{
    /**
     * Muestra la pantalla de Configuración General:
     * 1. Configuración del Botellón de 20 Litros y precios
     * 2. Horario de la sucursal
     */
    public function index(StoreScheduleService $scheduleService): Response
    {
        $schedule = $scheduleService->getSchedule();
        $scheduleSummary = $scheduleService->getScheduleSummary($schedule);

        $twentyLiters = BottlePresentation::getTwentyLiters();

        $presentationData = [
            'id' => $twentyLiters->id,
            'name' => $twentyLiters->name ?: 'Botellón 20 Litros',
            'capacity' => 20.00,
            'unit' => 'Litros',
            'formatted_capacity' => '20 Litros',
            'refill_price' => $twentyLiters->refill_price !== null ? (float) $twentyLiters->refill_price : null,
            'store_price' => $twentyLiters->store_price !== null ? (float) $twentyLiters->store_price : null,
            'new_bottle_price' => $twentyLiters->new_bottle_price !== null ? (float) $twentyLiters->new_bottle_price : null,
            'has_refill_price' => $twentyLiters->has_refill_price,
            'has_store_price' => $twentyLiters->has_store_price,
            'has_new_bottle_price' => $twentyLiters->has_new_bottle_price,
            'image_path' => $twentyLiters->image_path,
            'image_url' => $twentyLiters->image_url,
            'is_available_for_delivery' => (bool) $twentyLiters->is_available_for_delivery,
            'is_active' => (bool) $twentyLiters->is_active,
            'created_at' => $twentyLiters->created_at?->format('d/m/Y H:i'),
            'updated_at' => $twentyLiters->updated_at?->format('d/m/Y H:i'),
        ];

        return Inertia::render('Admin/Settings/Index', [
            'schedule' => $scheduleSummary,
            'bottlePresentation' => $presentationData,
            'bottlePresentations' => [$presentationData],
            'max_pending_payment_bottles' => app(SettingService::class)->getMaxPendingPaymentBottles(),
        ]);
    }

    /**
     * Actualiza el límite general de botellones pendientes de pago por cliente.
     */
    public function updateDebtLimit(Request $request, SettingService $settingService): RedirectResponse
    {
        $validated = $request->validate([
            'max_pending_payment_bottles' => ['required', 'integer', 'min:0'],
        ]);

        $settingService->setMaxPendingPaymentBottles((int) $validated['max_pending_payment_bottles']);

        return redirect()->back()->with('success', 'El límite máximo de botellones pendientes de pago se actualizó correctamente.');
    }

    /**
     * Actualiza el horario de atención, descansos y anticipación de la sucursal.
     */
    public function updateSchedule(UpdateScheduleRequest $request, StoreScheduleService $scheduleService): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $scheduleService) {
            $schedule = $scheduleService->getSchedule();
            $schedule->update([
                'opening_time' => $validated['opening_time'],
                'break_start_time' => $validated['break_start_time'],
                'break_end_time' => $validated['break_end_time'],
                'closing_time' => $validated['closing_time'],
                'order_cutoff_advance_minutes' => (int) $validated['order_cutoff_advance_minutes'],
            ]);
        });

        return redirect()->back()->with('success', 'El horario de la sucursal y la anticipación de pedidos se actualizaron correctamente.');
    }

    /**
     * Rechaza la adición de nuevas presentaciones ya que el sistema opera exclusivamente con 20L.
     */
    public function storeBottlePresentation(StoreBottlePresentationRequest $request): RedirectResponse
    {
        return redirect()->back()->withErrors([
            'presentation' => 'El sistema opera exclusivamente con botellones de 20 litros. No se permite agregar nuevas presentaciones al catálogo.',
        ]);
    }

    /**
     * Modifica los precios y configuración del botellón de 20 litros.
     */
    public function updateBottlePresentation(UpdateBottlePresentationRequest $request, BottlePresentation $bottlePresentation): RedirectResponse
    {
        $validated = $request->validated();

        $imagePath = $bottlePresentation->image_path;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file->getClientOriginalName());
            $destination = public_path('images/bottles');
            if (!file_exists($destination)) {
                mkdir($destination, 0755, true);
            }
            $file->move($destination, $filename);
            $imagePath = '/images/bottles/' . $filename;
        } elseif (array_key_exists('image_path', $validated)) {
            $imagePath = $validated['image_path'];
        }

        DB::transaction(function () use ($bottlePresentation, $validated, $imagePath) {
            $bottlePresentation->update([
                'name' => $validated['name'] ?? 'Botellón 20 Litros',
                'capacity' => 20.00,
                'unit' => 'Litros',
                'refill_price' => $validated['refill_price'] ?? null,
                'store_price' => $validated['store_price'] ?? null,
                'new_bottle_price' => $validated['new_bottle_price'] ?? null,
                'image_path' => $imagePath,
                'is_available_for_delivery' => $validated['is_available_for_delivery'] ?? $bottlePresentation->is_available_for_delivery,
                'is_active' => true,
            ]);
        });

        return redirect()->back()->with('success', 'La configuración y precios del botellón de 20 litros se actualizaron correctamente.');
    }

    /**
     * Alterna la disponibilidad comercial del servicio a domicilio para el botellón de 20L.
     * No elimina el botellón ni altera registros operacionales.
     */
    public function toggleBottlePresentationStatus(Request $request, BottlePresentation $bottlePresentation): RedirectResponse
    {
        if (!$request->user()?->can('settings.manage')) {
            abort(403, 'No tienes autorización para modificar la configuración de botellones.');
        }

        DB::transaction(function () use ($bottlePresentation) {
            $bottlePresentation->update([
                'is_available_for_delivery' => !$bottlePresentation->is_available_for_delivery,
            ]);
        });

        $statusLabel = $bottlePresentation->is_available_for_delivery ? 'habilitado' : 'suspendido';
        return redirect()->back()->with('success', "El servicio a domicilio para el botellón de 20 litros ha sido {$statusLabel}.");
    }
}
