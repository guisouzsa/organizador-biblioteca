<?php

namespace App\Enums;

enum CopyCondition: string
{
    case Good = 'good';
    case Fair = 'fair';
    case Poor = 'poor';
    case Damaged = 'damaged';

    public function label(): string
    {
        return match ($this) {
            self::Good => 'Bom',
            self::Fair => 'Regular',
            self::Poor => 'Ruim',
            self::Damaged => 'Danificado',
        };
    }
}
