<?php

namespace App\Services\Circulation;

use App\Enums\CopyCondition;
use App\Enums\CopyEventType;
use App\Enums\CopyStatus;
use App\Enums\ErrorCode;
use App\Enums\LoanOutcome;
use App\Enums\ReservationStatus;
use App\Exceptions\Domain\DomainException;
use App\Models\BookCopy;
use App\Models\Borrower;
use App\Models\CopyEvent;
use App\Models\Loan;
use App\Models\LoanRenewal;
use App\Models\Reservation;
use App\Services\Admin\AuditLogger;
use App\Services\Inventory\CopyBarcodeService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Empréstimo, devolução, renovação e estorno.
 *
 * Toda operação é uma única transação. Contra empréstimos simultâneos do mesmo exemplar há
 * duas barreiras: lock de linha (FOR UPDATE) e o índice único parcial loans_one_active_per_copy.
 * A disponibilidade é derivada dos empréstimos, então não existe estado a dessincronizar.
 */
class LoanService
{
    public function __construct(
        private CopyBarcodeService $barcodes,
        private LoanRuleResolver $rules,
        private ReservationService $reservations,
        private AuditLogger $audit,
    ) {}

    /** @throws DomainException */
    public function checkout(string $codeOrAccession, Borrower $borrower, bool $acknowledgeOverdue = false): Loan
    {
        $resolved = $this->barcodes->resolve($codeOrAccession);
        if (! $resolved) {
            throw DomainException::of(ErrorCode::ExemplarNaoEncontrado);
        }

        try {
            return DB::transaction(function () use ($resolved, $borrower, $acknowledgeOverdue) {
                // Ordem fixa de locks (exemplar → leitor) evita deadlock entre operações.
                $copy = BookCopy::whereKey($resolved['copy']->id)->lockForUpdate()->firstOrFail();
                $borrower = Borrower::whereKey($borrower->id)->lockForUpdate()->firstOrFail();

                if ($copy->status !== CopyStatus::Active) {
                    throw DomainException::of(ErrorCode::ExemplarIndisponivel, "Este exemplar está “{$copy->status->label()}” e não circula.", ['copy_id' => $copy->id]);
                }

                $current = $copy->loans()->whereNull('returned_at')->first();
                if ($current) {
                    throw DomainException::of(ErrorCode::ExemplarJaEmprestado, 'Este exemplar já está emprestado (devolução prevista em '.$current->due_on->format('d/m/Y').').', ['copy_id' => $copy->id]);
                }

                if (! $borrower->isActive()) {
                    throw DomainException::of(ErrorCode::LeitorInativo, "Este leitor está “{$borrower->status->label()}” e não pode pegar livros emprestados.", ['borrower_id' => $borrower->id]);
                }

                $rule = $this->rules->forBorrower($borrower);

                $active = $borrower->activeLoans()->count();
                if ($rule->max_active_loans !== null && $active >= $rule->max_active_loans) {
                    throw DomainException::of(ErrorCode::LimiteEmprestimos, "Este leitor já tem {$active} empréstimo(s) ativo(s) (limite: {$rule->max_active_loans}).", ['borrower_id' => $borrower->id]);
                }

                $overdue = $borrower->activeLoans()->where('due_on', '<', today()->toDateString())->count();
                if ($overdue > 0 && ($rule->block_when_overdue || ! $acknowledgeOverdue)) {
                    throw DomainException::of(
                        ErrorCode::LeitorComAtraso,
                        $rule->block_when_overdue
                            ? "Este leitor tem {$overdue} empréstimo(s) em atraso. Novos empréstimos estão bloqueados até a devolução."
                            : "Este leitor tem {$overdue} empréstimo(s) em atraso. Confirme para emprestar mesmo assim.",
                        ['borrower_id' => $borrower->id, 'needs_confirmation' => ! $rule->block_when_overdue],
                    );
                }

                // Retenção para reserva: vencida é expirada na hora (não depende do agendador).
                $held = Reservation::where('copy_id', $copy->id)->where('status', ReservationStatus::Ready->value)->lockForUpdate()->first();
                if ($held && $held->expires_at && $held->expires_at->isPast()) {
                    $held->update(['status' => ReservationStatus::Expired]);
                    $this->reservations->assignFreeCopies($copy->book);
                    $held = Reservation::where('copy_id', $copy->id)->where('status', ReservationStatus::Ready->value)->first();
                }
                if ($held && $held->borrower_id !== $borrower->id) {
                    throw DomainException::of(ErrorCode::ExemplarRetidoOutro, 'Este exemplar está reservado para outro leitor'.($held->expires_at ? ' até '.$held->expires_at->format('d/m H:i') : '').'.', ['copy_id' => $copy->id]);
                }

                $loan = Loan::create([
                    'copy_id' => $copy->id, 'borrower_id' => $borrower->id, 'loaned_by' => Auth::id(),
                    'loaned_at' => now(), 'due_on' => today()->addDays($rule->loan_days),
                    'loan_rule_id' => $rule->id,
                ]);

                if ($held) {
                    $held->update(['status' => ReservationStatus::Fulfilled, 'fulfilled_loan_id' => $loan->id]);
                }

                $this->event($copy, CopyEventType::Loaned, $loan, ['borrower_id' => $borrower->id, 'due_on' => $loan->due_on->toDateString()]);
                $this->audit->record('loan.created', $loan, [], ['copy_id' => $copy->id, 'borrower_id' => $borrower->id, 'due_on' => $loan->due_on->toDateString()]);

                return $loan->load(['copy.book', 'copy.activeBarcode', 'borrower']);
            });
        } catch (UniqueConstraintViolationException) {
            // Corrida: outro operador emprestou a mesma cópia no mesmo instante.
            throw DomainException::of(ErrorCode::ExemplarJaEmprestado, 'Este exemplar acabou de ser emprestado por outro operador.', ['copy_id' => $resolved['copy']->id]);
        }
    }

