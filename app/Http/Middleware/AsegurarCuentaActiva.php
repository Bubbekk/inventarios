<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AsegurarCuentaActiva
{
    /**
     * Cierra la sesión de las cuentas desactivadas.
     *
     * Cubre los accesos que no pasan por la validación de credenciales de
     * Fortify: ingreso con passkey, sesión recordada y sesión ya abierta
     * cuando la cuenta se desactiva mientras el usuario está operando.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = Auth::user();

        if ($usuario !== null && ! $usuario->activo) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => __('Su cuenta está desactivada. Comuníquese con el administrador del sistema.'),
            ]);
        }

        return $next($request);
    }
}
