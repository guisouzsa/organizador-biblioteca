<?php

namespace App\Models;

use App\Enums\BorrowerType;
use Illuminate\Database\Eloquent\Model;

/** Regras de empréstimo por tipo de leitor. Sem valores padrão (RN-CIR-03). */
class LoanRule extends Model
{
    protected $fillable = [
        'borrower_type', 'loan_days', 'max_active_loans', 'max_renewals', 'renewal_days',
        'renew_when_overdue', 'block_when_overdue', 'reservation_hold_hours',
    ];

    protected function casts(): array
    {
        return [
            'borrower_type' => BorrowerType::class,
            'renew_when_overdue' => 'boolean',
            'block_when_overdue' => 'boolean',
        ];
    }
}
