<?php

namespace App\Enums;

enum CategoryType: string
{
    case Discipline = 'discipline';
    case Subject = 'subject';
    case Genre = 'genre';
    case Theme = 'theme'; // legado: preservado para dados existentes

    public function label(): string
    {
        return match ($this) {
            self::Discipline => 'Disciplina',
            self::Subject => 'Assunto',
            self::Genre => 'Gênero literário',
            self::Theme => 'Tema',
        };
    }
}
