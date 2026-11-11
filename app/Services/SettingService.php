<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\BottlePresentation;

class SettingService
{
    public const KEY_MAX_PENDING_PAYMENT_BOTTLES = 'max_pending_payment_bottles';
    public const DEFAULT_MAX_PENDING_PAYMENT_BOTTLES = 5;

    public const KEY_EXCHANGE_BOTTLES_TARGET = 'exchange_bottles_target';
    public const KEY_EXCHANGE_BOTTLES_TARGET_UPDATED_BY = 'exchange_bottles_target_updated_by';
    public const KEY_EXCHANGE_BOTTLES_TARGET_UPDATED_AT = 'exchange_bottles_target_updated_at';
    public const DEFAULT_EXCHANGE_BOTTLES_TARGET = 40;

    /**
     * Obtiene la meta operativa de botellones de cambio de 20L.
     */
    public function getExchangeBottlesTarget(): int
    {
        $val = AppSetting::get(self::KEY_EXCHANGE_BOTTLES_TARGET, (string) self::DEFAULT_EXCHANGE_BOTTLES_TARGET);
        return max(0, (int) $val);
    }

    /**
     * Obtiene metadatos de la última edición de la meta de cambios.
     */
    public function getExchangeBottlesTargetMetadata(): array
    {
        return [
            'target' => $this->getExchangeBottlesTarget(),
            'updated_by' => AppSetting::get(self::KEY_EXCHANGE_BOTTLES_TARGET_UPDATED_BY),
            'updated_at' => AppSetting::get(self::KEY_EXCHANGE_BOTTLES_TARGET_UPDATED_AT),
        ];
    }

    /**
     * Actualiza la meta operativa de botellones de cambio de 20L.
     */
    public function setExchangeBottlesTarget(int $target, ?string $updatedByName = null): void
    {
        AppSetting::set(
            self::KEY_EXCHANGE_BOTTLES_TARGET,
            max(0, $target),
            'Meta operativa total de botellones de cambio de 20L en el circuito.'
        );

        if ($updatedByName !== null) {
            AppSetting::set(
                self::KEY_EXCHANGE_BOTTLES_TARGET_UPDATED_BY,
                $updatedByName,
                'Usuario que actualizó por última vez la meta de botellones de cambio.'
            );
        }

        AppSetting::set(
            self::KEY_EXCHANGE_BOTTLES_TARGET_UPDATED_AT,
            now('America/Merida')->format('d/m/Y H:i'),
            'Fecha y hora de última actualización de la meta de botellones de cambio.'
        );
    }

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
