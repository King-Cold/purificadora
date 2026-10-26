<?php

namespace App\Services;

use App\Models\BottlePresentation;
use App\Models\BranchSchedule;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Collection;

class StoreScheduleService
{
    /**
     * Zona horaria oficial para los cálculos del negocio en Yucatán.
     */
    public const TIMEZONE = 'America/Merida';

    /**
     * Obtiene el horario actual de la sucursal.
     */
    public function getSchedule(): BranchSchedule
    {
        return BranchSchedule::current();
    }

    /**
     * Calcula la hora límite de pedidos a partir de la hora de cierre y la anticipación.
     */
    public function calculateCutoffTime(string $closingTime, int $advanceMinutes): string
    {
        $close = Carbon::createFromFormat('H:i', substr($closingTime, 0, 5), self::TIMEZONE);
        return $close->subMinutes(max(0, $advanceMinutes))->format('H:i');
    }

    /**
     * Obtiene la hora límite de recepción de pedidos del horario configurado.
     */
    public function getCutoffTime(?BranchSchedule $schedule = null): string
    {
        $sched = $schedule ?? $this->getSchedule();
        return $this->calculateCutoffTime($sched->closing_time, $sched->order_cutoff_advance_minutes);
    }

    /**
     * Obtiene los intervalos permitidos para la recepción de pedidos.
     */
    public function getAllowedIntervals(?BranchSchedule $schedule = null): array
    {
        $sched = $schedule ?? $this->getSchedule();
        $cutoff = $this->getCutoffTime($sched);

        $open = substr($sched->opening_time, 0, 5);
        $breakStart = substr($sched->break_start_time, 0, 5);
        $breakEnd = substr($sched->break_end_time, 0, 5);

        return [
            'morning' => [
                'start' => $open,
                'end' => $breakStart,
                'label' => "{$open} a {$breakStart}",
            ],
            'afternoon' => [
                'start' => $breakEnd,
                'end' => $cutoff,
                'label' => "{$breakEnd} a {$cutoff}",
            ],
        ];
    }

