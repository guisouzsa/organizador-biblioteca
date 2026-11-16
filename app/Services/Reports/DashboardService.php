<?php

namespace App\Services\Reports;

use App\Enums\BorrowerStatus;
use App\Enums\BorrowerType;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Borrower;
use App\Models\Loan;
use App\Models\LoanRule;
use App\Models\Reservation;

/** Indicadores do painel: sempre derivados de consultas ao banco (RN-GES-02). */
class DashboardService
{
    /** @return array<string, int> */
    public function counters(): array
    {
        $copies = BookCopy::query()->count();

        return [
            'books' => Book::active()->count(),
            'copies' => $copies,
            'copies_available' => BookCopy::available()->count(),
            'copies_on_loan' => Loan::active()->count(),
            'copies_unavailable' => BookCopy::whereIn('status', ['maintenance', 'lost', 'written_off'])->count(),
            'borrowers_active' => Borrower::where('status', BorrowerStatus::Active->value)->count(),
            'loans_active' => Loan::active()->count(),
            'loans_overdue' => Loan::overdue()->count(),
            'reservations_pending' => Reservation::active()->count(),
            'reservations_ready' => Reservation::where('status', 'ready')->count(),
        ];
    }

    /**
     * Pendências de configuração: tipos de leitor com leitores ativos e sem regra de empréstimo.
     *
     * @return list<BorrowerType>
     */
    public function typesWithoutRule(): array
    {
        $configured = LoanRule::pluck('borrower_type')->map(fn ($t) => $t->value)->all();

        return collect(BorrowerType::cases())
            ->filter(fn (BorrowerType $type) => ! in_array($type->value, $configured, true))
            ->filter(fn (BorrowerType $type) => Borrower::where('type', $type->value)->where('status', 'active')->exists())
            ->values()->all();
    }

    public function ruleConfiguredAtAll(): bool
    {
        return LoanRule::query()->exists();
    }
}
