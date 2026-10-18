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
                $roleName = $user->roles->first()?->name ?? 'Sin Rol';
                $displayName = $user->name;
                if ($roleName === 'Repartidor') {
                    $displayName = 'Repartidor';
                } elseif ($roleName === 'Operador Planta') {
                    $displayName = 'Llenador';
                }

                return [
                    'id' => $user->id,
                    'name' => $displayName,
                    'email' => $user->email,
                    'status' => $user->status ?? 'active',
                    'is_active' => $user->isActive(),
                    'is_verified' => !is_null($user->email_verified_at),
                    'primary_role' => $roleName,
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
     * Creación deshabilitada en el módulo administrativo de usuarios.
     */
    public function create(): never
    {
        abort(403, 'La creación de usuarios administrativos está deshabilitada. El sistema opera con cuentas únicas de personal.');
    }

    /**
     * Creación deshabilitada en el módulo administrativo de usuarios.
     */
    public function store(): never
    {
        abort(403, 'La creación de usuarios administrativos está deshabilitada. El sistema opera con cuentas únicas de personal.');
    }

    /**
     * Muestra la vista de consulta detallada de un usuario.
     */
    public function show(Request $request, User $user): Response
    {
        $user->load(['roles:id,name', 'customer.address']);

        $customerData = null;
        if ($user->customer) {
            $customerData = [
                'id' => $user->customer->id,
                'code' => $user->customer->code,
                'nickname' => $user->customer->nickname,
                'phone' => $user->customer->phone,
                'has_address' => $user->customer->address()->exists(),
            ];
        }

        $canEdit = $request->user()->can('users.manage');
        if ($user->hasRole('Super Admin') && !$request->user()->hasRole('Super Admin')) {
            $canEdit = false;
        }

        $roleName = $user->roles->first()?->name ?? 'Sin Rol';
        $displayName = $user->name;
        if ($roleName === 'Repartidor') {
            $displayName = 'Repartidor';
        } elseif ($roleName === 'Operador Planta') {
            $displayName = 'Llenador';
        }

        return Inertia::render('Admin/Users/Show', [
            'user' => [
                'id' => $user->id,
                'name' => $displayName,
                'email' => $user->email,
                'status' => $user->status ?? 'active',
                'is_active' => $user->isActive(),
                'email_verified_at' => $user->email_verified_at ? $user->email_verified_at->format('d/m/Y H:i') : null,
                'is_verified' => !is_null($user->email_verified_at),
                'primary_role' => $roleName,
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
     * Muestra el formulario para editar datos de la cuenta (nombre, correo y contraseña).
     * El rol es permanente y no es modificable.
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
        $roleName = $user->roles->first()?->name ?? 'Sin Rol';

        $isPositionFixed = $user->hasAnyRole(['Repartidor', 'Operador Planta']);
        $positionTitle = $user->hasRole('Repartidor') ? 'Repartidor' : ($user->hasRole('Operador Planta') ? 'Llenador' : null);
        $displayName = $positionTitle ?: $user->name;

        return Inertia::render('Admin/Users/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $displayName,
                'email' => $user->email,
                'role' => $roleName,
                'position_title' => $positionTitle,
                'is_position_fixed' => $isPositionFixed,
                'status' => $user->status ?? 'active',
                'has_customer' => !is_null($user->customer),
                'customer_code' => $user->customer?->code,
            ],
            'isSelf' => ($user->id === $authUser->id),
        ]);
    }

    /**
     * Actualiza la información permitida del usuario.
     * Los roles son permanentes; los nombres de Repartidor y Llenador son fijos a sus puestos.
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

        // 1. Bloqueo estricto contra modificación de roles
        $currentRole = $user->roles->first()?->name;
        if ($request->has('role') && $request->input('role') !== $currentRole) {
            abort(403, 'Los roles de usuario son permanentes y no pueden ser modificados.');
        }

        // 2. Determinar nombre según el puesto (Repartidor y Llenador se identifican únicamente por su puesto)
        $isPositionFixed = $user->hasAnyRole(['Repartidor', 'Operador Planta']);
        if ($user->hasRole('Repartidor')) {
            $targetName = 'Repartidor';
        } elseif ($user->hasRole('Operador Planta')) {
            $targetName = 'Llenador';
        } else {
            $targetName = trim((string) $request->input('name'));
        }

        $rules = [
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
        ];
        if (!$isPositionFixed) {
            $rules['name'] = ['required', 'string', 'max:255'];
        }

        $validated = $request->validate($rules);

        DB::transaction(function () use ($user, $validated, $targetName) {
            $userData = [
                'name' => $targetName,
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
                    'name' => $targetName,
                    'email' => $validated['email'],
                ]);
            }
        });

        return redirect()
            ->route('admin.users.show', $user->id)
            ->with('success', "Los datos de la cuenta '{$user->name}' se actualizaron correctamente.");
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

        // 2. No permitir desactivar cuentas de clientes desde el panel administrativo
        if ($user->hasRole('Cliente')) {
            return back()->with('error', 'No está permitido desactivar cuentas de clientes desde el panel administrativo. Una cuenta registrada permanece habilitada.');
        }

        // 3. Proteger al último administrador activo
        if ($user->hasRole('Administrador') && $user->isActive()) {
            $activeAdminsCount = User::role('Administrador')->where('status', 'active')->count();
            if ($activeAdminsCount <= 1) {
                return back()->with('error', 'No se puede desactivar al último Administrador activo del sistema.');
            }
        }

        // 4. Proteger a Super Admin si el usuario actual no es Super Admin
        if ($user->hasRole('Super Admin') && !$authUser->hasRole('Super Admin')) {
            return back()->with('error', 'Solo un Super Admin tiene autorización para modificar el estado de otra cuenta Super Admin.');
        }

        $newStatus = $user->isActive() ? 'inactive' : 'active';

        $user->update(['status' => $newStatus]);

        $actionText = $newStatus === 'active' ? 'activada' : 'desactivada';
        return back()->with('success', "La cuenta del usuario '{$user->name}' ha sido {$actionText} exitosamente.");
    }
}
