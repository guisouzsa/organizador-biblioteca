<?php

namespace App\Services\Inventory;

use App\Enums\CopyCondition;
use App\Enums\CopyEventType;
use App\Enums\ErrorCode;
use App\Exceptions\Domain\DomainException;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\CopyEvent;
use App\Services\Admin\AuditLogger;
use App\Services\Admin\SettingsService;
use App\Services\Circulation\ReservationService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CopyRegistrationService
{
    public function __construct(
        private CopyBarcodeService $barcodes,
        private SettingsService $settings,
        private AuditLogger $audit,
        private ReservationService $reservations,
    ) {}

    /**
     * Registra N exemplares de um livro numa única transação (tudo ou nada, RN-ACE-09).
     *
     * @param  array{location_id?: ?int, condition?: ?string, acquired_at?: ?string, acquisition_source?: ?string, notes?: ?string, accession_number?: ?string}  $data
     * @return Collection<int, BookCopy>
     */
    public function register(Book $book, int $quantity, array $data = []): Collection
    {
        if ($book->isArchived()) {
            throw DomainException::of(ErrorCode::LivroArquivado);
        }

        $accession = filled($data['accession_number'] ?? null) ? trim($data['accession_number']) : null;

        if ($quantity > 1 && $accession) {
            throw DomainException::of(ErrorCode::OperacaoInvalida, 'O tombo é individual: informe-o ao registrar uma cópia por vez.');
        }
        if ($quantity > 1 && $this->settings->accessionRequired()) {
            throw DomainException::of(ErrorCode::OperacaoInvalida, 'O tombo é obrigatório nesta biblioteca: registre uma cópia por vez.');
        }
        if ($this->settings->accessionRequired() && ! $accession) {
            throw DomainException::of(ErrorCode::OperacaoInvalida, 'Informe o tombo (número patrimonial) do exemplar.');
        }

        if ($accession && ($other = BookCopy::where('accession_number', $accession)->first())) {
            throw DomainException::of(ErrorCode::ExemplarDuplicado, "O tombo {$accession} já pertence ao exemplar {$other->activeBarcode?->code}.", ['copy_id' => $other->id]);
        }

        try {
            return DB::transaction(function () use ($book, $quantity, $data, $accession) {
                $copies = collect();

                for ($i = 0; $i < $quantity; $i++) {
                    $copy = $book->copies()->create([
                        'accession_number' => $accession,
                        'location_id' => $data['location_id'] ?? null,
                        'condition' => $data['condition'] ?? CopyCondition::Good->value,
                        'acquired_at' => $data['acquired_at'] ?? null,
                        'acquisition_source' => $data['acquisition_source'] ?? null,
                        'notes' => $data['notes'] ?? null,
                        'status_changed_at' => now(),
                    ]);

                    $barcode = $this->barcodes->issue($copy);

                    CopyEvent::create([
                        'copy_id' => $copy->id, 'type' => CopyEventType::Registered, 'user_id' => Auth::id(),
                        'payload' => ['code' => $barcode->code, 'location_id' => $copy->location_id],
                    ]);

                    $copies->push($copy->setRelation('activeBarcode', $barcode));
                }

                $this->audit->record('copy.registered', $book, [], [
                    'quantity' => $quantity, 'codes' => $copies->map(fn ($c) => $c->activeBarcode->code)->all(),
                ]);

                // Um novo exemplar pode atender a fila de reservas do livro.
                $this->reservations->assignFreeCopies($book);

                return $copies;
            });
        } catch (UniqueConstraintViolationException) {
            throw DomainException::of(ErrorCode::ExemplarDuplicado);
        }
    }
}
