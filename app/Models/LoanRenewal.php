<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanRenewal extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['loan_id', 'renewed_by', 'previous_due_on', 'new_due_on'];

    protected function casts(): array
    {
        return ['previous_due_on' => 'date:Y-m-d', 'new_due_on' => 'date:Y-m-d', 'created_at' => 'datetime'];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function renewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'renewed_by');
    }
}
