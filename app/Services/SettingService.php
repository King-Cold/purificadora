<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\BottlePresentation;

class SettingService
{
    public const KEY_MAX_PENDING_PAYMENT_BOTTLES = 'max_pending_payment_bottles';
    public const DEFAULT_MAX_PENDING_PAYMENT_BOTTLES = 5;

    /**
     * Obtiene el límite máximo general de botellones pendientes de pago por cliente.
     */
    public function getMaxPendingPaymentBottles(): int
    {
        $val = AppSetting::get(self::KEY_MAX_PENDING_PAYMENT_BOTTLES, (string) self::DEFAULT_MAX_PENDING_PAYMENT_BOTTLES);
        return max(0, (int) $val);
    }

    /**
     * Actualiza el límite máximo de botellones pendientes de pago.
     */
    public function setMaxPendingPaymentBottles(int $limit): void
    {
        AppSetting::set(
            self::KEY_MAX_PENDING_PAYMENT_BOTTLES,
            max(0, $limit),
            'Límite máximo general de botellones de 20L pendientes de pago por cliente.'
        );
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return AppSetting::get($key, $default);
    }

    public function set(string $key, mixed $value, ?string $description = null): void
    {
        AppSetting::set($key, $value, $description);
    }

    /**
     * Obtiene el precio vigente de llenado en planta para botellón de 20L.
     */
    public function getCurrentStorePrice(): float
    {
        $pres = BottlePresentation::getTwentyLiters();
        return (float) ($pres->store_price ?? 10.00);
    }

    /**
     * Obtiene el precio vigente de recarga para botellón de 20L a domicilio desde Configuración General.
     */
    public function getCurrentRefillPrice(): float
    {
        $pres = BottlePresentation::getTwentyLiters();
        return (float) ($pres->refill_price ?? 15.00);
    }
}
