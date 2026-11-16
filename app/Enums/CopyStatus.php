<?php

namespace App\Enums;

/** Estado administrativo do exemplar. Disponível/emprestado são derivados (RN-ACE-07). */
enum CopyStatus: string
{
    case Active = 'active';
    case Maintenance = 'maintenance';
    case Lost = 'lost';
    case WrittenOff = 'written_off';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Em acervo',
            self::Maintenance => 'Em manutenção',
            self::Lost => 'Perdido',
            self::WrittenOff => 'Baixado',
        };
    }

    /** Transições permitidas (RN-ACE-06). */
    public function canTransitionTo(self $to): bool
    {
        return match ($this) {
            self::Active => in_array($to, [self::Maintenance, self::Lost, self::WrittenOff], true),
            self::Maintenance => in_array($to, [self::Active, self::WrittenOff], true),
            self::Lost => in_array($to, [self::Active, self::WrittenOff], true),
            self::WrittenOff => $to === self::Active, // restauração: só Administrador
        };
    }

    public function requiresReason(): bool
    {
        return in_array($this, [self::Lost, self::WrittenOff], true);
    }
}
