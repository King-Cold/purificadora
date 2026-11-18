<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\BottlePresentation;
use Illuminate\Support\Str;

class SettingService
{
    public const KEY_MAX_PENDING_PAYMENT_BOTTLES = 'max_pending_payment_bottles';
    public const DEFAULT_MAX_PENDING_PAYMENT_BOTTLES = 5;

    public const KEY_EXCHANGE_BOTTLES_TARGET = 'exchange_bottles_target';
    public const KEY_EXCHANGE_BOTTLES_TARGET_UPDATED_BY = 'exchange_bottles_target_updated_by';
    public const KEY_EXCHANGE_BOTTLES_TARGET_UPDATED_AT = 'exchange_bottles_target_updated_at';
    public const DEFAULT_EXCHANGE_BOTTLES_TARGET = 40;

    public const KEY_DELIVERY_ZONES = 'delivery_zones';
    public const BASE_ZONE_KEYS = ['north', 'south', 'east', 'west'];

    public const DEFAULT_DELIVERY_ZONES = [
        ['key' => 'north', 'name' => 'Norte', 'color' => '#0071BA', 'is_active' => true],
        ['key' => 'south', 'name' => 'Sur', 'color' => '#26845B', 'is_active' => true],
        ['key' => 'east', 'name' => 'Este', 'color' => '#1E1545', 'is_active' => true],
        ['key' => 'west', 'name' => 'Oeste', 'color' => '#D99A27', 'is_active' => true],
    ];

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

    /**
     * Devuelve el catálogo de zonas configuradas (garantizando las 4 zonas base editables).
     *
     * @return array<int, array{key: string, name: string, color: string, is_active: bool, is_base: bool}>
     */
    public function getDeliveryZones(bool $onlyActive = false): array
    {
        $raw = AppSetting::get(self::KEY_DELIVERY_ZONES);
        $decoded = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : null);

        $defaultsByKey = collect(self::DEFAULT_DELIVERY_ZONES)->keyBy('key');
        $zones = [];
        $seenKeys = [];

        if (is_array($decoded)) {
            foreach ($decoded as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $key = trim((string) ($item['key'] ?? ''));
                $name = trim((string) ($item['name'] ?? ''));
                if ($key === '' || $name === '' || isset($seenKeys[$key])) {
                    continue;
                }

                $color = trim((string) ($item['color'] ?? ''));
                if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
                    $color = $defaultsByKey->get($key)['color'] ?? '#0071BA';
                }

                $zones[] = [
                    'key' => $key,
                    'name' => mb_substr($name, 0, 60),
                    'color' => $color,
                    'is_active' => array_key_exists('is_active', $item) ? (bool) $item['is_active'] : true,
                    'is_base' => in_array($key, self::BASE_ZONE_KEYS, true),
                ];
                $seenKeys[$key] = true;
            }
        }

        // Garantizar que las 4 zonas base siempre existan aunque aún no exista configuración guardada
        foreach (self::DEFAULT_DELIVERY_ZONES as $base) {
            if (!isset($seenKeys[$base['key']])) {
                $zones[] = [
                    'key' => $base['key'],
                    'name' => $base['name'],
                    'color' => $base['color'],
                    'is_active' => $base['is_active'],
                    'is_base' => true,
                ];
                $seenKeys[$base['key']] = true;
            }
        }

        if ($onlyActive) {
            return array_values(array_filter($zones, fn ($z) => $z['is_active']));
        }

        return $zones;
    }

    /**
     * Mapa asociativo key => name de las zonas de reparto.
     *
     * @return array<string, string>
     */
    public function getDeliveryZonesMap(bool $onlyActive = false): array
    {
        $map = [];
        foreach ($this->getDeliveryZones($onlyActive) as $zone) {
            $map[$zone['key']] = $zone['name'];
        }
        return $map;
    }

    /**
     * Claves válidas de zonas configuradas.
     *
     * @return array<int, string>
     */
    public function getDeliveryZoneKeys(bool $onlyActive = false): array
    {
        return array_column($this->getDeliveryZones($onlyActive), 'key');
    }

    /**
     * Normaliza una zona (clave, alias en español o nombre configurado) a su clave canónica.
     */
    public function normalizeDeliveryZoneKey(?string $raw): string
    {
        $z = mb_strtolower(trim((string) $raw));
        if ($z === '') {
            return 'none';
        }

        $legacyAliases = [
            'norte' => 'north',
            'sur' => 'south',
            'este' => 'east',
            'oeste' => 'west',
        ];
        if (isset($legacyAliases[$z])) {
            return $legacyAliases[$z];
        }

        foreach ($this->getDeliveryZones() as $zone) {
            if (mb_strtolower($zone['key']) === $z || mb_strtolower($zone['name']) === $z) {
                return $zone['key'];
            }
        }

        return 'none';
    }

    /**
     * Devuelve el nombre legible configurado para una zona.
     */
    public function resolveDeliveryZoneLabel(?string $raw): string
    {
        $key = $this->normalizeDeliveryZoneKey($raw);
        if ($key === 'none') {
            return 'Sin zona';
        }

        $map = $this->getDeliveryZonesMap();
        return $map[$key] ?? 'Sin zona';
    }

    /**
     * Guarda las zonas editables preservando las 4 claves base y generando slug único para nuevas zonas.
     *
     * @return array<int, array{key: string, name: string, color: string, is_active: bool, is_base: bool}>
     */
    public function saveDeliveryZones(array $zonesInput): array
    {
        $defaultsByKey = collect(self::DEFAULT_DELIVERY_ZONES)->keyBy('key');
        $clean = [];
        $usedKeys = [];

        foreach ($zonesInput as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $rawKey = trim((string) ($row['key'] ?? ''));
            if ($rawKey !== '' && preg_match('/^[a-z0-9_]+$/', $rawKey) && !isset($usedKeys[$rawKey])) {
                $key = $rawKey;
            } else {
                $baseSlug = Str::slug($name, '_');
                $baseSlug = $baseSlug !== '' ? substr($baseSlug, 0, 36) : 'zona';
                $key = $baseSlug;
                $suffix = 2;
                while (isset($usedKeys[$key]) || in_array($key, ['all', 'none', 'no_zone', 'other_municipality'], true)) {
                    $key = substr($baseSlug, 0, 32) . '_' . $suffix++;
                }
            }

            $color = trim((string) ($row['color'] ?? ''));
            if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
                $color = $defaultsByKey->get($key)['color'] ?? '#0071BA';
            }

            $clean[] = [
                'key' => $key,
                'name' => mb_substr($name, 0, 60),
                'color' => $color,
                'is_active' => array_key_exists('is_active', $row) ? (bool) $row['is_active'] : true,
            ];
            $usedKeys[$key] = true;
        }

        $existingBase = collect($this->getDeliveryZones())->keyBy('key');
        foreach (self::DEFAULT_DELIVERY_ZONES as $base) {
            if (!isset($usedKeys[$base['key']])) {
                $prev = $existingBase->get($base['key'], $base);
                $clean[] = [
                    'key' => $base['key'],
                    'name' => $prev['name'],
                    'color' => $prev['color'],
                    'is_active' => (bool) ($prev['is_active'] ?? true),
                ];
                $usedKeys[$base['key']] = true;
            }
        }

        AppSetting::set(
            self::KEY_DELIVERY_ZONES,
            json_encode($clean, JSON_UNESCAPED_UNICODE),
            'Catálogo editable de zonas disponibles de entrega en Puritronic.'
        );

        return $this->getDeliveryZones();
    }
}
