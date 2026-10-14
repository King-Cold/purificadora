<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardRedirectController extends Controller
{
    /**
     * Redirige al dashboard correspondiente según el rol del usuario autenticado.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        // 1. Super Admin y Administrador acceden al dashboard administrativo
        if ($user->hasRole('Super Admin') || $user->hasRole('Administrador')) {
            return redirect()->route('admin.dashboard');
        }

        // 2. Cliente accede al portal de cliente
        if ($user->hasRole('Cliente')) {
            return redirect()->route('cliente.dashboard');
        }

        // 3. Repartidor accede a operaciones de reparto
        if ($user->hasRole('Repartidor')) {
            return redirect()->route('repartidor.dashboard');
        }

        // 4. Operador Planta accede a producción y llenado
        if ($user->hasRole('Operador Planta')) {
            return redirect()->route('planta.dashboard');
        }

        // Salvaguarda: si el usuario no tiene rol asignado, cerramos la sesión huérfana
        // y lo redirigimos limpiamente a login en lugar de dejarlo bloqueado con un error 403
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('error', 'Su cuenta no cuenta con un rol asignado en el sistema. Inicie sesión con una cuenta autorizada.');
    }
}
