<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Admin\AuditLogger;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

/** Recuperação de acesso e troca de senha (UC-PES-011). */
class PasswordController extends Controller
{
    public function forgotForm(): View
    {
        return view('auth.forgot-password');
    }

    /** Resposta sempre igual: não revela se o e-mail existe. */
    public function sendLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email', 'max:255']]);

        Password::sendResetLink($request->only('email'));

        return back()->with('status', 'Se o e-mail estiver cadastrado, enviaremos as instruções para redefinir a senha.');
    }

    public function resetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function reset(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(10)->letters()->numbers()],
        ]);

        $status = Password::reset($data, function (User $user, string $password) use ($audit) {
            $user->forceFill([
                'password' => $password,
                'must_change_password' => false,
                'remember_token' => Str::random(60),
            ])->save();
            event(new PasswordReset($user));
            $audit->record('auth.password_reset', $user);
        });

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', 'Senha redefinida. Entre com a nova senha.')
            : back()->withInput($request->only('email'))->withErrors(['email' => 'Link inválido ou expirado. Solicite um novo.']);
    }

    public function changeForm(): View
    {
        return view('auth.change-password');
    }

    public function change(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', PasswordRule::min(10)->letters()->numbers(), 'different:current_password'],
        ], [
            'password.different' => 'A nova senha deve ser diferente da atual.',
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'A senha atual está incorreta.']);
        }

        $user->forceFill(['password' => $data['password'], 'must_change_password' => false])->save();
        Auth::logoutOtherDevices($data['password']);
        $audit->record('auth.password_changed', $user);

        return redirect()->route('dashboard')->with('status', 'Senha alterada com sucesso.');
    }
}
