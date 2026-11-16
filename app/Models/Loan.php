<?php

namespace App\Models;

use App\Enums\LoanOutcome;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    protected $fillable = [
        'copy_id', 'borrower_id', 'loaned_by', 'loaned_at', 'due_on', 'returned_at',
        'returned_by', 'outcome', 'loan_rule_id', 'notes', 'demo_batch',
    ];

    protected function casts(): array
    {
        return [
            'loaned_at' => 'datetime',
            'due_on' => 'date:Y-m-d',
            'returned_at' => 'datetime',
            'outcome' => LoanOutcome::class,
        ];
    }

    public function copy(): BelongsTo
    {
        return $this->belongsTo(BookCopy::class, 'copy_id');
    }

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(Borrower::class);
    }

    public function loanedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'loaned_by');
    }

    public function returnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(LoanRule::class, 'loan_rule_id');
    }

    public function renewals(): HasMany
    {
        return $this->hasMany(LoanRenewal::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('returned_at');
    }

    /** Atraso é derivado (RN-CIR-12): ativo e vencido antes de hoje. */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNull('returned_at')->where('due_on', '<', today()->toDateString());
    }

    public function isActive(): bool
    {
        return $this->returned_at === null;
    }

    public function isOverdue(): bool
    {
        return $this->isActive() && $this->due_on->lt(today());
    }

    public function daysOverdue(): int
    {
        return $this->isOverdue() ? (int) $this->due_on->diffInDays(today()) : 0;
    }
}
