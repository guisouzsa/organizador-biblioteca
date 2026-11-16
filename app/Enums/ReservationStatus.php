<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case Waiting = 'waiting';
    case Ready = 'ready';
    case Fulfilled = 'fulfilled';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'Na fila',
            self::Ready => 'Pronta para retirada',
            self::Fulfilled => 'Atendida',
            self::Cancelled => 'Cancelada',
            self::Expired => 'Expirada',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Waiting, self::Ready], true);
    }
}
