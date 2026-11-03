<?php

namespace App\Services;

use App\Exceptions\OrderBusinessRuleException;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderCharge;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\PaymentChargeAllocation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DebtAndCreditService
{
    public function __construct(
        protected SettingService $settingService
    ) {}

    /**
     * Obtiene el número total de botellones pendientes de pago acumulados del cliente.
     * No redondea importes parciales: solo unidades enteras cubiertas reducen la cuenta.
     */
    public function getPendingDebtBottles(Customer $customer): int
    {
        $charges = OrderCharge::where('customer_id', $customer->id)
            ->whereIn('status', [OrderCharge::STATUS_PENDING, OrderCharge::STATUS_PARTIALLY_PAID])
            ->get();

        $pendingBottles = 0;
        foreach ($charges as $charge) {
            $unitPrice = (float) $charge->unit_price;
            if ($unitPrice <= 0) {
                continue;
            }
            $coveredUnits = (int) floor((float) $charge->paid_amount / $unitPrice);
            $unpaidUnits = max(0, (int) $charge->quantity_delivered - $coveredUnits);
            $pendingBottles += $unpaidUnits;
        }

        return $pendingBottles;
    }

    /**
     * Obtiene el saldo monetario total pendiente de pago del cliente.
     */
    public function getPendingDebtAmount(Customer $customer): float
    {
        $charges = OrderCharge::where('customer_id', $customer->id)
            ->whereIn('status', [OrderCharge::STATUS_PENDING, OrderCharge::STATUS_PARTIALLY_PAID])
            ->get();

        return (float) $charges->sum(fn ($c) => $c->remainingAmount());
    }

    /**
     * Calcula la información visual y operativa del Semáforo del Repartidor.
     */
    public function getTrafficLight(Customer $customer): array
    {
        $limit = $this->settingService->getMaxPendingPaymentBottles();
        $pending = $this->getPendingDebtBottles($customer);

        if ($limit === 0) {
            return [
                'color' => $pending === 0 ? 'green' : 'red',
                'status' => 'credit_disabled',
                'pending_bottles' => $pending,
                'limit' => 0,
                'label' => $pending === 0 
                    ? 'Fiado deshabilitado'
                    : "Fiado deshabilitado · {$pending} botellones pendientes",
                'can_receive_more_debt' => false,
            ];
        }

        if ($pending === 0) {
            return [
                'color' => 'green',
                'status' => 'no_debt',
                'pending_bottles' => 0,
                'limit' => $limit,
                'label' => 'Al día · Sin pendientes',
                'can_receive_more_debt' => true,
            ];
        }

        if ($pending < $limit) {
            return [
                'color' => 'yellow',
                'status' => 'with_debt',
                'pending_bottles' => $pending,
                'limit' => $limit,
                'label' => "Con deuda · {$pending} de {$limit} botellones pendientes de pago",
                'can_receive_more_debt' => true,
            ];
        }

        return [
            'color' => 'red',
            'status' => 'limit_reached',
            'pending_bottles' => $pending,
            'limit' => $limit,
            'label' => "Límite alcanzado · {$pending} de {$limit} botellones pendientes de pago",
            'can_receive_more_debt' => false,
        ];
    }

    /**
     * Valida si el cliente puede contraer una cantidad adicional de botellones fiados.
     */
    public function canChargeDebt(Customer $customer, int $bottlesToDefer): bool
    {
        if ($bottlesToDefer <= 0) {
            return true;
        }

        $limit = $this->settingService->getMaxPendingPaymentBottles();
        if ($limit <= 0) {
            return false;
        }

        $currentPending = $this->getPendingDebtBottles($customer);
        return ($currentPending + $bottlesToDefer) <= $limit;
    }

    /**
     * Registra un cargo por unidades efectivamente entregadas al cliente.
     */
    public function recordCharge(
        Order $order,
        OrderItem $item,
        int $quantityDelivered,
        float $unitPrice,
        bool $isWaivedPromotion = false
    ): OrderCharge {
        if ($quantityDelivered <= 0) {
            throw new OrderBusinessRuleException('Solo las unidades efectivamente entregadas generan cargo.', 'quantity', 422);
        }

        $totalAmount = $isWaivedPromotion ? 0.00 : (float) round($quantityDelivered * $unitPrice, 2);
        $status = $isWaivedPromotion ? OrderCharge::STATUS_WAIVED_PROMOTION : OrderCharge::STATUS_PENDING;

        return OrderCharge::create([
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'customer_id' => $order->customer_id,
            'bottle_type' => $item->type,
            'quantity_delivered' => $quantityDelivered,
            'unit_price' => $unitPrice,
            'total_amount' => $totalAmount,
            'paid_amount' => 0.00,
            'status' => $status,
        ]);
    }

    /**
     * Calcula el importe exacto requerido para liquidar una cantidad entera de botellones
     * utilizando los precios históricos de los cargos pendientes más antiguos.
     */
    public function calculateExactAmountForBottles(Customer $customer, int $bottlesCount): array
    {
        if ($bottlesCount <= 0) {
            throw new OrderBusinessRuleException('La cantidad de botellones a liquidar debe ser al menos 1.', 'bottles', 422);
        }

        $pendingCharges = OrderCharge::where('customer_id', $customer->id)
            ->whereIn('status', [OrderCharge::STATUS_PENDING, OrderCharge::STATUS_PARTIALLY_PAID])
            ->orderBy('created_at', 'asc')
            ->get();

        $plan = [];
        $neededBottles = $bottlesCount;
        $totalExactAmount = 0.00;

        foreach ($pendingCharges as $charge) {
            if ($neededBottles <= 0) {
                break;
            }

            $unitPrice = (float) $charge->unit_price;
            if ($unitPrice <= 0) {
                continue;
            }

            $alreadyPaidUnits = (int) floor((float) $charge->paid_amount / $unitPrice);
            $unpaidUnitsInCharge = max(0, (int) $charge->quantity_delivered - $alreadyPaidUnits);

            if ($unpaidUnitsInCharge <= 0) {
                continue;
            }

            $unitsFromThisCharge = min($neededBottles, $unpaidUnitsInCharge);
            $amountFromThisCharge = round($unitsFromThisCharge * $unitPrice, 2);

            $plan[] = [
                'charge' => $charge,
                'bottles' => $unitsFromThisCharge,
                'amount' => $amountFromThisCharge,
            ];

            $totalExactAmount += $amountFromThisCharge;
            $neededBottles -= $unitsFromThisCharge;
        }

        if ($neededBottles > 0) {
            $totalPending = $this->getPendingDebtBottles($customer);
            throw new OrderBusinessRuleException(
                "No se pueden liquidar {$bottlesCount} botellón(es). El cliente solo tiene {$totalPending} botellón(es) pendiente(s) de pago.",
                'bottles',
                422
            );
        }

        return [
            'exact_amount' => round($totalExactAmount, 2),
            'bottles_count' => $bottlesCount,
            'plan' => $plan,
        ];
    }

    /**
     * Registra un pago en efectivo liquidando exclusivamente botellones completos.
     * Rechaza pagos que dejen una unidad parcialmente pagada.
     * Si el efectivo recibido supera el importe a cobrar, registra cambio devuelto y abona solo el importe exacto.
     */
    public function recordPayment(
        Customer $customer,
        float $cashTendered,
        User $receivedBy,
        ?Order $order = null,
        ?string $notes = null,
        ?int $bottlesToSettle = null
    ): OrderPayment {
        if ($cashTendered <= 0) {
            throw new OrderBusinessRuleException('El efectivo recibido debe ser mayor a cero.', 'cash_tendered', 422);
        }

        return DB::transaction(function () use ($customer, $cashTendered, $receivedBy, $order, $notes, $bottlesToSettle) {
            $pendingCharges = OrderCharge::where('customer_id', $customer->id)
                ->whereIn('status', [OrderCharge::STATUS_PENDING, OrderCharge::STATUS_PARTIALLY_PAID])
                ->orderBy('created_at', 'asc')
                ->lockForUpdate()
                ->get();

            // CASO A: No hay cargos pendientes en BD (pago directo sin deuda previa)
            if ($pendingCharges->isEmpty()) {
                $defaultUnitPrice = 15.00;
                if ($bottlesToSettle !== null) {
                    $exactAmount = round($bottlesToSettle * $defaultUnitPrice, 2);
                    if ($cashTendered < $exactAmount) {
                        throw new OrderBusinessRuleException(
                            "El efectivo recibido (\${$cashTendered}) es insuficiente para liquidar {$bottlesToSettle} botellón(es) (\${$exactAmount}).",
                            'cash_tendered',
                            422
                        );
                    }
                    $changeReturned = round($cashTendered - $exactAmount, 2);
                    $settledBottles = $bottlesToSettle;
                } else {
                    if (fmod($cashTendered, $defaultUnitPrice) != 0.0) {
                        throw new OrderBusinessRuleException(
                            'No se permiten abonos parciales de un botellón. El pago debe liquidar unidades completas.',
                            'amount',
                            422
                        );
                    }
                    $settledBottles = (int) ($cashTendered / $defaultUnitPrice);
                    $exactAmount = $cashTendered;
                    $changeReturned = 0.00;
                }

                return OrderPayment::create([
                    'order_id' => $order?->id,
                    'customer_id' => $customer->id,
                    'received_by_user_id' => $receivedBy->id,
                    'amount' => $exactAmount, // Solo el importe exacto liquidado
                    'cash_tendered' => $cashTendered,
                    'change_returned' => $changeReturned,
                    'payment_method' => 'cash',
                    'bottles_covered_count' => $settledBottles,
                    'notes' => $notes,
                ]);
            }

            // CASO B: Hay cargos pendientes con precios históricos
            if ($bottlesToSettle !== null) {
                $calc = $this->calculateExactAmountForBottles($customer, $bottlesToSettle);
                $exactAmount = $calc['exact_amount'];
                $plan = $calc['plan'];

                if ($cashTendered < $exactAmount) {
                    throw new OrderBusinessRuleException(
                        "El efectivo recibido (\${$cashTendered}) es insuficiente para liquidar {$bottlesToSettle} botellón(es) (\${$exactAmount}).",
                        'cash_tendered',
                        422
                    );
                }

                $changeReturned = round($cashTendered - $exactAmount, 2);
                $settledBottles = $bottlesToSettle;
            } else {
                // Si no se especificó botellones, determinar si cashTendered corresponde exactamente a unidades completas acumuladas
                $accumulatedExact = 0.00;
                $accumulatedBottles = 0;
                $foundExactMatch = false;

                foreach ($pendingCharges as $charge) {
                    $unitPrice = (float) $charge->unit_price;
                    if ($unitPrice <= 0) {
                        continue;
                    }
                    $alreadyPaidUnits = (int) floor((float) $charge->paid_amount / $unitPrice);
                    $unpaidUnitsInCharge = max(0, (int) $charge->quantity_delivered - $alreadyPaidUnits);

                    for ($i = 1; $i <= $unpaidUnitsInCharge; $i++) {
                        $accumulatedExact = round($accumulatedExact + $unitPrice, 2);
                        $accumulatedBottles++;

                        if (abs($accumulatedExact - $cashTendered) < 0.001) {
                            $foundExactMatch = true;
                            break 2;
                        }
                    }
                }

                if (!$foundExactMatch) {
                    throw new OrderBusinessRuleException(
                        'No se permiten abonos parciales de un botellón. El importe recibido no coincide con la liquidación exacta de unidades completas según los precios históricos.',
                        'amount',
                        422
                    );
                }

                $exactAmount = $cashTendered;
                $changeReturned = 0.00;
                $settledBottles = $accumulatedBottles;
                $calc = $this->calculateExactAmountForBottles($customer, $settledBottles);
                $plan = $calc['plan'];
            }

            $payment = OrderPayment::create([
                'order_id' => $order?->id,
                'customer_id' => $customer->id,
                'received_by_user_id' => $receivedBy->id,
                'amount' => $exactAmount, // Registra como pago únicamente el importe exacto liquidado
                'cash_tendered' => $cashTendered,
                'change_returned' => $changeReturned,
                'payment_method' => 'cash',
                'bottles_covered_count' => $settledBottles,
                'notes' => $notes,
            ]);

            foreach ($plan as $item) {
                /** @var OrderCharge $charge */
                $charge = $item['charge'];
                $amountToApply = $item['amount'];
                $bottlesInThisCharge = $item['bottles'];

                $newPaidAmount = round((float) $charge->paid_amount + $amountToApply, 2);
                $isFullyPaid = ($newPaidAmount >= (float) $charge->total_amount);

                $charge->update([
                    'paid_amount' => $newPaidAmount,
                    'status' => $isFullyPaid ? OrderCharge::STATUS_PAID : OrderCharge::STATUS_PARTIALLY_PAID,
                ]);

                PaymentChargeAllocation::create([
                    'order_payment_id' => $payment->id,
                    'order_charge_id' => $charge->id,
                    'amount_applied' => $amountToApply,
                    'bottles_count' => $bottlesInThisCharge,
                    'created_at' => now(),
                ]);
            }

            return $payment;
        });
    }
}
