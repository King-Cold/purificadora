<?php

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderCharge;
use App\Models\OrderPayment;
use App\Services\DebtAndCreditService;
use App\Services\OrderService;
use App\Services\WeeklyPromotionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Muestra el panel principal del cliente autenticado con su pedido activo, adeudos y promociones.
     */
    public function __invoke(
        Request $request,
        OrderService $orderService,
        DebtAndCreditService $debtService,
        WeeklyPromotionService $promoService
    ): Response {
        $customer = $request->user()?->customer;
        $address = $customer?->address;
        $activeOrder = $customer ? $orderService->getActiveOrderForCustomer($customer) : null;

        if ($activeOrder) {
            $activeOrder->loadMissing(['items', 'deliveryRoute']);
        }

        $pendingDebtBottles = $customer ? $debtService->getPendingDebtBottles($customer) : 0;
        $pendingDebtAmount = $customer ? $debtService->getPendingDebtAmount($customer) : 0.0;
        $weeklyPromo = $customer ? $promoService->getWeeklyProgress($customer) : null;

        $paymentHistory = $customer
            ? OrderPayment::where('customer_id', $customer->id)
                ->with('order')
                ->orderBy('id', 'desc')
                ->limit(5)
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'amount' => (float) $p->amount,
                    'cash_tendered' => (float) $p->cash_tendered,
                    'change_returned' => (float) $p->change_returned,
                    'bottles_count' => (int) $p->bottles_covered_count,
                    'order_folio' => $p->order?->folio,
                    'date' => $p->created_at?->setTimezone('America/Merida')->format('d/m/Y H:i'),
                ])
            : [];

        $freeDeliveries = $customer
            ? OrderCharge::where('customer_id', $customer->id)
                ->where('status', OrderCharge::STATUS_WAIVED_PROMOTION)
                ->with('order')
                ->orderBy('id', 'desc')
                ->limit(5)
                ->get()
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'order_folio' => $c->order?->folio,
                    'bottles_count' => (int) $c->quantity_delivered,
                    'date' => $c->created_at?->setTimezone('America/Merida')->format('d/m/Y H:i'),
                ])
            : [];

        $pendingReviewOrder = $customer
            ? Order::where('customer_id', $customer->id)
                ->where('status', '!=', Order::STATUS_CANCELLED)
                ->whereDoesntHave('review')
                ->latest('id')
                ->first()
            : null;

        return Inertia::render('Cliente/Dashboard', [
            'customer' => $customer ? [
                'code' => $customer->code,
                'name' => $customer->name ?: $request->user()?->name,
                'nickname' => $customer->nickname,
                'can_place_orders' => true,
                'fixed_bottles_count' => (int) $customer->fixed_bottles_count,
                'exchange_bottles_count' => (int) $customer->exchange_bottles_count,
                'total_bottles' => (int) $customer->total_bottles,
                'has_address' => !is_null($address),
                'address_summary' => $address ? ($address->street . ($address->exterior_number ? ' #' . $address->exterior_number : '')) : null,
                'between_streets' => $address?->between_streets,
                'reference' => $address?->reference,
                'zone_label' => $address?->zone_label,
            ] : null,
            'activeOrder' => $activeOrder ? [
                'id' => $activeOrder->id,
                'folio' => $activeOrder->folio,
                'status' => $activeOrder->status,
                'status_label' => $activeOrder->status_label,
                'total_bottles' => (int) $activeOrder->total_bottles,
                'fixed_quantity' => (int) $activeOrder->fixed_quantity,
                'exchange_quantity' => (int) $activeOrder->exchange_quantity,
                'operational_progress' => $activeOrder->getOperationalProgress(),
            ] : null,
            'debt' => [
                'pending_bottles' => $pendingDebtBottles,
                'pending_debt_bottles' => $pendingDebtBottles,
                'pending_amount' => $pendingDebtAmount,
                'pending_debt_amount' => $pendingDebtAmount,
                'disclaimer' => 'Son botellones pendientes de pago, no envases por devolver.',
                'clarification_note' => 'Son botellones pendientes de pago, no envases por devolver.',
            ],
            'weekly_promo' => $weeklyPromo,
            'payment_history' => $paymentHistory,
            'free_deliveries' => $freeDeliveries,
            'pendingReviewOrder' => $pendingReviewOrder ? [
                'id' => $pendingReviewOrder->id,
                'folio' => $pendingReviewOrder->folio,
                'total_bottles' => (int) $pendingReviewOrder->total_bottles,
                'created_at' => $pendingReviewOrder->created_at?->copy()->setTimezone('America/Merida')->format('d/m/Y H:i'),
            ] : null,
            'last_updated_at' => now('America/Merida')->format('d/m/Y H:i:s'),
        ]);
    }
}
