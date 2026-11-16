<?php

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;

class ReservationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('reservation.view');
    }

    public function create(User $user): bool
    {
        return $user->can('reservation.create');
    }

    public function cancel(User $user, Reservation $reservation): bool
    {
        return $user->can('reservation.cancel') && $reservation->status->isActive();
    }
}
