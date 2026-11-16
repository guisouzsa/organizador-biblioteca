<?php

use App\Exceptions\Domain\DomainException;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\ForcePasswordChange;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active' => EnsureActiveUser::class,
            'password.changed' => ForcePasswordChange::class,
        ]);
        $middleware->redirectGuestsTo('/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Erros de negócio: mensagem segura e código estável; nunca detalhes técnicos.
        $exceptions->render(function (DomainException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['code' => $e->code(), 'message' => $e->userMessage(), 'context' => $e->context], 409);
            }

            return back()->withInput()->with('error', $e->userMessage())->with('error_code', $e->code())->with('error_context', $e->context);
        });
    })->create();
