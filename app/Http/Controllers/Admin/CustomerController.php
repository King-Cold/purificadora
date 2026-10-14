<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerStatusHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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
            'zone' => ['nullable', 'string', 'in:all,north,south,east,west,no_zone,other_municipality'],
        ]);

        $search = $validated['search'] ?? null;
        $statusFilter = $validated['status'] ?? null;
        $accessFilter = $validated['access'] ?? null;
        $zoneFilter = $validated['zone'] ?? null;

        $query = Customer::query()
            ->with([
                'user:id,name,email,status',
                'address',
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
                'fixed_bottles_count',
                'exchange_bottles_count',
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

        // 4. Filtro por zonas de reparto del domicilio único
        if (!empty($zoneFilter) && $zoneFilter !== 'all') {
            if (in_array($zoneFilter, ['north', 'south', 'east', 'west'])) {
                $query->whereHas('address', function ($q) use ($zoneFilter) {
                    $q->where('delivery_zone', $zoneFilter)
                        ->where(function ($sub) {
                            $sub->where('city', 'like', '%Hocab%')
                                ->orWhereNull('city');
                        });
                });
            } elseif ($zoneFilter === 'other_municipality') {
                $query->whereHas('address', function ($q) {
                    $q->whereNotNull('city')
                        ->where('city', 'not like', '%Hocab%');
                });
            } elseif ($zoneFilter === 'no_zone') {
                $query->where(function ($q) {
                    $q->whereDoesntHave('address')
                        ->orWhereHas('address', function ($addrQ) {
                            $addrQ->where(function ($sub) {
                                $sub->where('city', 'like', '%Hocab%')
                                    ->orWhereNull('city');
                            })->whereNull('delivery_zone');
                        });
                });
            }
        }

        // 5. Paginación eficiente con query string preservation
        $customers = $query->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString()
            ->through(function (Customer $customer) {
                $address = $customer->address;
                $zoneLabel = 'Sin zona asignada';
                $addressText = 'Sin domicilio registrado';

                if ($address) {
                    $addressText = $address->street;
                    $isHocaba = empty($address->city) || in_array(strtolower(trim($address->city)), ['hocaba', 'hocabá', 'hocabá, yucatán', 'hocaba, yucatan']);
                    if (!$isHocaba) {
                        $zoneLabel = 'Otro municipio';
                    } elseif ($address->delivery_zone) {
                        $zoneLabel = 'Zona ' . (CustomerAddress::ZONES[$address->delivery_zone] ?? $address->delivery_zone);
                    } else {
                        $zoneLabel = 'Sin zona asignada';
                    }
                }

                $fixedCount = (int) ($customer->fixed_bottles_count ?? 0);
                $exchangeCount = (int) ($customer->exchange_bottles_count ?? 0);

                return [
                    'id' => $customer->id,
                    'code' => $customer->code,
                    'name' => $customer->name,
                    'nickname' => $customer->nickname,
                    'phone' => $customer->phone ?: 'No registrado',
                    'secondary_phone' => $customer->secondary_phone ?: null,
                    'email' => $customer->email ?: 'Sin correo registrado',
                    'fixed_bottles_count' => $fixedCount,
                    'exchange_bottles_count' => $exchangeCount,
                    'total_bottles' => $fixedCount + $exchangeCount,
                    'main_address' => $addressText,
                    'zone_label' => $zoneLabel,
                    'has_address' => !is_null($address),
                    'addresses_count' => $address ? 1 : 0,
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
                'zone' => $zoneFilter ?? 'all',
            ],
            'zones' => CustomerAddress::ZONES,
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
            'name' => ['nullable', 'string', 'max:150'],
            'nickname' => ['nullable', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:25'],
            'secondary_phone' => ['nullable', 'string', 'max:25'],
            'email' => ['nullable', 'email', 'max:120'],
            'fixed_bottles_count' => ['required', 'integer', 'min:0'],
            'exchange_bottles_count' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // Regla: Debe proporcionarse al menos el nombre o el apodo para identificar al cliente particular
        if (empty(trim((string)($validated['name'] ?? ''))) && empty(trim((string)($validated['nickname'] ?? '')))) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'name' => 'Debe ingresar al menos el nombre o el apodo para identificar al cliente.',
                'nickname' => 'Debe ingresar al menos el apodo o el nombre para identificar al cliente.',
            ]);
        }

        // Detección preventiva de posibles duplicados (por teléfono o nombre/apodo idéntico)
        $potentialDuplicates = Customer::query()
            ->where(function ($q) use ($validated) {
                $q->where('phone', $validated['phone']);
                if (!empty($validated['name'])) {
                    $q->orWhere('name', $validated['name']);
                }
                if (!empty($validated['nickname'])) {
                    $q->orWhere('nickname', $validated['nickname']);
                }
            })
            ->select(['id', 'code', 'name', 'nickname', 'phone'])
            ->get();

        $customer = DB::transaction(function () use ($validated) {
            // El modelo Customer se encarga automáticamente de generar el código CLI-XXXX en el hook creating.
            // Se fuerza user_id = null ya que este es el registro administrativo para clientes sin cuenta de acceso.
            return Customer::create([
                'user_id' => null,
                'name' => !empty($validated['name']) ? trim($validated['name']) : null,
                'nickname' => !empty($validated['nickname']) ? trim($validated['nickname']) : null,
                'phone' => trim($validated['phone']),
                'secondary_phone' => !empty($validated['secondary_phone']) ? trim($validated['secondary_phone']) : null,
                'email' => !empty($validated['email']) ? trim($validated['email']) : null,
                'fixed_bottles_count' => (int) $validated['fixed_bottles_count'],
                'exchange_bottles_count' => (int) $validated['exchange_bottles_count'],
                'notes' => !empty($validated['notes']) ? trim($validated['notes']) : null,
                'customer_type' => 'particular',
                'price_tier' => 'general',
                'credit_limit' => 0.00,
                'credit_days' => 0,
                'status' => 'active',
            ]);
        });

        $displayName = $customer->name ?: ($customer->nickname ? "Cliente conocido como '{$customer->nickname}'" : 'Cliente');
        $redirect = redirect()
            ->route('admin.customers.show', $customer->id)
            ->with('success', "{$displayName} registrado exitosamente con el código {$customer->code}.");

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
            'address',
            'statusHistories.changer:id,name',
        ]);

        $address = $customer->address;
        $addressData = null;
        if ($address) {
            $isHocaba = empty($address->city) || in_array(strtolower(trim($address->city)), ['hocaba', 'hocabá', 'hocabá, yucatán', 'hocaba, yucatan']);
            $addressData = [
                'id' => $address->id,
                'street_and_number' => $address->street,
                'municipality' => $address->city ?: 'Hocabá',
                'is_hocaba' => $isHocaba,
                'delivery_zone' => $address->delivery_zone,
                'zone_label' => $isHocaba
                    ? ($address->delivery_zone ? 'Zona ' . (CustomerAddress::ZONES[$address->delivery_zone] ?? $address->delivery_zone) : 'Sin zona asignada')
                    : 'Otro municipio (' . $address->city . ')',
                'reference' => $address->reference,
                'full_address' => $address->full_address,
            ];
        }

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

        $fixedCount = (int) ($customer->fixed_bottles_count ?? 0);
        $exchangeCount = (int) ($customer->exchange_bottles_count ?? 0);

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
                'status_label' => Customer::STATUSES[$customer->status] ?? $customer->status,
                'created_at' => $customer->created_at ? $customer->created_at->format('d/m/Y H:i') : 'N/D',
                'updated_at' => $customer->updated_at ? $customer->updated_at->format('d/m/Y H:i') : 'N/D',

                // Acceso al sistema
                'has_account' => !is_null($customer->user_id),
                'user' => $userData,

                // Domicilio único (casa del cliente)
                'address' => $addressData,
                'has_address' => !is_null($addressData),
                'addresses' => $addressData ? [$addressData] : [],
                'addresses_count' => $addressData ? 1 : 0,

                // Botellones del cliente (Fijos y de Cambio)
                'fixed_bottles_count' => $fixedCount,
                'exchange_bottles_count' => $exchangeCount,
                'total_bottles' => $fixedCount + $exchangeCount,
                'notes' => $customer->notes,

                // Historial de cambios de estado comercial
                'status_histories' => $customer->statusHistories->map(function ($history) {
                    return [
                        'id' => $history->id,
                        'previous_status' => $history->previous_status,
                        'previous_status_label' => Customer::STATUSES[$history->previous_status] ?? $history->previous_status,
                        'new_status' => $history->new_status,
                        'new_status_label' => Customer::STATUSES[$history->new_status] ?? $history->new_status,
                        'reason' => $history->reason,
                        'changed_by_name' => $history->changer?->name ?? 'Sistema / Administrador',
                        'created_at' => $history->created_at ? $history->created_at->format('d/m/Y H:i') : 'N/D',
                    ];
                }),
            ],
            'available_statuses' => Customer::STATUSES,
            'canEdit' => $authUser->can('customers.update'),
        ]);
    }

    /**
     * Muestra el formulario para editar un cliente particular.
     */
    public function edit(Request $request, Customer $customer): Response
    {
        $authUser = $request->user() ?: Auth::user();
        if (!$authUser || !$authUser->can('customers.update')) {
            abort(403, 'No tiene autorización para editar la información del cliente.');
        }

        $customer->load('user:id,email');

        return Inertia::render('Admin/Customers/Edit', [
            'customer' => [
                'id' => $customer->id,
                'code' => $customer->code,
                'name' => $customer->name ?? '',
                'nickname' => $customer->nickname ?? '',
                'phone' => $customer->phone ?? '',
                'secondary_phone' => $customer->secondary_phone ?? '',
                'email' => $customer->email ?? '',
                'notes' => $customer->notes ?? '',
                'fixed_bottles_count' => (int) ($customer->fixed_bottles_count ?? 0),
                'exchange_bottles_count' => (int) ($customer->exchange_bottles_count ?? 0),
                'has_account' => !is_null($customer->user_id),
                'user' => $customer->user ? [
                    'id' => $customer->user->id,
                    'email' => $customer->user->email,
                ] : null,
            ],
        ]);
    }

    /**
     * Actualiza la información personal y las cantidades de botellones del cliente.
     */
    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $authUser = $request->user() ?: Auth::user();
        if (!$authUser || !$authUser->can('customers.update')) {
            abort(403, 'No tiene autorización para editar la información del cliente.');
        }

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:150'],
            'nickname' => ['nullable', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:25'],
            'secondary_phone' => ['nullable', 'string', 'max:25'],
            'email' => ['nullable', 'email', 'max:120'],
            'fixed_bottles_count' => ['required', 'integer', 'min:0'],
            'exchange_bottles_count' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // Regla: Debe proporcionarse al menos el nombre o el apodo para identificar al cliente
        if (empty(trim((string)($validated['name'] ?? ''))) && empty(trim((string)($validated['nickname'] ?? '')))) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'name' => 'Debe ingresar al menos el nombre o el apodo para identificar al cliente.',
                'nickname' => 'Debe ingresar al menos el apodo o el nombre para identificar al cliente.',
            ]);
        }

        // Protegemos explícitamente id, code, user_id y cualquier credencial de acceso
        $customer->update([
            'name' => !empty($validated['name']) ? trim($validated['name']) : null,
            'nickname' => !empty($validated['nickname']) ? trim($validated['nickname']) : null,
            'phone' => trim($validated['phone']),
            'secondary_phone' => !empty($validated['secondary_phone']) ? trim($validated['secondary_phone']) : null,
            'email' => !empty($validated['email']) ? trim($validated['email']) : null,
            'fixed_bottles_count' => (int) $validated['fixed_bottles_count'],
            'exchange_bottles_count' => (int) $validated['exchange_bottles_count'],
            'notes' => !empty($validated['notes']) ? trim($validated['notes']) : null,
        ]);

        $displayName = $customer->name ?: ($customer->nickname ? "Cliente conocido como '{$customer->nickname}'" : 'Cliente');

        return redirect()
            ->route('admin.customers.show', $customer->id)
            ->with('success', "Datos del cliente '{$displayName}' actualizados correctamente.");
    }

    /**
     * Actualiza el estado comercial del cliente registrando el cambio en el historial de auditoría.
     */
    public function updateStatus(Request $request, Customer $customer): RedirectResponse
    {
        $authUser = $request->user() ?: Auth::user();
        if (!$authUser || !$authUser->can('customers.update')) {
            abort(403, 'No tiene autorización para modificar el estado comercial de clientes.');
        }

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(Customer::ALLOWED_STATUSES)],
            'reason' => ['nullable', 'string', 'max:500'],
        ], [
            'status.required' => 'Debe seleccionar un estado comercial válido.',
            'status.in' => 'El estado comercial seleccionado no es válido.',
            'reason.max' => 'El motivo del cambio no puede superar los 500 caracteres.',
        ]);

        $newStatus = $validated['status'];
        $previousStatus = $customer->status ?? Customer::STATUS_ACTIVE;

        if ($newStatus === $previousStatus) {
            return redirect()
                ->route('admin.customers.show', $customer->id)
                ->with('info', "El cliente ya se encuentra en estado comercial {$customer->status_label}.");
        }

        DB::transaction(function () use ($customer, $previousStatus, $newStatus, $validated, $authUser) {
            $customer->status = $newStatus;
            $customer->save();

            CustomerStatusHistory::logChange(
                customer: $customer,
                previousStatus: $previousStatus,
                newStatus: $newStatus,
                reason: !empty($validated['reason']) ? trim($validated['reason']) : null,
                changedBy: $authUser->id
            );
        });

        $displayName = $customer->name ?: ($customer->nickname ? "Cliente conocido como '{$customer->nickname}'" : 'Cliente');

        return redirect()
            ->route('admin.customers.show', $customer->id)
            ->with('success', "Estado comercial de '{$displayName}' actualizado a {$customer->status_label} correctamente.");
    }
}

