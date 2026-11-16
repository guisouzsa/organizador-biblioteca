<?php

namespace App\Services\People;

use App\Enums\BorrowerStatus;
use App\Enums\ErrorCode;
use App\Exceptions\Domain\DomainException;
use App\Models\Borrower;
use App\Services\Admin\AuditLogger;
use App\Services\Circulation\ReservationService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class BorrowerService
{
    public function __construct(private AuditLogger $audit, private ReservationService $reservations) {}

    /** @param  array<string, mixed>  $data */
    public function create(array $data): Borrower
    {
        $this->guardRegistration($data['type'], $data['registration_number'] ?? null);

        try {
            return DB::transaction(function () use ($data) {
                $borrower = Borrower::create($this->attributes($data) + ['status' => BorrowerStatus::Active, 'status_changed_at' => now()]);
                $this->audit->record('borrower.created', $borrower, [], ['name' => $borrower->name, 'type' => $borrower->type->value]);

                return $borrower;
            });
        } catch (UniqueConstraintViolationException) {
            throw DomainException::of(ErrorCode::MatriculaDuplicada);
        }
    }

    /** @param  array<string, mixed>  $data */
    public function update(Borrower $borrower, array $data): Borrower
    {
        $this->guardRegistration($data['type'], $data['registration_number'] ?? null, $borrower->id);

        try {
            return DB::transaction(function () use ($borrower, $data) {
                $fields = ['type', 'name', 'registration_number', 'class_group', 'email', 'phone'];
                $normalize = fn (array $a) => array_map(fn ($v) => $v instanceof \BackedEnum ? $v->value : $v, $a);

                $before = $normalize($borrower->only($fields));
                $borrower->update($this->attributes($data));
                [$old, $new] = AuditLogger::diff($before, $normalize($borrower->refresh()->only($fields)));

                if ($old || $new) {
                    $this->audit->record('borrower.updated', $borrower, $old, $new);
                }

                return $borrower;
            });
        } catch (UniqueConstraintViolationException) {
            throw DomainException::of(ErrorCode::MatriculaDuplicada);
        }
    }

    /** Muda a situação sem apagar histórico (RN-PES-02). */
    public function changeStatus(Borrower $borrower, BorrowerStatus $status, ?string $reason = null): Borrower
    {
        return DB::transaction(function () use ($borrower, $status, $reason) {
            $borrower = Borrower::whereKey($borrower->id)->lockForUpdate()->firstOrFail();

            if ($borrower->status === $status) {
                return $borrower;
            }

            $active = $borrower->activeLoans()->count();
            if ($status !== BorrowerStatus::Active && $active > 0) {
                throw DomainException::of(
                    ErrorCode::OperacaoInvalida,
                    "Este leitor tem {$active} empréstimo(s) ativo(s). Registre as devoluções antes de alterar a situação.",
                    ['borrower_id' => $borrower->id],
                );
            }

            $from = $borrower->status;
            $borrower->update(['status' => $status, 'status_changed_at' => now()]);

            $cancelled = $status !== BorrowerStatus::Active
                ? $this->reservations->cancelAllFor($borrower, 'Leitor '.mb_strtolower($status->label()))
                : 0;

            $this->audit->record('borrower.status_changed', $borrower, ['status' => $from->value], ['status' => $status->value, 'reason' => $reason, 'reservations_cancelled' => $cancelled]);

            return $borrower;
        });
    }

    /** LGPD: troca dados pessoais por marcador, preservando o histórico de circulação. Irreversível. */
    public function anonymize(Borrower $borrower): Borrower
    {
        return DB::transaction(function () use ($borrower) {
            $borrower = Borrower::whereKey($borrower->id)->lockForUpdate()->firstOrFail();

            if ($borrower->activeLoans()->exists()) {
                throw DomainException::of(ErrorCode::OperacaoInvalida, 'Este leitor tem empréstimos ativos. Registre as devoluções antes de anonimizar.');
            }
            $this->reservations->cancelAllFor($borrower, 'Leitor anonimizado');

            $borrower->update([
                'name' => 'Leitor anonimizado #'.$borrower->id, 'registration_number' => null, 'class_group' => null,
                'email' => null, 'phone' => null, 'status' => BorrowerStatus::Inactive, 'status_changed_at' => now(),
                'anonymized_at' => now(),
            ]);
            // O log não guarda o dado anterior (seria vazar o que se quer apagar).
            $this->audit->record('borrower.anonymized', $borrower);

            return $borrower;
        });
    }

    /** Matrícula única por tipo; oferece abrir o leitor existente (UC-PES-005). */
    private function guardRegistration(mixed $type, ?string $registration, ?int $exceptId = null): void
    {
        if (blank($registration)) {
            return;
        }
        $type = $type instanceof \BackedEnum ? $type->value : $type;

        $existing = Borrower::where('type', $type)->where('registration_number', trim($registration))
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))->first();

        if ($existing) {
            throw DomainException::of(ErrorCode::MatriculaDuplicada, "Já existe um leitor com esta matrícula: {$existing->name} ({$existing->status->label()}).", ['borrower_id' => $existing->id]);
        }
    }

    /** @param  array<string, mixed>  $data */
    private function attributes(array $data): array
    {
        $blank = fn ($v) => is_string($v) && trim($v) === '' ? null : (is_string($v) ? trim($v) : $v);

        $attributes = [
            'type' => $data['type'], 'name' => trim($data['name']),
            'registration_number' => $blank($data['registration_number'] ?? null),
            'class_group' => $blank($data['class_group'] ?? null),
        ];

        // Contatos só mudam quando o campo foi enviado (perfis sem permissão de contatos não os veem nem os apagam).
        foreach (['email', 'phone'] as $field) {
            if (array_key_exists($field, $data)) {
                $attributes[$field] = $blank($data[$field]);
            }
        }

        return $attributes;
    }
}
