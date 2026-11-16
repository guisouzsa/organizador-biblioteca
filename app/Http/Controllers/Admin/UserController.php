<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Models\User;
use App\Services\People\UserAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private UserAccountService $accounts) {}

    public function index(): View
    {
        $this->authorize('viewAny', User::class);

        return view('admin.users.index', ['users' => User::orderByDesc('active')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('admin.users.form', ['user' => null, 'roles' => UserRole::cases()]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        [$user, $password] = $this->accounts->create($request->validated('name'), $request->validated('email'), UserRole::from($request->validated('role')));

        // A senha temporária é exibida uma única vez (na próxima tela) e nunca registrada em log.
        return redirect()->route('users.index')
            ->with('status', "Usuário {$user->email} criado.")
            ->with('credentials', ['email' => $user->email, 'password' => $password]);
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('admin.users.form', ['user' => $user, 'roles' => UserRole::cases()]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $this->accounts->update($user, $request->validated('name'), UserRole::from($request->validated('role')), $request->boolean('active'));

        return redirect()->route('users.index')->with('status', 'Usuário atualizado.');
    }

    public function resetPassword(User $user): RedirectResponse
    {
        $this->authorize('update', $user);
        $password = $this->accounts->resetPassword($user);

        return redirect()->route('users.index')
            ->with('status', "Senha de {$user->email} redefinida.")
            ->with('credentials', ['email' => $user->email, 'password' => $password]);
    }
}
