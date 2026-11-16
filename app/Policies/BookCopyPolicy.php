<?php

namespace App\Policies;

use App\Models\BookCopy;
use App\Models\User;

class BookCopyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('copy.view');
    }

    public function view(User $user, BookCopy $copy): bool
    {
        return $user->can('copy.view');
    }

    public function create(User $user): bool
    {
        return $user->can('copy.create');
    }

    public function update(User $user, BookCopy $copy): bool
    {
        return $user->can('copy.update');
    }

    public function transition(User $user, BookCopy $copy): bool
    {
        return $user->can('copy.transition');
    }

    /** Restaurar exemplar baixado é exclusivo do Administrador. */
    public function restore(User $user, BookCopy $copy): bool
    {
        return $user->can('copy.restore');
    }
}
