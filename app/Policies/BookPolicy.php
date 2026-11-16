<?php

namespace App\Policies;

use App\Models\Book;
use App\Models\User;

class BookPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('catalog.view');
    }

    public function view(User $user, Book $book): bool
    {
        return $user->can('catalog.view');
    }

    public function create(User $user): bool
    {
        return $user->can('catalog.create');
    }

    public function update(User $user, Book $book): bool
    {
        return $user->can('catalog.update');
    }

    public function archive(User $user, Book $book): bool
    {
        return $user->can('catalog.archive');
    }

    /** Excluir só sem histórico: livro com exemplares ou reservas nunca é excluído (RN-CAT-09). */
    public function delete(User $user, Book $book): bool
    {
        return $user->can('catalog.delete') && ! $book->copies()->exists() && ! $book->reservations()->exists();
    }
}
