<?php

namespace App\Policies;

use App\Models\Loan;
use App\Models\User;

class LoanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('loan.view');
    }

    public function view(User $user, Loan $loan): bool
    {
        return $user->can('loan.view');
    }

    public function create(User $user): bool
    {
        return $user->can('loan.create');
    }

    public function return(User $user, Loan $loan): bool
    {
        return $user->can('loan.return') && $loan->isActive();
    }

    public function renew(User $user, Loan $loan): bool
    {
        return $user->can('loan.renew') && $loan->isActive();
    }

    /** Estorno corrige lançamento por engano e é exclusivo do Administrador (RN-CIR-14). */
    public function void(User $user, Loan $loan): bool
    {
        return $user->can('loan.void') && $loan->outcome?->value !== 'voided';
    }
}
