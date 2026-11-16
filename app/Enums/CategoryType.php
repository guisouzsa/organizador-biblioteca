<?php

namespace App\Enums;

enum CategoryType: string
{
    case Discipline = 'discipline';
    case Theme = 'theme';

    public function label(): string
    {
        return match ($this) {
            self::Discipline => 'Disciplina',
            self::Theme => 'Tema',
        };
    }
}