    /**
     * @return array{loan: Loan, days_overdue: int, held_for: ?Borrower}
     *
     * @throws DomainException
     */
    public function return(string $codeOrAccession, ?CopyCondition $condition = null, ?Borrower $hint = null): array
    {
        $resolved = $this->barcodes->resolve($codeOrAccession);
        if (! $resolved) {
            throw DomainException::of(ErrorCode::ExemplarNaoEncontrado);
        }

        return DB::transaction(function () use ($resolved, $condition, $hint) {
            $copy = BookCopy::whereKey($resolved['copy']->id)->lockForUpdate()->firstOrFail();
            $loan = $copy->loans()->whereNull('returned_at')->lockForUpdate()->first();

            if (! $loan) {
                // UC-CIR-008: o leitor traz outra cópia do mesmo livro?
                $other = $hint?->activeLoans()->whereHas('copy', fn ($q) => $q->where('book_id', $copy->book_id))->first();
                if ($other) {
                    throw DomainException::of(
                        ErrorCode::DevolucaoSemEmprestimo,
                        'Este exemplar não está emprestado, mas este leitor tem outra cópia do mesmo livro emprestada ('.$other->copy->code().'). Confira a etiqueta ou registre a devolução da cópia correta.',
                        ['copy_id' => $other->copy_id],
                    );
                }

                throw DomainException::of(ErrorCode::DevolucaoSemEmprestimo, 'Este exemplar não consta como emprestado ('.$copy->code().').', ['copy_id' => $copy->id]);
            }

            $loan->update(['returned_at' => now(), 'returned_by' => Auth::id(), 'outcome' => LoanOutcome::Returned]);
            $this->event($copy, CopyEventType::Returned, $loan, ['borrower_id' => $loan->borrower_id]);

            if ($condition && $condition !== $copy->condition) {
                $from = $copy->condition->value;
                $copy->update(['condition' => $condition]);
                $this->event($copy, CopyEventType::ConditionChanged, $loan, ['from' => $from, 'to' => $condition->value]);
            }

            $this->audit->record('loan.returned', $loan, [], ['copy_id' => $copy->id, 'days_overdue' => $this->daysLate($loan)]);

            // Devolveu: se há fila, retém o exemplar para o primeiro reservante.
            $this->reservations->assignFreeCopies($copy->book);
            $heldFor = Reservation::where('copy_id', $copy->id)->where('status', ReservationStatus::Ready->value)->with('borrower')->first()?->borrower;

            return ['loan' => $loan->load(['copy.book', 'copy.activeBarcode', 'borrower']), 'days_overdue' => $this->daysLate($loan), 'held_for' => $heldFor];
        });
    }

