<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

class OrderFolioGenerator
{
    /**
     * Prefijo para los folios de pedido.
     */
    public const PREFIX = 'PED-';

    /**
     * Longitud mínima de dígitos con ceros a la izquierda.
     */
    public const MIN_DIGITS = 4;

    /**
     * Genera el siguiente folio consecutivo de forma atómica y segura contra concurrencia.
     * Ejemplo: 'PED-0001', 'PED-0002', ..., 'PED-9999', 'PED-10000'.
     */
    public static function generate(): string
    {
        if (DB::transactionLevel() === 0) {
            return DB::transaction(fn () => self::getNextFolio());
        }

        return self::getNextFolio();
    }

    /**
     * Obtiene el siguiente folio aplicando bloqueo pesimista.
     */
    private static function getNextFolio(): string
    {
        $lastOrder = Order::lockForUpdate()
            ->where('folio', 'LIKE', self::PREFIX . '%')
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = 1;

        if ($lastOrder && preg_match('/^' . preg_quote(self::PREFIX, '/') . '(\d+)$/', $lastOrder->folio, $matches)) {
            $lastNumber = (int) $matches[1];
            $nextNumber = $lastNumber + 1;
        }

        // Bucle de seguridad para garantizar unicidad absoluta ante cualquier colisión
        do {
            $candidateFolio = self::PREFIX . str_pad((string) $nextNumber, self::MIN_DIGITS, '0', STR_PAD_LEFT);
            $exists = Order::where('folio', $candidateFolio)->exists();
            if ($exists) {
                $nextNumber++;
            }
        } while ($exists);

        return $candidateFolio;
    }
}
