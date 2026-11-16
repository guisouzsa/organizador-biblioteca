<?php

namespace App\Services\Inventory;

use App\Enums\CopyCondition;
use App\Enums\CopyEventType;
use App\Enums\CopyStatus;
use App\Enums\ErrorCode;
use App\Enums\LoanOutcome;
use App\Exceptions\Domain\DomainException;
use App\Models\BookCopy;
use App\Models\CopyEvent;
use App\Services\Admin\AuditLogger;
use App\Services\Circulation\ReservationService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Alterações de um exemplar existente: dados, local, conservação, situação e etiqueta. */
class CopyLifecycleService
{
    public function __construct(
        private CopyBarcodeService $barcodes,
        private AuditLogger $audit,
        private ReservationService $reservations,
    ) {}

    /**
     * Atualiza local, conservação e dados administrativos; cada mudança relevante gera um evento.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(BookCopy $copy, array $data): BookCopy
    {
        try {
            return DB::transaction(function () use ($copy, $data) {
                $copy = BookCopy::whereKey($copy->id)->lockForUpdate()->firstOrFail();
                $before = $copy->only(['location_id', 'condition', 'accession_number', 'acquisition_source', 'acquired_at', 'notes']);

                $copy->fill([
                    'location_id' => $data['location_id'] ?? null,
                    'condition' => $data['condition'] ?? $copy->condition,
                    'accession_number' => filled($data['accession_number'] ?? null) ? trim($data['accession_number']) : null,
                    'acquisition_source' => $data['acquisition_source'] ?? null,
                    'acquired_at' => $data['acquired_at'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ]);

                $locationChanged = $copy->isDirty('location_id');
                $conditionChanged = $copy->isDirty('condition');
                $newCondition = $copy->condition;
                $copy->save();

                if ($locationChanged) {
                    $this->event($copy, CopyEventType::LocationChanged, ['from' => $before['location_id'], 'to' => $copy->location_id]);
                }
                if ($conditionChanged) {
                    $this->event($copy, CopyEventType::ConditionChanged, [
                        'from' => $before['condition']->value ?? null, 'to' => $newCondition instanceof CopyCondition ? $newCondition->value : $newCondition,
                    ]);
                }

                [$old, $new] = AuditLogger::diff(
                    array_map(fn ($v) => $v instanceof \BackedEnum ? $v->value : $v, $before),
                    array_map(fn ($v) => $v instanceof \BackedEnum ? $v->value : $v, $copy->only(array_keys($before))),
                );
                if ($old || $new) {
                    $this->audit->record('copy.updated', $copy, $old, $new);
                }

                return $copy;
            });
        } catch (UniqueConstraintViolationException) {
            throw DomainException::of(ErrorCode::ExemplarDuplicado, 'Este tombo já pertence a outro exemplar.');
        }
    }

    /** Muda a situação administrativa (RN-ACE-06/10). */
    public function transition(BookCopy $copy, CopyStatus $to, ?string $reason = null): BookCopy
    {
        return DB::transaction(function () use ($copy, $to, $reason) {
            $copy = BookCopy::whereKey($copy->id)->lockForUpdate()->firstOrFail();
            $from = $copy->status;

            if ($from === $to || ! $from->canTransitionTo($to)) {
                throw DomainException::of(ErrorCode::TransicaoInvalida, "Não é possível mudar de “{$from->label()}” para “{$to->label()}”.");
            }
            if ($to->requiresReason() && blank($reason)) {
                throw DomainException::of(ErrorCode::OperacaoInvalida, 'Informe o motivo para registrar esta situação.');
            }

            $activeLoan = $copy->loans()->whereNull('returned_at')->lockForUpdate()->first();

            if ($activeLoan && $to === CopyStatus::Maintenance) {
                throw DomainException::of(ErrorCode::OperacaoInvalida, 'Este exemplar está emprestado. Registre a devolução antes de enviá-lo para manutenção.');
            }

            // RN-ACE-10 / RN-CIR-16: perdido ou baixado encerra o empréstimo ativo.
            if ($activeLoan && in_array($to, [CopyStatus::Lost, CopyStatus::WrittenOff], true)) {
                $activeLoan->update(['returned_at' => now(), 'returned_by' => Auth::id(), 'outcome' => LoanOutcome::Lost, 'notes' => 'Encerrado: exemplar '.mb_strtolower($to->label())]);
                $this->event($copy, CopyEventType::StatusChanged, ['note' => 'empréstimo encerrado como perdido'], $activeLoan->id);
            }

            $copy->update([
                'status' => $to,
                'status_reason' => $to->requiresReason() ? $reason : null,
                'status_changed_at' => now(),
            ]);

            $this->event($copy, CopyEventType::StatusChanged, ['from' => $from->value, 'to' => $to->value, 'reason' => $reason]);
            $this->audit->record('copy.status_changed', $copy, ['status' => $from->value], ['status' => $to->value, 'reason' => $reason]);

            // Saiu de circulação: libera reserva retida. Voltou: tenta atender a fila.
            if ($to !== CopyStatus::Active) {
                $this->reservations->releaseHold($copy);
            } else {
                $this->reservations->assignFreeCopies($copy->book);
            }

            return $copy;
        });
    }

    /** Nova etiqueta: o código antigo continua resolvendo para este exemplar (UC-ACE-003). */
    public function relabel(BookCopy $copy, string $reason): BookCopy
    {
        return DB::transaction(function () use ($copy, $reason) {
            $copy = BookCopy::whereKey($copy->id)->lockForUpdate()->firstOrFail();
            $old = $copy->activeBarcode?->code;
            $new = $this->barcodes->relabel($copy, $reason);

            $this->event($copy, CopyEventType::Relabeled, ['from' => $old, 'to' => $new->code, 'reason' => $reason]);
            $this->audit->record('copy.relabeled', $copy, ['code' => $old], ['code' => $new->code]);

            return $copy->load('activeBarcode');
        });
    }

    /** @param  array<string, mixed>  $payload */
    private function event(BookCopy $copy, CopyEventType $type, array $payload = [], ?int $loanId = null): void
    {
        CopyEvent::create(['copy_id' => $copy->id, 'type' => $type, 'user_id' => Auth::id(), 'loan_id' => $loanId, 'payload' => $payload ?: null]);
    }
}
