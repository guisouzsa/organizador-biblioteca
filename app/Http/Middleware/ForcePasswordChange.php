<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Usuários com senha temporária só acessam a troca de senha e o logout. */
class ForcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->must_change_password && ! $request->routeIs('account.password', 'account.password.update', 'logout')) {
            return redirect()->route('account.password')->with('status', 'Defina uma nova senha para continuar.');
        }

        return $next($request);
    }
}
