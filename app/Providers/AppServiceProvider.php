<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        \Illuminate\Pagination\Paginator::defaultView('pagination.library');
        \Illuminate\Pagination\Paginator::defaultSimpleView('pagination.library');
        // Cada permissão da matriz (UserRole::abilities) vira um Gate ("catalog.create", "loan.void"…).
        // Usuário desativado nunca é autorizado.
        foreach (UserRole::allAbilities() as $ability) {
            Gate::define($ability, fn (User $user) => $user->active && $user->role->can($ability));
        }
    }
}
