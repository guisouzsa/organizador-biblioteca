<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    protected $fillable = [
        'book_id', 'borrower_id', 'status', 'requested_at', 'copy_id', 'ready_at', 'expires_at',
        'fulfilled_loan_id', 'cancelled_by', 'cancel_reason', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
            'requested_at' => 'datetime',
            'ready_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(Borrower::class);
    }

    public function copy(): BelongsTo
    {
        return $this->belongsTo(BookCopy::class, 'copy_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', ['waiting', 'ready']);
    }

    /** Posição na fila (1 = próximo) entre as reservas em espera do mesmo livro. */
    public function queuePosition(): ?int
    {
        if ($this->status !== ReservationStatus::Waiting) {
            return null;
        }

        return 1 + static::where('book_id', $this->book_id)->where('status', 'waiting')
            ->where(fn ($q) => $q->where('requested_at', '<', $this->requested_at)
                ->orWhere(fn ($q2) => $q2->where('requested_at', $this->requested_at)->where('id', '<', $this->id)))
            ->count();
    }
}
