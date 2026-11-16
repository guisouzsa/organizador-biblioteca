<?php

namespace App\Console\Commands;

use App\Services\Circulation\ReservationService;
use Illuminate\Console\Command;

class ExpireReservations extends Command
{
    protected $signature = 'library:expire-reservations';

    protected $description = 'Expira reservas retidas vencidas e repassa o exemplar ao próximo da fila';

    public function handle(ReservationService $reservations): int
    {
        $count = $reservations->expireDue();
        $this->info("Reservas expiradas: {$count}");

        return self::SUCCESS;
    }
}
