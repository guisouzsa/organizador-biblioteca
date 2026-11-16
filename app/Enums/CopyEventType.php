<?php

namespace App\Enums;

enum CopyEventType: string
{
    case Registered = 'registered';
    case Relabeled = 'relabeled';
    case LocationChanged = 'location_changed';
    case ConditionChanged = 'condition_changed';
    case StatusChanged = 'status_changed';
    case Loaned = 'loaned';
    case Returned = 'returned';
    case Renewed = 'renewed';
    case LoanVoided = 'loan_voided';
    case Held = 'held';

    public function label(): string
    {
        return match ($this) {
            self::Registered => 'Exemplar registrado',
            self::Relabeled => 'Nova etiqueta gerada',
            self::LocationChanged => 'Localização alterada',
            self::ConditionChanged => 'Conservação alterada',
            self::StatusChanged => 'Situação alterada',
            self::Loaned => 'Emprestado',
            self::Returned => 'Devolvido',
            self::Renewed => 'Empréstimo renovado',
            self::LoanVoided => 'Empréstimo estornado',
            self::Held => 'Retido para reserva',
        };
    }
}
