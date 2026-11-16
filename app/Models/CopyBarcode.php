<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CopyBarcode extends Model
{
    protected $fillable = ['copy_id', 'code', 'active', 'replaced_at', 'replaced_reason'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'replaced_at' => 'datetime'];
    }

    public function copy(): BelongsTo
    {
        return $this->belongsTo(BookCopy::class, 'copy_id');
    }
}
