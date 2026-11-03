<?php

namespace App\Services;

use App\Exceptions\OrderBusinessRuleException;
use App\Models\Customer;
use App\Models\CustomerPromotion;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class WeeklyPromotionService
{
    public const TIMEZONE = 'America/Merida';
    public const REQUIRED_PAID_BOTTLES = 9;

    /**
     * Resuelve las fechas de inicio, fin y clave de la semana actual en America/Merida.
     */
    public function getWeekBounds(?Carbon $referenceDate = null): array
    {
        $date = $referenceDate ? $referenceDate->copy()->setTimezone(self::TIMEZONE) : now(self::TIMEZONE);
        $startOfWeek = $date->copy()->startOfWeek(Carbon::MONDAY);
        $endOfWeek = $date->copy()->endOfWeek(Carbon::SUNDAY);
        $weekIdentifier = $startOfWeek->format('o-\WW');

        return [$startOfWeek, $endOfWeek, $weekIdentifier];
    }

    /**
     * Obtiene el total de botellones de 20L pagados por el cliente en la semana calendario.
     * Los fiados cuentan en la semana en que se realiza el pago en efectivo (sin duplicar).
     */
    public function getPaidBottlesInWeek(Customer $customer, ?Carbon $referenceDate = null): int
    {
        [$startOfWeek, $endOfWeek] = $this->getWeekBounds($referenceDate);

        return (int) OrderPayment::where('customer_id', $customer->id)
            ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
            ->sum('bottles_covered_count');
    }

    /**
     * Comprueba si el cliente tiene un beneficio gratuito disponible o elegible para la semana actual.
     */
    public function checkOrGenerateWeeklyPromotion(Customer $customer, ?Carbon $referenceDate = null): ?CustomerPromotion
    {
        [$startOfWeek, $endOfWeek, $weekIdentifier] = $this->getWeekBounds($referenceDate);

        return DB::transaction(function () use ($customer, $startOfWeek, $endOfWeek, $weekIdentifier) {
            $existing = CustomerPromotion::where('customer_id', $customer->id)
                ->where('week_identifier', $weekIdentifier)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            // Contar botellones pagados en la semana
            $paidCount = (int) OrderPayment::where('customer_id', $customer->id)
                ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
                ->sum('bottles_covered_count');

            if ($paidCount >= self::REQUIRED_PAID_BOTTLES) {
                return CustomerPromotion::create([
                    'customer_id' => $customer->id,
                    'week_identifier' => $weekIdentifier,
                    'week_start_date' => $startOfWeek->toDateString(),
                    'week_end_date' => $endOfWeek->toDateString(),
                    'eligible_at' => now(self::TIMEZONE),
                    'expires_at' => $endOfWeek,
                    'status' => CustomerPromotion::STATUS_AVAILABLE,
                ]);
            }

            return null;
        });
    }

    /**
     * Canjea el botellón gratuito en un pedido y detalle específico.
     */
    public function redeemPromotion(
        CustomerPromotion $promotion,
        Order $order,
        OrderItem $item
    ): CustomerPromotion {
        if (!$promotion->isAvailable()) {
            throw new OrderBusinessRuleException('El beneficio de la promoción no se encuentra disponible o ha vencido.', 'promotion', 422);
        }

        return DB::transaction(function () use ($promotion, $order, $item) {
            $lockedPromotion = CustomerPromotion::where('id', $promotion->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!$lockedPromotion->isAvailable()) {
                throw new OrderBusinessRuleException('El beneficio de la promoción ya ha sido canjeado o ha expirado.', 'promotion', 422);
            }

            $lockedPromotion->update([
                'status' => CustomerPromotion::STATUS_REDEEMED,
                'redeemed_order_id' => $order->id,
                'redeemed_order_item_id' => $item->id,
                'redeemed_at' => now(self::TIMEZONE),
            ]);

            return $lockedPromotion;
        });
    }

    /**
     * Devuelve el progreso detallado de la promoción semanal 9+1 para visualización en el panel móvil.
     */
    public function getWeeklyProgress(Customer $customer, ?Carbon $referenceDate = null): array
    {
        [$startOfWeek, $endOfWeek, $weekIdentifier] = $this->getWeekBounds($referenceDate);
        $paidCount = $this->getPaidBottlesInWeek($customer, $referenceDate);
        $promo = CustomerPromotion::where('customer_id', $customer->id)
            ->where('week_identifier', $weekIdentifier)
            ->first();

        $status = 'in_progress';
        $label = "{$paidCount} de " . self::REQUIRED_PAID_BOTTLES . " botellones pagados esta semana";
        $canRedeem = false;
        $expiresAt = $promo?->expires_at ?? $endOfWeek;

        if ($promo && $promo->status === CustomerPromotion::STATUS_REDEEMED) {
            $status = 'redeemed';
            $label = 'Promoción semanal canjeada (10.º botellón gratuito entregado)';
        } elseif ($promo && ($promo->status === CustomerPromotion::STATUS_EXPIRED || now(self::TIMEZONE)->greaterThan($promo->expires_at))) {
            $status = 'expired';
            $label = 'Beneficio semanal vencido (expiró el ' . $promo->expires_at->format('d/m/Y H:i') . ')';
        } elseif (($promo && $promo->isAvailable()) || $paidCount >= self::REQUIRED_PAID_BOTTLES) {
            $status = 'available';
            $label = '¡Promoción disponible! 10.º botellón gratis (requiere envase de cambio)';
            $canRedeem = true;
        }

        return [
            'paid_count' => $paidCount,
            'required' => self::REQUIRED_PAID_BOTTLES,
            'remaining_to_free' => max(0, self::REQUIRED_PAID_BOTTLES - $paidCount),
            'status' => $status,
            'status_label' => match ($status) {
                'available' => 'Disponible',
                'redeemed' => 'Canjeado',
                'expired' => 'Vencido',
                default => 'En progreso',
            },
            'label' => $label,
            'can_redeem' => $canRedeem,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'expires_at_label' => $expiresAt->format('d/m/Y H:i'),
            'week_identifier' => $weekIdentifier,
            'week_start' => $startOfWeek->toDateString(),
            'week_end' => $endOfWeek->toDateString(),
        ];
    }
}

