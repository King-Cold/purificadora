<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use App\Services\CustomerCodeGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Obtiene la lista de roles que un usuario autenticado puede asignar o gestionar
     * respetando el principio de menor privilegio y jerarquía de roles:
     * - Super Admin puede gestionar roles de negocio: Administrador, Cliente, Repartidor, Operador Planta.
     * - Administrador únicamente puede gestionar: Cliente, Repartidor, Operador Planta.
     * - Super Admin nunca se asigna desde el formulario administrativo.
     */
    protected function getAssignableRolesFor(User $currentUser): array
    {
        if ($currentUser->hasRole('Super Admin')) {
            return [
                'Administrador',
                'Cliente',
                'Repartidor',
                'Operador Planta',
            ];
        }

        return [
            'Cliente',
            'Repartidor',
            'Operador Planta',
        ];
    }

    /**
     * Muestra el listado de usuarios con búsqueda, filtros por rol y estado, y paginación.
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'in:all,active,inactive,verified,unverified'],
        ]);

        $search = $validated['search'] ?? null;
        $roleFilter = $validated['role'] ?? null;
        $statusFilter = $validated['status'] ?? null;

        $query = User::query()
            ->with(['roles:id,name', 'customer:id,user_id,code'])
            ->select(['id', 'name', 'email', 'status', 'email_verified_at', 'created_at']);

        // 1. Búsqueda por nombre o correo electrónico
        if (!empty($search)) {
            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // 2. Filtro por Rol de Spatie
        if (!empty($roleFilter) && $roleFilter !== 'all') {
            $query->role($roleFilter);
        }

        // 3. Filtro por Estado (Cuenta o Verificación)
        if (!empty($statusFilter) && $statusFilter !== 'all') {
            if ($statusFilter === 'active') {
                $query->where('status', 'active');
            } elseif ($statusFilter === 'inactive') {
                $query->where('status', 'inactive');
            } elseif ($statusFilter === 'verified') {
                $query->whereNotNull('email_verified_at');
            } elseif ($statusFilter === 'unverified') {
                $query->whereNull('email_verified_at');
            }
        }

        // 4. Paginación eficiente con preservación de filtros en query string
        $users = $query->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString()
            ->through(function (User $user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'status' => $user->status ?? 'active',
                    'is_active' => $user->isActive(),
                    'is_verified' => !is_null($user->email_verified_at),
                    'primary_role' => $user->roles->first()?->name ?? 'Sin Rol',
                    'roles' => $user->roles->pluck('name'),
                    'has_customer' => !is_null($user->customer),
                    'customer_code' => $user->customer?->code,
                    'created_at' => $user->created_at ? $user->created_at->format('d/m/Y H:i') : 'N/D',
                ];
            });

        // 5. Catálogo de roles para el dropdown de filtrado
        $availableRoles = Role::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->pluck('name');

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => [
                'search' => $search ?? '',
                'role' => $roleFilter ?? 'all',
                'status' => $statusFilter ?? 'all',
            ],
            'availableRoles' => $availableRoles,
            'totalUsersCount' => User::count(),
        ]);
    }

    /**
     * Muestra el formulario para crear un nuevo usuario administrativo o comercial.
     */
    public function create(Request $request): Response
    {
        $assignableRoles = $this->getAssignableRolesFor($request->user());

        return Inertia::render('Admin/Users/Create', [
            'assignableRoles' => $assignableRoles,
        ]);
    }

    /**
     * Almacena un nuevo usuario en la base de datos dentro de una transacción segura.
     */
    public function store(Request $request): RedirectResponse
    {
        $allowedRoles = $this->getAssignableRolesFor($request->user());

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'string', Rule::in($allowedRoles)],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $createdUser = DB::transaction(function () use ($validated) {
            // 1. Creación del usuario con contraseña hasheada y estado activo
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'status' => 'active',
            ]);

            // 2. Asignación estricta del rol verificado
            $user->assignRole($validated['role']);

            // 3. Si el rol es 'Cliente', crear automáticamente el perfil Customer vinculado
            if ($validated['role'] === 'Cliente') {
                $code = CustomerCodeGenerator::generate();

                Customer::create([
                    'user_id' => $user->id,
                    'code' => $code,
                    'name' => $user->name,
                    'email' => $user->email,
                    'customer_type' => 'particular',
                    'price_tier' => 'general',
                    'status' => 'active',
                ]);
            }

            return $user;
        });

        return redirect()
            ->route('admin.users.index')
            ->with('success', "Usuario '{$createdUser->name}' creado exitosamente con el rol {$validated['role']}.");
    }

    /**
     * Muestra la vista de consulta detallada de un usuario.
     */
    public function show(Request $request, User $user): Response
    {
        $user->load(['roles:id,name', 'customer.addresses']);

        $customerData = null;
        if ($user->customer) {
            $customerData = [
                'id' => $user->customer->id,
                'code' => $user->customer->code,
                'business_name' => $user->customer->business_name,
                'phone' => $user->customer->phone,
                'customer_type' => $user->customer->customer_type,
                'price_tier' => $user->customer->price_tier,
                'status' => $user->customer->status,
                'addresses_count' => $user->customer->addresses->count(),
            ];
        }

        $canEdit = $request->user()->can('users.manage');
        if ($user->hasRole('Super Admin') && !$request->user()->hasRole('Super Admin')) {
            $canEdit = false;
        }

        return Inertia::render('Admin/Users/Show', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status ?? 'active',
                'is_active' => $user->isActive(),
                'email_verified_at' => $user->email_verified_at ? $user->email_verified_at->format('d/m/Y H:i') : null,
                'is_verified' => !is_null($user->email_verified_at),
                'primary_role' => $user->roles->first()?->name ?? 'Sin Rol',
                'roles' => $user->roles->pluck('name'),
                'created_at' => $user->created_at ? $user->created_at->format('d/m/Y H:i') : 'N/D',
                'updated_at' => $user->updated_at ? $user->updated_at->format('d/m/Y H:i') : 'N/D',
                'customer' => $customerData,
            ],
            'canEdit' => $canEdit,
            'isSelf' => $user->id === $request->user()->id,
        ]);
    }

    /**
     * Muestra el formulario para editar datos y roles del usuario.
     */
    public function edit(Request $request, User $user): Response
    {
        $authUser = $request->user() ?: Auth::user();
        if (!$authUser) {
            abort(401, 'No autenticado.');
        }

        // Evitar que un Administrador edite a un Super Admin
        if ($user->hasRole('Super Admin') && !$authUser->hasRole('Super Admin')) {
            abort(403, 'No tiene autorización para editar una cuenta de Super Admin.');
        }

        $user->load(['roles:id,name', 'customer:id,user_id,code,phone']);
        $assignableRoles = $this->getAssignableRolesFor($authUser);

        $isSelf = ($user->id === $authUser->id);
        $hasCustomer = !is_null($user->customer);

        return Inertia::render('Admin/Users/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roles->first()?->name ?? '',
                'status' => $user->status ?? 'active',
                'has_customer' => $hasCustomer,
                'customer_code' => $user->customer?->code,
            ],
            'assignableRoles' => $assignableRoles,
            'isSelf' => $isSelf,
            'canChangeRole' => !$isSelf && !$hasCustomer,
            'roleLockReason' => $isSelf
                ? 'No puedes modificar tu propio rol de acceso por seguridad del sistema.'
                : ($hasCustomer
                    ? "Este usuario cuenta con un perfil comercial activo (Código: {$user->customer->code}). El cambio a un rol operativo está restringido para preservar el historial de clientes."
                    : null),
        ]);
    }

    /**
     * Actualiza la información y rol del usuario bajo estrictas validaciones.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $authUser = $request->user() ?: Auth::user();
        if (!$authUser) {
            abort(401, 'No autenticado.');
        }

        // Proteger a Super Admin
        if ($user->hasRole('Super Admin') && !$authUser->hasRole('Super Admin')) {
            abort(403, 'No tiene autorización para modificar una cuenta de Super Admin.');
        }

        $allowedRoles = $this->getAssignableRolesFor($authUser);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['nullable', 'string', Rule::in(['Super Admin', 'Administrador', 'Cliente', 'Repartidor', 'Operador Planta'])],
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
        ]);

        $newRole = $validated['role'] ?? null;
        $currentRole = $user->roles->first()?->name;

        // Si se solicitó cambiar el rol
        if (!empty($newRole) && $newRole !== $currentRole) {
            // 1. Prohibir auto-modificación de roles
            if ($user->id === $authUser->id) {
                return back()->withErrors(['role' => 'No puedes modificar tu propio rol de acceso.']);
            }

            // 2. Administrador no puede asignar rol fuera de su jerarquía
            if (!in_array($newRole, $allowedRoles, true)) {
                return back()->withErrors(['role' => "No tienes autorización para asignar el rol '{$newRole}'."]);
            }

            // 3. Protección de perfil Customer: No permitir convertir Cliente a rol operativo
            if ($user->customer()->exists() && $newRole !== 'Cliente') {
                return back()->withErrors([
                    'role' => "No es posible cambiar el rol de un usuario con perfil comercial de Cliente activo a un rol interno/operativo ({$newRole}). El usuario tiene asignado el código comercial {$user->customer->code} con historial de operaciones.",
                ]);
            }

            // 4. Proteger al último Administrador activo contra pérdida de rol
            if ($currentRole === 'Administrador' && $newRole !== 'Administrador') {
                if ($user->isActive()) {
                    $activeAdminsCount = User::role('Administrador')->where('status', 'active')->count();
                    if ($activeAdminsCount <= 1) {
                        return back()->withErrors(['role' => 'No se puede cambiar el rol del último Administrador activo del sistema. Debe existir al menos un administrador en funciones.']);
                    }
                }
            }
        }

        DB::transaction(function () use ($user, $validated, $newRole, $currentRole, $authUser) {
            $userData = [
                'name' => $validated['name'],
                'email' => $validated['email'],
            ];

            // Actualizar contraseña solo si fue proporcionada
            if (!empty($validated['password'])) {
                $userData['password'] = Hash::make($validated['password']);
            }

            $user->update($userData);

            // Mantener consistencia de datos personales con Customer
            if ($user->customer) {
                $user->customer->update([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                ]);
            }

            // Aplicar cambio de rol autorizado si corresponde
            if (!empty($newRole) && $newRole !== $currentRole && $user->id !== $authUser->id) {
                $user->syncRoles([$newRole]);
            }
        });

        return redirect()
            ->route('admin.users.show', $user->id)
            ->with('success', "Los datos del usuario '{$user->name}' se actualizaron correctamente.");
    }

    /**
     * Activa o desactiva la cuenta de un usuario de manera segura.
     */
    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        $authUser = $request->user() ?: Auth::user();
        if (!$authUser) {
            abort(401, 'No autenticado.');
        }

        // 1. Evitar que un usuario se desactive a sí mismo
        if ($user->id === $authUser->id) {
            return back()->with('error', 'Por seguridad, no puedes desactivar tu propia cuenta de usuario.');
        }

        // 2. Proteger al último administrador activo
        if ($user->hasRole('Administrador') && $user->isActive()) {
            $activeAdminsCount = User::role('Administrador')->where('status', 'active')->count();
            if ($activeAdminsCount <= 1) {
                return back()->with('error', 'No se puede desactivar al último Administrador activo del sistema.');
            }
        }

        // 3. Proteger a Super Admin si el usuario actual no es Super Admin
        if ($user->hasRole('Super Admin') && !$authUser->hasRole('Super Admin')) {
            return back()->with('error', 'Solo un Super Admin tiene autorización para modificar el estado de otra cuenta Super Admin.');
        }

        $newStatus = $user->isActive() ? 'inactive' : 'active';

        DB::transaction(function () use ($user, $newStatus) {
            $user->update(['status' => $newStatus]);

            // Sincronizar estado del perfil comercial si existe
            if ($user->customer) {
                $user->customer->update([
                    'status' => $newStatus === 'active' ? 'active' : 'suspended',
                ]);
            }
        });

        $actionText = $newStatus === 'active' ? 'activada' : 'desactivada';
        return back()->with('success', "La cuenta del usuario '{$user->name}' ha sido {$actionText} exitosamente.");
    }
}
