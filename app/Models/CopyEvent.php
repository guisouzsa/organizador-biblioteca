<?php

namespace App\Models;

use App\Enums\CopyEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Histórico do exemplar. Append-only. */
class CopyEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['copy_id', 'type', 'user_id', 'loan_id', 'payload'];

    protected function casts(): array
    {
        return ['type' => CopyEventType::class, 'payload' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
