<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    protected function redirectTo(Request $request)
    {
        // No redirigir para solicitudes JSON (API)
        if ($request->expectsJson()) {
            return null;  // Dejar que el middleware devuelva un error 401
        }

        // Para solicitudes web (si las usas), redirigir a una ruta válida
        return route('auth.signin');
    }
}