    /** @throws DomainException */
    public function renew(Loan $loan): Loan
    {
        return DB::transaction(function () use ($loan) {
            $loan = Loan::whereKey($loan->id)->lockForUpdate()->firstOrFail();
            $loan->load(['borrower', 'copy']);

            $deny = fn (string $why) => DomainException::of(ErrorCode::RenovacaoNegada, "Não é possível renovar: {$why}");

            if (! $loan->isActive()) {
                throw $deny('o empréstimo já foi encerrado.');
            }
            if (! $loan->borrower->isActive()) {
                throw $deny('o leitor não está ativo.');
            }

            $rule = $this->rules->forBorrower($loan->borrower);
            $renewals = $loan->renewals()->count();

            if ($rule->max_renewals !== null && $renewals >= $rule->max_renewals) {
                throw $deny($rule->max_renewals === 0 ? 'as renovações não são permitidas para este tipo de leitor.' : "o limite de {$rule->max_renewals} renovação(ões) foi atingido.");
            }
            $isOverdue = $loan->isOverdue();
            if ($isOverdue && ! $rule->renew_when_overdue) {
                throw $deny('o empréstimo está vencido. Registre a devolução.');
            }
            if (Reservation::where('book_id', $loan->copy->book_id)->where('status', ReservationStatus::Waiting->value)->exists()) {
                throw $deny('há outra pessoa aguardando este livro.');
            }

            $previous = $loan->due_on->copy();
            $base = $isOverdue ? today() : $loan->due_on->copy();
            $newDue = $base->addDays($rule->renewal_days ?? $rule->loan_days);

            LoanRenewal::create(['loan_id' => $loan->id, 'renewed_by' => Auth::id(), 'previous_due_on' => $previous, 'new_due_on' => $newDue]);
            $loan->update(['due_on' => $newDue]);

            $this->event($loan->copy, CopyEventType::Renewed, $loan, ['from' => $previous->toDateString(), 'to' => $newDue->toDateString()]);
            $this->audit->record('loan.renewed', $loan, ['due_on' => $previous->toDateString()], ['due_on' => $newDue->toDateString()]);

            return $loan;
        });
    }

    /** Estorno (correção de lançamento por engano). Não apaga: marca como estornado (RN-CIR-14). */
    public function void(Loan $loan, string $reason): Loan
    {
        return DB::transaction(function () use ($loan, $reason) {
            $loan = Loan::whereKey($loan->id)->lockForUpdate()->firstOrFail();

            if ($loan->outcome === LoanOutcome::Voided) {
                throw DomainException::of(ErrorCode::TransicaoInvalida, 'Este empréstimo já foi estornado.');
            }

            $wasActive = $loan->isActive();
            $loan->update([
                'outcome' => LoanOutcome::Voided,
                'returned_at' => $loan->returned_at ?? now(),
                'returned_by' => $loan->returned_by ?? Auth::id(),
                'notes' => mb_substr('Estorno: '.$reason, 0, 255),
            ]);

            $this->event($loan->copy, CopyEventType::LoanVoided, $loan, ['reason' => $reason]);
            $this->audit->record('loan.voided', $loan, ['outcome' => $wasActive ? null : 'returned'], ['outcome' => 'voided', 'reason' => $reason]);

            if ($wasActive) {
                $this->reservations->assignFreeCopies($loan->copy->book);
            }

            return $loan;
        });
    }

    private function daysLate(Loan $loan): int
    {
        if (! $loan->returned_at) {
            return 0;
        }

        $returnedOn = $loan->returned_at->copy()->startOfDay();
        $dueOn = $loan->due_on->copy()->startOfDay();

        return $returnedOn->gt($dueOn) ? (int) $dueOn->diffInDays($returnedOn) : 0;
    }

    /** @param  array<string, mixed>  $payload */
    private function event(BookCopy $copy, CopyEventType $type, Loan $loan, array $payload = []): void
    {
        CopyEvent::create(['copy_id' => $copy->id, 'type' => $type, 'user_id' => Auth::id(), 'loan_id' => $loan->id, 'payload' => $payload ?: null]);
    }
}
