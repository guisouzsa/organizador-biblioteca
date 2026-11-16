<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Admin\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LoginController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $key = Str::lower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);

            return back()->withInput($request->only('email'))
                ->withErrors(['email' => "Muitas tentativas. Aguarde {$minutes} minuto(s) e tente novamente."]);
        }

        // Mensagem sempre genérica para credenciais incorretas (não revela se o e-mail existe).
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);

            return back()->withInput($request->only('email'))->withErrors(['email' => 'E-mail ou senha incorretos.']);
        }

        if (! $request->user()->active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'Acesso desativado. Procure o administrador.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->user()->forceFill(['last_login_at' => now()])->save();
        $audit->record('auth.login', $request->user());

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Você saiu do sistema.');
    }
}
