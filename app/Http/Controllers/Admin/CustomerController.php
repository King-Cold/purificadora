<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    /**
     * Muestra el listado de clientes particulares con búsqueda, filtros y paginación.
     */
    public function index(Request $request): Response
    {
        $authUser = $request->user() ?: Auth::user();
        if (!$authUser || !$authUser->can('customers.view')) {
            abort(403, 'No tiene autorización para visualizar el catálogo de clientes.');
        }

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'in:all,active,suspended,inactive'],
            'access' => ['nullable', 'string', 'in:all,with_account,without_account'],
        ]);

        $search = $validated['search'] ?? null;
        $statusFilter = $validated['status'] ?? null;
        $accessFilter = $validated['access'] ?? null;

        $query = Customer::query()
            ->with([
                'user:id,name,email,status',
                'addresses' => function ($q) {
                    $q->orderBy('is_default', 'desc')->orderBy('id', 'asc');
                },
            ])
            ->select([
                'id',
                'user_id',
                'code',
                'name',
                'nickname',
                'phone',
                'secondary_phone',
                'email',
                'status',
                'created_at',
            ]);

        // 1. Búsqueda por código, nombre, apodo, teléfono o correo
        if (!empty($search)) {
            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('nickname', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('secondary_phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('email', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        // 2. Filtro por estado comercial del cliente
        if (!empty($statusFilter) && $statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        // 3. Filtro por tipo de acceso al sistema (Con cuenta / Sin cuenta)
        if (!empty($accessFilter) && $accessFilter !== 'all') {
            if ($accessFilter === 'with_account') {
                $query->whereNotNull('user_id');
            } elseif ($accessFilter === 'without_account') {
                $query->whereNull('user_id');
            }
        }

        // 4. Paginación eficiente con query string preservation
        $customers = $query->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString()
            ->through(function (Customer $customer) {
                // Obtener domicilio principal o el primer domicilio disponible
                $mainAddress = $customer->addresses->firstWhere('is_default', true)
                    ?: $customer->addresses->first();

                return [
                    'id' => $customer->id,
                    'code' => $customer->code,
                    'name' => $customer->name,
                    'nickname' => $customer->nickname,
                    'phone' => $customer->phone ?: 'No registrado',
                    'secondary_phone' => $customer->secondary_phone ?: null,
                    'email' => $customer->email ?: 'Sin correo registrado',
                    'main_address' => $mainAddress ? $mainAddress->full_address : 'Sin domicilio registrado',
                    'addresses_count' => $customer->addresses->count(),
                    'has_account' => !is_null($customer->user_id),
                    'account_type' => !is_null($customer->user_id) ? 'Con cuenta' : 'Sin cuenta',
                    'user' => $customer->user ? [
                        'id' => $customer->user->id,
                        'email' => $customer->user->email,
                        'status' => $customer->user->status ?? 'active',
                        'is_active' => $customer->user->isActive(),
                    ] : null,
                    'status' => $customer->status,
                    'created_at' => $customer->created_at ? $customer->created_at->format('d/m/Y') : 'N/D',
                ];
            });

        return Inertia::render('Admin/Customers/Index', [
            'customers' => $customers,
            'filters' => [
                'search' => $search ?? '',
                'status' => $statusFilter ?? 'all',
                'access' => $accessFilter ?? 'all',
            ],
            'totalCustomersCount' => Customer::count(),
        ]);
    }

    /**
     * Muestra el formulario administrativo para registrar un nuevo cliente particular sin cuenta.
     */
    public function create(Request $request): Response
    {
        $authUser = $request->user() ?: Auth::user();
        if (!$authUser || !$authUser->can('customers.create')) {
            abort(403, 'No tiene autorización para registrar nuevos clientes.');
        }

        return Inertia::render('Admin/Customers/Create');
    }

    /**
     * Almacena un nuevo cliente particular sin cuenta dentro de una transacción segura.
     */
    public function store(Request $request): RedirectResponse
    {
        $authUser = $request->user() ?: Auth::user();
        if (!$authUser || !$authUser->can('customers.create')) {
            abort(403, 'No tiene autorización para registrar nuevos clientes.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'nickname' => ['nullable', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:25'],
            'secondary_phone' => ['nullable', 'string', 'max:25'],
            'email' => ['nullable', 'email', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // Detección preventiva de posibles duplicados (por teléfono o nombre idéntico)
        $potentialDuplicates = Customer::query()
            ->where(function ($q) use ($validated) {
                $q->where('phone', $validated['phone'])
                  ->orWhere('name', $validated['name']);
            })
            ->select(['id', 'code', 'name', 'nickname', 'phone'])
            ->get();

        $customer = DB::transaction(function () use ($validated) {
            // El modelo Customer se encarga automáticamente de generar el código CLI-XXXX en el hook creating.
            // Se fuerza user_id = null ya que este es el registro administrativo para clientes sin cuenta de acceso.
            return Customer::create([
                'user_id' => null,
                'name' => trim($validated['name']),
                'nickname' => !empty($validated['nickname']) ? trim($validated['nickname']) : null,
                'phone' => trim($validated['phone']),
                'secondary_phone' => !empty($validated['secondary_phone']) ? trim($validated['secondary_phone']) : null,
                'email' => !empty($validated['email']) ? trim($validated['email']) : null,
                'notes' => !empty($validated['notes']) ? trim($validated['notes']) : null,
                'customer_type' => 'particular',
                'price_tier' => 'general',
                'credit_limit' => 0.00,
                'credit_days' => 0,
                'has_deposit_bottles' => false,
                'status' => 'active',
            ]);
        });

        $redirect = redirect()
            ->route('admin.customers.show', $customer->id)
            ->with('success', "Cliente '{$customer->name}' registrado exitosamente con el código {$customer->code}.");

        // Si se encontraron posibles duplicados, adjuntar advertencia preventiva
        if ($potentialDuplicates->isNotEmpty()) {
            $duplicateDetails = $potentialDuplicates->map(function ($dup) {
                $alias = $dup->nickname ? " ({$dup->nickname})" : '';
                return "{$dup->name}{$alias} - {$dup->code} [Tel: {$dup->phone}]";
            })->implode(', ');

            $redirect->with('warning', "Aviso preventivo de duplicado: Existen registros previos con datos similares: {$duplicateDetails}. Verifique que no se trate de una persona duplicada.");
        }

        return $redirect;
    }

    /**
     * Muestra la vista de consulta detallada de un cliente particular.
     */
    public function show(Request $request, Customer $customer): Response
    {
        $authUser = $request->user() ?: Auth::user();
        if (!$authUser || !$authUser->can('customers.view')) {
            abort(403, 'No tiene autorización para consultar el perfil del cliente.');
        }

        $customer->load([
            'user:id,name,email,status,email_verified_at,created_at',
            'addresses' => function ($q) {
                $q->orderBy('is_default', 'desc')->orderBy('id', 'asc');
            },
        ]);

        $addresses = $customer->addresses->map(function (CustomerAddress $address) {
            return [
                'id' => $address->id,
                'label' => $address->label ?: 'Domicilio',
                'street' => $address->street,
                'exterior_number' => $address->exterior_number,
                'interior_number' => $address->interior_number,
                'neighborhood' => $address->neighborhood,
                'postal_code' => $address->postal_code,
                'city' => $address->city,
                'state' => $address->state,
                'between_streets' => $address->between_streets,
                'reference' => $address->reference,
                'delivery_zone' => $address->delivery_zone,
                'preferred_time_window' => $address->preferred_time_window,
                'is_default' => (bool) $address->is_default,
                'full_address' => $address->full_address,
            ];
        });

        $userData = null;
        if ($customer->user) {
            $userData = [
                'id' => $customer->user->id,
                'name' => $customer->user->name,
                'email' => $customer->user->email,
                'status' => $customer->user->status ?? 'active',
                'is_active' => $customer->user->isActive(),
                'email_verified_at' => $customer->user->email_verified_at
                    ? $customer->user->email_verified_at->format('d/m/Y H:i')
                    : null,
                'is_verified' => !is_null($customer->user->email_verified_at),
                'created_at' => $customer->user->created_at
                    ? $customer->user->created_at->format('d/m/Y H:i')
                    : 'N/D',
            ];
        }

        return Inertia::render('Admin/Customers/Show', [
            'customer' => [
                'id' => $customer->id,
                'code' => $customer->code,
                'name' => $customer->name,
                'nickname' => $customer->nickname,
                'phone' => $customer->phone ?: 'No registrado',
                'secondary_phone' => $customer->secondary_phone ?: 'No registrado',
                'email' => $customer->email ?: 'No registrado',
                'status' => $customer->status,
                'created_at' => $customer->created_at ? $customer->created_at->format('d/m/Y H:i') : 'N/D',
                'updated_at' => $customer->updated_at ? $customer->updated_at->format('d/m/Y H:i') : 'N/D',

                // Acceso al sistema
                'has_account' => !is_null($customer->user_id),
                'user' => $userData,

                // Domicilios
                'addresses' => $addresses,
                'addresses_count' => $addresses->count(),

                // Información comercial existente (sin campos empresariales)
                'price_tier' => $customer->price_tier ?? 'general',
                'credit_limit' => (float) ($customer->credit_limit ?? 0),
                'credit_days' => (int) ($customer->credit_days ?? 0),
                'has_credit' => ($customer->credit_limit > 0 || $customer->credit_days > 0),
                'has_deposit_bottles' => (bool) $customer->has_deposit_bottles,
                'notes' => $customer->notes,
            ],
        ]);
    }
}
