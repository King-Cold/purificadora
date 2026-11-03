<?php

namespace App\Http\Controllers\Repartidor;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\DeliveryRoute;
use App\Services\CustomerCodeGenerator;
use App\Services\DebtAndCreditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RouteCustomerController extends Controller
{
    public function __construct(
        protected DebtAndCreditService $debtService
    ) {}

    /**
     * Directorio de clientes accesible para el Repartidor con búsqueda y semáforo.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $query = Customer::with('address');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('nickname', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $customers = $query->orderBy('id', 'desc')->paginate(20)->withQueryString();

        $formattedCustomers = $customers->through(function ($cust) {
            $tl = $this->debtService->getTrafficLight($cust);
            $debtAmount = $this->debtService->getPendingDebtAmount($cust);
            $debtBottles = $this->debtService->getPendingDebtBottles($cust);

            return [
                'id' => $cust->id,
                'code' => $cust->code,
                'nickname' => $cust->nickname,
                'name' => $cust->name,
                'phone' => $cust->phone,
                'zone' => $cust->address?->zone ?? 'Sin zona',
                'address_summary' => $cust->address ? ($cust->address->street . ' #' . $cust->address->exterior_number . ', ' . $cust->address->neighborhood) : 'Sin dirección registrada',
                'traffic_light' => $tl,
                'pending_debt_amount' => $debtAmount,
                'pending_debt_bottles' => $debtBottles,
            ];
        });

        return Inertia::render('Repartidor/Customers/Index', [
            'customers' => $formattedCustomers,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    /**
     * Pantalla para agregar un cliente nuevo directamente desde la sesión de Repartidor.
     * Accesible en cualquier momento, con o sin ruta iniciada y sin requerir pedido.
     */
    public function create(Request $request): Response
    {
        $user = $request->user();

        // Verificar si el chofer cuenta con ruta en curso para ofrecer opciones de contexto
        $hasActiveRoute = DeliveryRoute::where('driver_user_id', $user->id)
            ->where('status', DeliveryRoute::STATUS_IN_PROGRESS)
            ->exists();

        return Inertia::render('Repartidor/Customers/Create', [
            'hasActiveRoute' => $hasActiveRoute,
            'createdCustomer' => session('createdCustomer'),
        ]);
    }

    /**
     * Guarda el nuevo cliente rápido en ruta:
     * - Apodo obligatorio y nombre opcional.
     * - No solicita teléfono, correo, contraseña ni domicilio.
     * - Crea Customer sin User y genera CLI-XXXX de forma atómica.
     * - Permite guardar sin pedido ni ruta iniciada.
     * - Admite apodos repetidos sin mezclar registros.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nickname' => ['required', 'string', 'max:100'],
            'name' => ['nullable', 'string', 'max:150'],
        ], [
            'nickname.required' => 'El apodo del cliente es obligatorio.',
            'nickname.max' => 'El apodo no debe superar los 100 caracteres.',
            'name.max' => 'El nombre no debe superar los 150 caracteres.',
        ]);

        $nickname = trim($validated['nickname']);
        $name = !empty($validated['name']) ? trim($validated['name']) : null;

        // Creación del cliente sin cuenta User (Customer independiente)
        $customer = Customer::create([
            'user_id' => null,
            'nickname' => $nickname,
            'name' => $name,
            'fixed_bottles_count' => 0,
            'exchange_bottles_count' => 0,
        ]);

        return redirect()->route('repartidor.customers.create')
            ->with('createdCustomer', [
                'id' => $customer->id,
                'code' => $customer->code,
                'nickname' => $customer->nickname,
                'name' => $customer->name,
            ])
            ->with('success', "Cliente {$customer->code} (\"{$customer->nickname}\") guardado exitosamente.");
    }
}

