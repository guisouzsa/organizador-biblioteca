<?php

namespace App\Policies;

use App\Models\Borrower;
use App\Models\User;

class BorrowerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('borrower.view');
    }

    public function view(User $user, Borrower $borrower): bool
    {
        return $user->can('borrower.view');
    }

    public function viewContacts(User $user, Borrower $borrower): bool
    {
        return $user->can('borrower.contacts');
    }

    public function create(User $user): bool
    {
        return $user->can('borrower.create');
    }

    public function update(User $user, Borrower $borrower): bool
    {
        return $user->can('borrower.update') && $borrower->anonymized_at === null;
    }

    public function changeStatus(User $user, Borrower $borrower): bool
    {
        return $user->can('borrower.status') && $borrower->anonymized_at === null;
    }

    public function anonymize(User $user, Borrower $borrower): bool
    {
        return $user->can('borrower.anonymize') && $borrower->anonymized_at === null;
    }
}
