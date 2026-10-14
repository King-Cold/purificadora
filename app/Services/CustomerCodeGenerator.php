<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class CustomerCodeGenerator
{
    /**
     * Prefijo para los códigos de cliente.
     */
    public const PREFIX = 'CLI-';

    /**
     * Longitud de los dígitos numéricos con ceros a la izquierda.
     */
    public const DIGITS = 4;

    /**
     * Genera el siguiente código de cliente de forma atómica y segura contra concurrencia.
     * Ejemplo de salida: 'CLI-0001', 'CLI-0002', etc.
     */
    public static function generate(): string
    {
        // Si no estamos dentro de una transacción, envolvemos la lectura con bloqueo
        if (DB::transactionLevel() === 0) {
            return DB::transaction(fn () => self::getNextCode());
        }

        return self::getNextCode();
    }

    /**
     * Obtiene el siguiente código aplicando bloqueo pesimista sobre los registros existentes.
     */
    private static function getNextCode(): string
    {
        // Obtenemos el último código numérico bloqueando la fila para actualización
        $lastCustomer = Customer::query()
            ->lockForUpdate()
            ->where('code', 'LIKE', self::PREFIX . '%')
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = 1;

        if ($lastCustomer && preg_match('/^' . preg_quote(self::PREFIX, '/') . '(\d+)$/', $lastCustomer->code, $matches)) {
            $lastNumber = (int) $matches[1];
            $nextNumber = $lastNumber + 1;
        }

        // Aseguramos que no exista (en caso de que los IDs no sean estrictamente correlativos)
        do {
            $candidateCode = self::PREFIX . str_pad((string) $nextNumber, self::DIGITS, '0', STR_PAD_LEFT);
            $exists = Customer::query()->where('code', $candidateCode)->exists();
            if ($exists) {
                $nextNumber++;
            }
        } while ($exists);

        return $candidateCode;
    }
}
