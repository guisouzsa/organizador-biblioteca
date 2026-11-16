<?php

namespace App\Enums;

enum BorrowerStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Transferred = 'transferred';
    case Graduated = 'graduated';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Ativo',
            self::Inactive => 'Inativo',
            self::Transferred => 'Transferido',
            self::Graduated => 'Formado',
        };
    }
}
