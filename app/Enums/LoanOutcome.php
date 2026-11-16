<?php

namespace App\Enums;

enum LoanOutcome: string
{
    case Returned = 'returned';
    case Lost = 'lost';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Returned => 'Devolvido',
            self::Lost => 'Encerrado como perdido',
            self::Voided => 'Estornado',
        };
    }
}
