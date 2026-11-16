<?php

namespace App\Models;

use App\Enums\CopyCondition;
use App\Enums\CopyStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Exemplar físico de um livro (edição). A disponibilidade é derivada (RN-ACE-07). */
class BookCopy extends Model
{
    protected $fillable = [
        'book_id', 'accession_number', 'location_id', 'status', 'condition', 'acquired_at',
        'acquisition_source', 'notes', 'status_reason', 'status_changed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => CopyStatus::class,
            'condition' => CopyCondition::class,
            'acquired_at' => 'date:Y-m-d',
            'status_changed_at' => 'datetime',
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function barcodes(): HasMany
    {
        return $this->hasMany(CopyBarcode::class, 'copy_id');
    }

    public function activeBarcode(): HasOne
    {
        return $this->hasOne(CopyBarcode::class, 'copy_id')->where('active', true);
    }

    public function events(): HasMany
    {
        return $this->hasMany(CopyEvent::class, 'copy_id')->latest('created_at')->latest('id');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class, 'copy_id');
    }

    public function activeLoan(): HasOne
    {
        return $this->hasOne(Loan::class, 'copy_id')->whereNull('returned_at');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'copy_id');
    }

    public function heldReservation(): HasOne
    {
        return $this->hasOne(Reservation::class, 'copy_id')->where('status', 'ready');
    }

    /** Disponível = em acervo, sem empréstimo ativo e sem retenção para reserva. */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', CopyStatus::Active->value)
            ->whereDoesntHave('loans', fn (Builder $l) => $l->whereNull('returned_at'))
            ->whereDoesntHave('reservations', fn (Builder $r) => $r->where('status', 'ready'));
    }

    public function code(): ?string
    {
        return $this->activeBarcode?->code;
    }

    /** @return array{key: string, label: string, tone: string} */
    public function situation(): array
    {
        if ($this->status !== CopyStatus::Active) {
            return ['key' => $this->status->value, 'label' => $this->status->label(), 'tone' => 'rose'];
        }
        if ($this->activeLoan) {
            return ['key' => 'on_loan', 'label' => 'Emprestado', 'tone' => 'amber'];
        }
        if ($this->heldReservation) {
            return ['key' => 'held', 'label' => 'Retido para reserva', 'tone' => 'sky'];
        }

        return ['key' => 'available', 'label' => 'Disponível', 'tone' => 'emerald'];
    }

    public function isCirculable(): bool
    {
        return $this->status === CopyStatus::Active;
    }
}