    /**
     * Evalúa si una fecha y hora determinadas permiten la recepción de pedidos.
     * Si no se proporciona fecha/hora, utiliza el momento actual en America/Merida.
     *
     * @param DateTimeInterface|string|null $dateTime
     * @param BranchSchedule|null $schedule
     * @return array{can_receive: bool, reason: string, message: string, evaluated_time: string, timezone: string}
     */
    public function checkOrderReception($dateTime = null, ?BranchSchedule $schedule = null): array
    {
        $sched = $schedule ?? $this->getSchedule();

        // 1. Normalizar la fecha y hora a la zona horaria America/Merida
        if ($dateTime instanceof Carbon) {
            $dt = $dateTime->copy()->setTimezone(self::TIMEZONE);
        } elseif ($dateTime instanceof DateTimeInterface) {
            $dt = Carbon::instance($dateTime)->setTimezone(self::TIMEZONE);
        } elseif (is_string($dateTime)) {
            $trimmed = trim($dateTime);
            if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $trimmed)) {
                $dt = Carbon::createFromFormat('H:i', substr($trimmed, 0, 5), self::TIMEZONE);
            } else {
                $dt = Carbon::parse($trimmed, self::TIMEZONE)->setTimezone(self::TIMEZONE);
            }
        } else {
            $dt = Carbon::now(self::TIMEZONE);
        }

        $currentTime = $dt->format('H:i');
        $open = substr($sched->opening_time, 0, 5);
        $breakStart = substr($sched->break_start_time, 0, 5);
        $breakEnd = substr($sched->break_end_time, 0, 5);
        $close = substr($sched->closing_time, 0, 5);
        $cutoff = $this->getCutoffTime($sched);

        // 2. REGLA: Después del cierre debe prevalecer el mensaje de sucursal cerrada
        if ($currentTime >= $close) {
            return [
                'can_receive' => false,
                'is_store_open' => false,
                'reason' => 'store_closed',
                'message' => 'La sucursal está cerrada.',
                'evaluated_time' => $currentTime,
                'timezone' => self::TIMEZONE,
            ];
        }

        // 3. Antes de abrir
        if ($currentTime < $open) {
            return [
                'can_receive' => false,
                'is_store_open' => false,
                'reason' => 'not_open_yet',
                'message' => 'La sucursal todavía no abre.',
                'evaluated_time' => $currentTime,
                'timezone' => self::TIMEZONE,
            ];
        }

        // 4. Durante el descanso
        if ($currentTime >= $breakStart && $currentTime < $breakEnd) {
            return [
                'can_receive' => false,
                'is_store_open' => false,
                'reason' => 'on_break',
                'message' => 'La sucursal está en descanso.',
                'evaluated_time' => $currentTime,
                'timezone' => self::TIMEZONE,
            ];
        }

        // 5. Desde la hora límite de pedidos en adelante (antes del cierre: sucursal sigue abierta en planta)
        if ($currentTime >= $cutoff && $currentTime < $close) {
            return [
                'can_receive' => false,
                'is_store_open' => true,
                'reason' => 'orders_closed_for_today',
                'message' => 'La recepción de pedidos terminó por hoy.',
                'evaluated_time' => $currentTime,
                'timezone' => self::TIMEZONE,
            ];
        }

        // 6. Horarios válidos de recepción
        $inMorning = ($currentTime >= $open && $currentTime < $breakStart);
        $inAfternoon = ($currentTime >= $breakEnd && $currentTime < $cutoff);

        if ($inMorning || $inAfternoon) {
            return [
                'can_receive' => true,
                'is_store_open' => true,
                'reason' => 'allowed',
                'message' => 'Recepción de pedidos disponible.',
                'evaluated_time' => $currentTime,
                'timezone' => self::TIMEZONE,
            ];
        }

        // Fallback defensivo
        return [
            'can_receive' => false,
            'is_store_open' => false,
            'reason' => 'store_closed',
            'message' => 'La sucursal está cerrada.',
            'evaluated_time' => $currentTime,
            'timezone' => self::TIMEZONE,
        ];
    }

    /**
     * Comprueba si la sucursal física está abierta para atención presencial.
     */
    public function isStoreOpen($dateTime = null, ?BranchSchedule $schedule = null): bool
    {
        return (bool) ($this->checkOrderReception($dateTime, $schedule)['is_store_open'] ?? false);
    }

    /**
     * Comprueba si una fecha y hora dadas permiten recibir pedidos (valor booleano directo).
     */
    public function canReceiveOrdersAt($dateTime = null, ?BranchSchedule $schedule = null): bool
    {
        return $this->checkOrderReception($dateTime, $schedule)['can_receive'];
    }

    /**
     * Consulta centralizada de presentaciones activas y sus precios.
     * No devuelve cero cuando un precio esté sin configurar (retorna null explícito).
     */
    public function getActivePresentations(): Collection
    {
        return BottlePresentation::active()
            ->orderBy('capacity', 'desc')
            ->get()
            ->map(function (BottlePresentation $presentation) {
                return [
                    'id' => $presentation->id,
                    'name' => $presentation->name,
                    'capacity' => (float) $presentation->capacity,
                    'unit' => $presentation->unit,
                    'formatted_capacity' => $presentation->formatted_capacity,
                    // Precios en formato decimal o null explícito (nunca 0 como sustituto)
                    'refill_price' => $presentation->refill_price !== null ? (float) $presentation->refill_price : null,
                    'has_refill_price' => $presentation->has_refill_price,
                    'store_price' => $presentation->store_price !== null ? (float) $presentation->store_price : null,
                    'has_store_price' => $presentation->has_store_price,
                    'new_bottle_price' => $presentation->new_bottle_price !== null ? (float) $presentation->new_bottle_price : null,
                    'has_new_bottle_price' => $presentation->has_new_bottle_price,
                    'image_path' => $presentation->image_path,
                    'image_url' => $presentation->image_url,
                    'is_available_for_delivery' => (bool) $presentation->is_available_for_delivery,
                    'is_active' => $presentation->is_active,
                ];
            });
    }

    /**
     * Resumen completo del horario para vista previa y diagnósticos.
     */
    public function getScheduleSummary(?BranchSchedule $schedule = null): array
    {
        $sched = $schedule ?? $this->getSchedule();
        $cutoff = $this->getCutoffTime($sched);
        $open = substr($sched->opening_time, 0, 5);
        $breakStart = substr($sched->break_start_time, 0, 5);
        $breakEnd = substr($sched->break_end_time, 0, 5);
        $close = substr($sched->closing_time, 0, 5);

        return [
            'opening_time' => $open,
            'break_start_time' => $breakStart,
            'break_end_time' => $breakEnd,
            'closing_time' => $close,
            'order_cutoff_advance_minutes' => (int) $sched->order_cutoff_advance_minutes,
            'order_cutoff_time' => $cutoff,
            'morning_attention' => "{$open} a {$breakStart}",
            'break_period' => "{$breakStart} a {$breakEnd}",
            'afternoon_attention' => "{$breakEnd} a {$close}",
            'order_intervals' => [
                "{$open} a {$breakStart}",
                "{$breakEnd} a {$cutoff}",
            ],
            'days_applied' => 'Lunes a Domingo',
            'timezone' => self::TIMEZONE,
        ];
    }
}
