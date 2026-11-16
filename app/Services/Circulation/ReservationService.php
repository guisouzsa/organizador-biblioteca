<?php

namespace App\Services\Circulation;

use App\Enums\BorrowerStatus;
use App\Enums\CopyEventType;
use App\Enums\ErrorCode;
use App\Enums\ReservationStatus;
use App\Exceptions\Domain\DomainException;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Borrower;
use App\Models\CopyEvent;
use App\Models\LoanRule;
use App\Models\Reservation;
use App\Services\Admin\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Reservas por livro (edição). Fila FIFO por `requested_at` (RN-CIR-09…11).
 * Quando um exemplar fica livre, é retido para o primeiro da fila.
 */
class ReservationService
{
    public function __construct(private AuditLogger $audit) {}

    public function reserve(Book $book, Borrower $borrower): Reservation
    {
        try {
            return DB::transaction(function () use ($book, $borrower) {
                if ($book->isArchived()) {
                    throw DomainException::of(ErrorCode::LivroArquivado);
                }
                if ($borrower->status !== BorrowerStatus::Active) {
                    throw DomainException::of(ErrorCode::LeitorInativo);
                }
                if (! $book->copies()->where('status', 'active')->exists()) {
                    throw DomainException::of(ErrorCode::LivroSemExemplares, 'Este livro não tem exemplares em circulação para reservar.');
                }
                if ($borrower->reservations()->where('book_id', $book->id)->active()->exists()) {
                    throw DomainException::of(ErrorCode::ReservaDuplicada);
                }
                $hasThisBook = $borrower->activeLoans()->whereHas('copy', fn ($q) => $q->where('book_id', $book->id))->exists();
                if ($hasThisBook) {
                    throw DomainException::of(ErrorCode::OperacaoInvalida, 'Este leitor já está com um exemplar deste livro.');
                }
                if ($book->copies()->available()->exists()) {
                    throw DomainException::of(ErrorCode::ReservaDesnecessaria);
                }

                $reservation = Reservation::create([
                    'book_id' => $book->id, 'borrower_id' => $borrower->id, 'status' => ReservationStatus::Waiting,
                    'requested_at' => now(), 'created_by' => Auth::id(),
                ]);
                $this->audit->record('reservation.created', $reservation, [], ['book_id' => $book->id, 'borrower_id' => $borrower->id]);

                return $reservation;
            });
        } catch (UniqueConstraintViolationException) {
            throw DomainException::of(ErrorCode::ReservaDuplicada);
        }
    }

    public function cancel(Reservation $reservation, ?string $reason = null): void
    {
        DB::transaction(function () use ($reservation, $reason) {
            $reservation = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if (! $reservation->status->isActive()) {
                throw DomainException::of(ErrorCode::TransicaoInvalida, 'Esta reserva não pode mais ser cancelada.');
            }

            $wasReady = $reservation->status === ReservationStatus::Ready;
            $reservation->update([
                'status' => ReservationStatus::Cancelled, 'cancelled_by' => Auth::id(),
                'cancel_reason' => $reason, 'copy_id' => $wasReady ? $reservation->copy_id : null,
            ]);
            $this->audit->record('reservation.cancelled', $reservation, ['status' => $wasReady ? 'ready' : 'waiting'], ['status' => 'cancelled', 'reason' => $reason]);

            // A cópia retida é liberada e pode atender o próximo da fila.
            if ($wasReady) {
                $this->assignFreeCopies($reservation->book);
            }
        });
    }

    /** Cancela todas as reservas ativas de um leitor (ex.: ao ser inativado). */
    public function cancelAllFor(Borrower $borrower, string $reason): int
    {
        $count = 0;
        foreach ($borrower->reservations()->active()->get() as $reservation) {
            $this->cancel($reservation, $reason);
            $count++;
        }

        return $count;
    }

    /**
     * Retém exemplares livres para as reservas em espera do livro, em ordem de chegada.
     * Chamada após devolução, novo exemplar, cancelamento, expiração e retorno à circulação.
     */
    public function assignFreeCopies(Book $book): int
    {
        return DB::transaction(function () use ($book) {
            $waiting = Reservation::where('book_id', $book->id)->where('status', ReservationStatus::Waiting->value)
                ->orderBy('requested_at')->orderBy('id')->lockForUpdate()->get();

            if ($waiting->isEmpty()) {
                return 0;
            }

            $free = $book->copies()->available()->orderBy('id')->lockForUpdate()->get();
            $assigned = 0;

            foreach ($free as $copy) {
                $reservation = $waiting->shift();
                if (! $reservation) {
                    break;
                }

                $reservation->update([
                    'status' => ReservationStatus::Ready, 'copy_id' => $copy->id,
                    'ready_at' => now(), 'expires_at' => $this->expiresAt($reservation->borrower),
                ]);
                CopyEvent::create([
                    'copy_id' => $copy->id, 'type' => CopyEventType::Held, 'user_id' => Auth::id(),
                    'payload' => ['reservation_id' => $reservation->id, 'borrower_id' => $reservation->borrower_id],
                ]);
                $assigned++;
            }

            return $assigned;
        });
    }

    /** Exemplar saiu de circulação: a reserva volta ao início da fila (mantém a ordem original). */
    public function releaseHold(BookCopy $copy): void
    {
        $held = Reservation::where('copy_id', $copy->id)->where('status', ReservationStatus::Ready->value)->lockForUpdate()->first();
        if (! $held) {
            return;
        }

        $held->update(['status' => ReservationStatus::Waiting, 'copy_id' => null, 'ready_at' => null, 'expires_at' => null]);
        $this->assignFreeCopies($copy->book);
    }

    /** Expira retenções vencidas e repassa o exemplar ao próximo da fila. Idempotente. */
    public function expireDue(): int
    {
        $expired = 0;

        $due = Reservation::where('status', ReservationStatus::Ready->value)
            ->whereNotNull('expires_at')->where('expires_at', '<=', now())->get();

        foreach ($due as $reservation) {
            DB::transaction(function () use ($reservation, &$expired) {
                $fresh = Reservation::whereKey($reservation->id)->lockForUpdate()->first();
                if (! $fresh || $fresh->status !== ReservationStatus::Ready) {
                    return;
                }
                $fresh->update(['status' => ReservationStatus::Expired]);
                $this->audit->record('reservation.expired', $fresh);
                $this->assignFreeCopies($fresh->book);
                $expired++;
            });
        }

        return $expired;
    }

    private function expiresAt(Borrower $borrower): ?Carbon
    {
        $hours = LoanRule::where('borrower_type', $borrower->type->value)->value('reservation_hold_hours');

        return $hours ? now()->addHours((int) $hours) : null;
    }
}
