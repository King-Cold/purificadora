<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBottlePresentationRequest;
use App\Http\Requests\Admin\UpdateBottlePresentationRequest;
use App\Http\Requests\Admin\UpdateScheduleRequest;
use App\Models\BottlePresentation;
use App\Models\BranchSchedule;
use App\Services\StoreScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class GeneralSettingController extends Controller
{
    /**
     * Muestra la pantalla de Configuración General con las dos secciones:
     * 1. Envases y precios
     * 2. Horario de la sucursal
     */
    public function index(StoreScheduleService $scheduleService): Response
    {
        $schedule = $scheduleService->getSchedule();
        $scheduleSummary = $scheduleService->getScheduleSummary($schedule);

        $presentations = BottlePresentation::query()
            ->orderBy('id', 'asc')
            ->get()
            ->map(function (BottlePresentation $presentation) {
                return [
                    'id' => $presentation->id,
                    'name' => $presentation->name,
                    'capacity' => (float) $presentation->capacity,
                    'unit' => $presentation->unit,
                    'formatted_capacity' => $presentation->formatted_capacity,
                    'refill_price' => $presentation->refill_price !== null ? (float) $presentation->refill_price : null,
                    'store_price' => $presentation->store_price !== null ? (float) $presentation->store_price : null,
                    'new_bottle_price' => $presentation->new_bottle_price !== null ? (float) $presentation->new_bottle_price : null,
                    'has_refill_price' => $presentation->has_refill_price,
                    'has_store_price' => $presentation->has_store_price,
                    'has_new_bottle_price' => $presentation->has_new_bottle_price,
                    'image_path' => $presentation->image_path,
                    'image_url' => $presentation->image_url,
                    'is_available_for_delivery' => (bool) $presentation->is_available_for_delivery,
                    'is_active' => (bool) $presentation->is_active,
                    'created_at' => $presentation->created_at?->format('d/m/Y H:i'),
                    'updated_at' => $presentation->updated_at?->format('d/m/Y H:i'),
                ];
            });

        return Inertia::render('Admin/Settings/Index', [
            'schedule' => $scheduleSummary,
            'bottlePresentations' => $presentations,
        ]);
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
     * Registra una nueva presentación de envase y sus precios en el catálogo.
     */
    public function storeBottlePresentation(StoreBottlePresentationRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $imagePath = $validated['image_path'] ?? null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file->getClientOriginalName());
            $destination = public_path('images/bottles');
            if (!file_exists($destination)) {
                mkdir($destination, 0755, true);
            }
            $file->move($destination, $filename);
            $imagePath = '/images/bottles/' . $filename;
        }

        $presentation = DB::transaction(function () use ($validated, $imagePath) {
            return BottlePresentation::create([
                'name' => $validated['name'],
                'capacity' => $validated['capacity'],
                'unit' => $validated['unit'],
                'refill_price' => $validated['refill_price'] ?? null,
                'store_price' => $validated['store_price'] ?? null,
                'new_bottle_price' => $validated['new_bottle_price'] ?? null,
                'image_path' => $imagePath,
                'is_available_for_delivery' => $validated['is_available_for_delivery'] ?? false,
                'is_active' => $validated['is_active'] ?? true,
            ]);
        });

        return redirect()->back()->with('success', "La presentación '{$presentation->name}' ha sido agregada exitosamente al catálogo.");
    }

    /**
     * Modifica los datos o precios de una presentación de envase existente.
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
                'name' => $validated['name'],
                'capacity' => $validated['capacity'],
                'unit' => $validated['unit'],
                'refill_price' => $validated['refill_price'] ?? null,
                'store_price' => $validated['store_price'] ?? null,
                'new_bottle_price' => $validated['new_bottle_price'] ?? null,
                'image_path' => $imagePath,
                'is_available_for_delivery' => $validated['is_available_for_delivery'] ?? $bottlePresentation->is_available_for_delivery,
                'is_active' => $validated['is_active'] ?? $bottlePresentation->is_active,
            ]);
        });

        return redirect()->back()->with('success', "La presentación '{$bottlePresentation->name}' se actualizó correctamente.");
    }

    /**
     * Alterna el estado activo/inactivo de una presentación de envase.
     * No realiza eliminación física para preservar futuras referencias de pedidos e historial.
     */
    public function toggleBottlePresentationStatus(Request $request, BottlePresentation $bottlePresentation): RedirectResponse
    {
        if (!$request->user()?->can('settings.manage')) {
            abort(403, 'No tienes autorización para modificar el catálogo de presentaciones.');
        }

        DB::transaction(function () use ($bottlePresentation) {
            $bottlePresentation->update([
                'is_active' => !$bottlePresentation->is_active,
            ]);
        });

        $statusLabel = $bottlePresentation->is_active ? 'activada' : 'desactivada';
        return redirect()->back()->with('success', "La presentación '{$bottlePresentation->name}' fue {$statusLabel} correctamente.");
    }
}
