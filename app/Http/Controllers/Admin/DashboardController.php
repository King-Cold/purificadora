<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderCharge;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\PlantCounterRefill;
use App\Services\PlantInventoryService;
use App\Services\StoreScheduleService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        protected PlantInventoryService $plantInventoryService,
        protected StoreScheduleService $scheduleService
    ) {}

    /**
     * Renderiza el panel ejecutivo del Administrador con métricas reales en tiempo real.
     */
    public function __invoke(Request $request): Response
    {
        $tz = 'America/Merida';
        $now = Carbon::now($tz);

        $todayStartUtc = $now->copy()->startOfDay()->utc();
        $todayEndUtc = $now->copy()->endOfDay()->utc();
        $yesterdayStartUtc = $now->copy()->subDay()->startOfDay()->utc();
        $yesterdayEndUtc = $now->copy()->subDay()->endOfDay()->utc();

        $monthStartUtc = $now->copy()->startOfMonth()->utc();
        $monthEndUtc = $now->copy()->endOfDay()->utc();
        $prevMonthStartUtc = $now->copy()->subMonthNoOverflow()->startOfMonth()->utc();
        $prevMonthEndUtc = $now->copy()->subMonthNoOverflow()->endOfMonth()->utc();

        // 1. Horario comercial actual
        $receptionStatus = $this->scheduleService->checkOrderReception($now);
        $scheduleSummary = $this->scheduleService->getScheduleSummary();

        // 2. KPIs de Hoy vs Ayer
        $routeRevenueToday = (float) OrderPayment::whereBetween('created_at', [$todayStartUtc, $todayEndUtc])->sum('amount');
        $counterRevenueToday = (float) PlantCounterRefill::whereBetween('created_at', [$todayStartUtc, $todayEndUtc])->sum('total_amount');
        $revenueToday = round($routeRevenueToday + $counterRevenueToday, 2);

        $routeRevenueYesterday = (float) OrderPayment::whereBetween('created_at', [$yesterdayStartUtc, $yesterdayEndUtc])->sum('amount');
        $counterRevenueYesterday = (float) PlantCounterRefill::whereBetween('created_at', [$yesterdayStartUtc, $yesterdayEndUtc])->sum('total_amount');
        $revenueYesterday = round($routeRevenueYesterday + $counterRevenueYesterday, 2);

        $ordersToday = Order::whereBetween('created_at', [$todayStartUtc, $todayEndUtc])->count();
        $ordersYesterday = Order::whereBetween('created_at', [$yesterdayStartUtc, $yesterdayEndUtc])->count();
        $pendingOrdersCount = Order::where('status', Order::STATUS_PENDING)->count();

        $routeBottlesToday = (int) OrderCharge::whereBetween('created_at', [$todayStartUtc, $todayEndUtc])->sum('quantity_delivered');
        $counterBottlesToday = (int) PlantCounterRefill::whereBetween('created_at', [$todayStartUtc, $todayEndUtc])->sum('quantity');
        $bottlesToday = $routeBottlesToday + $counterBottlesToday;

        $routeBottlesYesterday = (int) OrderCharge::whereBetween('created_at', [$yesterdayStartUtc, $yesterdayEndUtc])->sum('quantity_delivered');
        $counterBottlesYesterday = (int) PlantCounterRefill::whereBetween('created_at', [$yesterdayStartUtc, $yesterdayEndUtc])->sum('quantity');
        $bottlesYesterday = $routeBottlesYesterday + $counterBottlesYesterday;

        $totalCustomers = Customer::count();
        $customersThisMonth = Customer::whereBetween('created_at', [$monthStartUtc, $monthEndUtc])->count();
        $customersWithAccount = Customer::whereNotNull('user_id')->count();
        $customersWithoutAccount = $totalCustomers - $customersWithAccount;

        // 3. Series diarias para "Últimos 7 días" y "Esta semana (Lun - Dom)"
        $last7Days = $this->buildDailySeries($now->copy()->subDays(6)->startOfDay(), 7, $now);
        $thisWeek = $this->buildDailySeries($now->copy()->startOfWeek(Carbon::MONDAY), 7, $now);

        $revenueSparkline = array_map(fn ($d) => $d['total_revenue'], $last7Days);
        $ordersSparkline = array_map(fn ($d) => $d['orders_count'], $last7Days);
        $bottlesSparkline = array_map(fn ($d) => $d['total_bottles'], $last7Days);
        $customersSparkline = array_map(fn ($d) => $d['new_customers'], $last7Days);

        // 4. Pedidos recientes (últimos 6)
        $recentOrders = Order::with(['customer', 'items', 'charges'])
            ->latest('id')
            ->take(6)
            ->get()
            ->map(function (Order $order) use ($now, $tz) {
                $createdLocal = $order->created_at?->copy()->setTimezone($tz);
                $timeLabel = '—';
                if ($createdLocal) {
                    if ($createdLocal->isSameDay($now)) {
                        $timeLabel = $createdLocal->format('H:i');
                    } elseif ($createdLocal->isSameDay($now->copy()->subDay())) {
                        $timeLabel = 'Ayer';
                    } else {
                        $timeLabel = $createdLocal->format('d/m');
                    }
                }

                $chargedTotal = (float) $order->charges->sum('total_amount');
                $estimatedTotal = $chargedTotal > 0
                    ? $chargedTotal
                    : (float) $order->items->sum(fn (OrderItem $i) => $i->quantity * (float) $i->unit_price);

                return [
                    'id' => $order->id,
                    'folio' => $order->folio,
                    'customer_name' => $order->customer?->name ?: ($order->customer?->nickname ? "\"{$order->customer->nickname}\"" : 'Cliente particular'),
                    'customer_nickname' => $order->customer?->name && $order->customer?->nickname ? $order->customer->nickname : null,
                    'status' => $order->status,
                    'status_label' => Order::STATUSES[$order->status] ?? $order->status,
                    'total_bottles' => (int) $order->items->sum('quantity'),
                    'total_amount' => round($estimatedTotal, 2),
                    'time_label' => $timeLabel,
                ];
            })
            ->values();

        // 5. Rendimiento por canal de Garrafón 20L en el mes + Distribución de clientes por zona
        $counterMonthBottles = (int) PlantCounterRefill::whereBetween('created_at', [$monthStartUtc, $monthEndUtc])->sum('quantity');
        $counterMonthRevenue = (float) PlantCounterRefill::whereBetween('created_at', [$monthStartUtc, $monthEndUtc])->sum('total_amount');

        $exchangeMonthRow = OrderCharge::whereBetween('created_at', [$monthStartUtc, $monthEndUtc])
            ->where('bottle_type', OrderItem::TYPE_EXCHANGE)
            ->selectRaw('COALESCE(SUM(quantity_delivered), 0) as bottles, COALESCE(SUM(total_amount), 0) as amount')
            ->first();

        $fixedMonthRow = OrderCharge::whereBetween('created_at', [$monthStartUtc, $monthEndUtc])
            ->where('bottle_type', OrderItem::TYPE_FIXED)
            ->selectRaw('COALESCE(SUM(quantity_delivered), 0) as bottles, COALESCE(SUM(total_amount), 0) as amount')
            ->first();

        $exchangeMonthBottles = (int) ($exchangeMonthRow->bottles ?? 0);
        $exchangeMonthRevenue = round((float) ($exchangeMonthRow->amount ?? 0), 2);
        $fixedMonthBottles = (int) ($fixedMonthRow->bottles ?? 0);
        $fixedMonthRevenue = round((float) ($fixedMonthRow->amount ?? 0), 2);

        // Cobertura por zona de clientes registrados
        $zoneCountsRaw = CustomerAddress::query()
            ->whereHas('customer')
            ->select('delivery_zone', DB::raw('COUNT(*) as total'))
            ->groupBy('delivery_zone')
            ->pluck('total', 'delivery_zone');

        $zonesBreakdown = collect(app(\App\Services\SettingService::class)->getDeliveryZones())
            ->map(fn (array $z) => [
                'key' => $z['key'],
                'label' => $z['name'],
                'count' => (int) ($zoneCountsRaw[$z['key']] ?? 0),
                'color' => $z['color'],
            ])
            ->values()
            ->all();

        // 6. Balance financiero del mes + fiados pendientes por cobrar
        $routeMonthRevenue = (float) OrderPayment::whereBetween('created_at', [$monthStartUtc, $monthEndUtc])->sum('amount');
        $monthlyRevenueTotal = round($routeMonthRevenue + $counterMonthRevenue, 2);

        $prevRouteRevenue = (float) OrderPayment::whereBetween('created_at', [$prevMonthStartUtc, $prevMonthEndUtc])->sum('amount');
        $prevCounterRevenue = (float) PlantCounterRefill::whereBetween('created_at', [$prevMonthStartUtc, $prevMonthEndUtc])->sum('total_amount');
        $prevMonthTotal = round($prevRouteRevenue + $prevCounterRevenue, 2);

        $pendingDebtQuery = OrderCharge::whereIn('status', [OrderCharge::STATUS_PENDING, OrderCharge::STATUS_PARTIALLY_PAID]);
        $pendingReceivable = round((float) (clone $pendingDebtQuery)->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0) as pending')->value('pending'), 2);
        $debtorsCount = (int) (clone $pendingDebtQuery)->distinct('customer_id')->count('customer_id');

        // 7. Estado de Planta e Inventario de Cambios 20L
        $exchangeState = $this->plantInventoryService->getExchangeSupervisionState();
        $invSummary = $exchangeState['summary'];

        // 8. Pipeline de pedidos de hoy y activos + Top 5 clientes del mes
        $todayStatusCounts = Order::whereBetween('created_at', [$todayStartUtc, $todayEndUtc])
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $pipeline = [
            'pending' => Order::where('status', Order::STATUS_PENDING)->count(),
            'confirmed' => Order::where('status', Order::STATUS_CONFIRMED)->count(),
            'in_route' => Order::where('status', Order::STATUS_IN_ROUTE)->count(),
            'in_process' => Order::where('status', Order::STATUS_IN_PROCESS)->count(),
            'delivered_today' => (int) ($todayStatusCounts[Order::STATUS_DELIVERED] ?? 0),
            'cancelled_today' => (int) ($todayStatusCounts[Order::STATUS_CANCELLED] ?? 0),
        ];

        $topCustomersMonth = OrderCharge::query()
            ->whereBetween('created_at', [$monthStartUtc, $monthEndUtc])
            ->whereNotNull('customer_id')
            ->select(
                'customer_id',
                DB::raw('SUM(quantity_delivered) as total_bottles'),
                DB::raw('SUM(total_amount) as total_amount')
            )
            ->groupBy('customer_id')
            ->orderByDesc('total_bottles')
            ->take(5)
            ->with('customer:id,name,nickname')
            ->get()
            ->filter(fn ($row) => $row->customer !== null)
            ->map(fn ($row) => [
                'id' => $row->customer->id,
                'name' => $row->customer->name ?: "\"{$row->customer->nickname}\"",
                'nickname' => $row->customer->name && $row->customer->nickname ? $row->customer->nickname : null,
                'total_bottles' => (int) $row->total_bottles,
                'total_amount' => round((float) $row->total_amount, 2),
            ])
            ->values();

        return Inertia::render('Dashboard', [
            'meta' => [
                'date_label' => ucfirst($now->copy()->locale('es')->translatedFormat('l, d \d\e F \d\e Y')),
                'month_label' => ucfirst($now->copy()->locale('es')->translatedFormat('F Y')),
                'updated_at' => $now->format('H:i'),
                'can_receive_orders' => (bool) ($receptionStatus['can_receive'] ?? false),
                'is_store_open' => (bool) ($receptionStatus['is_store_open'] ?? $this->scheduleService->isStoreOpen($now)),
                'schedule_message' => $receptionStatus['message'] ?? '',
                'schedule_hours' => ($scheduleSummary['opening_time'] ?? '08:00') . ' - ' . ($scheduleSummary['closing_time'] ?? '19:00'),
            ],
            'kpis' => [
                'revenue_today' => $revenueToday,
                'revenue_yesterday' => $revenueYesterday,
                'revenue_growth_pct' => $this->calcGrowthPct($revenueToday, $revenueYesterday),
                'route_revenue_today' => round($routeRevenueToday, 2),
                'counter_revenue_today' => round($counterRevenueToday, 2),
                'revenue_sparkline' => $revenueSparkline,

                'orders_today' => $ordersToday,
                'orders_yesterday' => $ordersYesterday,
                'orders_diff' => $ordersToday - $ordersYesterday,
                'pending_orders_count' => $pendingOrdersCount,
                'orders_sparkline' => $ordersSparkline,

                'bottles_today' => $bottlesToday,
                'route_bottles_today' => $routeBottlesToday,
                'counter_bottles_today' => $counterBottlesToday,
                'bottles_yesterday' => $bottlesYesterday,
                'bottles_diff' => $bottlesToday - $bottlesYesterday,
                'bottles_sparkline' => $bottlesSparkline,

                'total_customers' => $totalCustomers,
                'customers_this_month' => $customersThisMonth,
                'customers_with_account' => $customersWithAccount,
                'customers_without_account' => $customersWithoutAccount,
                'customers_sparkline' => $customersSparkline,
            ],
            'weeklyChart' => [
                'this_week' => $thisWeek,
                'last_7_days' => $last7Days,
            ],
            'recentOrders' => $recentOrders,
            'channels' => [
                'counter_bottles' => $counterMonthBottles,
                'counter_revenue' => round($counterMonthRevenue, 2),
                'exchange_bottles' => $exchangeMonthBottles,
                'exchange_revenue' => $exchangeMonthRevenue,
                'fixed_bottles' => $fixedMonthBottles,
                'fixed_revenue' => $fixedMonthRevenue,
                'zones' => $zonesBreakdown,
            ],
            'monthlyFinance' => [
                'total_revenue' => $monthlyRevenueTotal,
                'previous_month_revenue' => $prevMonthTotal,
                'growth_pct' => $this->calcGrowthPct($monthlyRevenueTotal, $prevMonthTotal),
                'route_revenue' => round($routeMonthRevenue, 2),
                'counter_revenue' => round($counterMonthRevenue, 2),
                'pending_receivable' => $pendingReceivable,
                'debtors_count' => $debtorsCount,
            ],
            'operations' => [
                'inventory' => [
                    'available_full_total' => (int) ($invSummary['available_full_total'] ?? 0),
                    'plant_full_available' => (int) ($invSummary['plant_full_available'] ?? 0),
                    'truck_full_available' => (int) ($invSummary['truck_full_available'] ?? 0),
                    'plant_in_preparation' => (int) ($invSummary['plant_in_preparation'] ?? 0),
                    'plant_empty_available' => (int) ($invSummary['plant_empty_available'] ?? 0),
                    'active_total' => (int) ($invSummary['active_total'] ?? 0),
                    'target_quantity' => (int) ($invSummary['target_quantity'] ?? 0),
                    'diff_status' => $invSummary['diff_status'] ?? 'exact',
                    'diff_label' => $invSummary['diff_label'] ?? '',
                    'in_review_total' => (int) ($invSummary['in_review_total'] ?? 0),
                    'pending_incidents_count' => count($exchangeState['pending_incidents'] ?? []),
                ],
                'pipeline' => $pipeline,
            ],
            'topCustomersMonth' => $topCustomersMonth,
        ]);
    }

    /**
     * Construye una serie diaria de 7 días para gráficas y sparklines.
     */
    private function buildDailySeries(Carbon $startLocal, int $days, Carbon $nowLocal): array
    {
        $endLocal = $startLocal->copy()->addDays($days - 1)->endOfDay();
        $startUtc = $startLocal->copy()->startOfDay()->utc();
        $endUtc = $endLocal->copy()->utc();

        $payments = OrderPayment::whereBetween('created_at', [$startUtc, $endUtc])
            ->get(['amount', 'created_at'])
            ->groupBy(fn ($p) => $p->created_at->copy()->setTimezone('America/Merida')->format('Y-m-d'));

        $counterRefills = PlantCounterRefill::whereBetween('created_at', [$startUtc, $endUtc])
            ->get(['quantity', 'total_amount', 'created_at'])
            ->groupBy(fn ($r) => $r->created_at->copy()->setTimezone('America/Merida')->format('Y-m-d'));

        $routeCharges = OrderCharge::whereBetween('created_at', [$startUtc, $endUtc])
            ->get(['quantity_delivered', 'created_at'])
            ->groupBy(fn ($c) => $c->created_at->copy()->setTimezone('America/Merida')->format('Y-m-d'));

        $orders = Order::whereBetween('created_at', [$startUtc, $endUtc])
            ->get(['created_at'])
            ->groupBy(fn ($o) => $o->created_at->copy()->setTimezone('America/Merida')->format('Y-m-d'));

        $customers = Customer::whereBetween('created_at', [$startUtc, $endUtc])
            ->get(['created_at'])
            ->groupBy(fn ($c) => $c->created_at->copy()->setTimezone('America/Merida')->format('Y-m-d'));

        $shortDayNames = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
        $series = [];

        for ($i = 0; $i < $days; $i++) {
            $day = $startLocal->copy()->addDays($i);
            $key = $day->format('Y-m-d');

            $routeRev = (float) ($payments->get($key)?->sum('amount') ?? 0);
            $counterRev = (float) ($counterRefills->get($key)?->sum('total_amount') ?? 0);
            $routeBtl = (int) ($routeCharges->get($key)?->sum('quantity_delivered') ?? 0);
            $counterBtl = (int) ($counterRefills->get($key)?->sum('quantity') ?? 0);

            $series[] = [
                'date' => $key,
                'short_label' => $shortDayNames[$day->dayOfWeek],
                'full_label' => ucfirst($day->copy()->locale('es')->translatedFormat('l d M')),
                'is_today' => $day->isSameDay($nowLocal),
                'route_revenue' => round($routeRev, 2),
                'counter_revenue' => round($counterRev, 2),
                'total_revenue' => round($routeRev + $counterRev, 2),
                'route_bottles' => $routeBtl,
                'counter_bottles' => $counterBtl,
                'total_bottles' => $routeBtl + $counterBtl,
                'orders_count' => $orders->get($key)?->count() ?? 0,
                'new_customers' => $customers->get($key)?->count() ?? 0,
            ];
        }

        return $series;
    }

    /**
     * Calcula variación porcentual segura entre periodo actual y anterior.
     */
    private function calcGrowthPct(float $current, float $previous): ?float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
