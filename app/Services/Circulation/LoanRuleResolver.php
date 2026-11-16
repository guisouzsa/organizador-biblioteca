<?php

namespace App\Services\Circulation;

use App\Enums\ErrorCode;
use App\Exceptions\Domain\DomainException;
use App\Models\Borrower;
use App\Models\LoanRule;

/** Regras de empréstimo configuradas pela escola. Sem regra, não há empréstimo (RN-CIR-03). */
class LoanRuleResolver
{
    public function forBorrower(Borrower $borrower): LoanRule
    {
        $rule = LoanRule::where('borrower_type', $borrower->type->value)->first();

        if (! $rule) {
            throw DomainException::of(
                ErrorCode::RegrasNaoConfiguradas,
                "As regras de empréstimo para “{$borrower->type->label()}” ainda não foram configuradas. Peça a um administrador que as defina em Administração → Regras de empréstimo.",
            );
        }

        return $rule;
    }
}
