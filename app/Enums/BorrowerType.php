<?php

namespace App\Enums;

enum BorrowerType: string
{
    case Student = 'student';
    case Staff = 'staff';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Student => 'Aluno',
            self::Staff => 'Servidor',
            self::Other => 'Outro',
        };
    }
}
