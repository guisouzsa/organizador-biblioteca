<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Registro de auditoria. Append-only: nunca é atualizado nem excluído pela aplicação. */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'event', 'subject_type', 'subject_id', 'old_values', 'new_values', 'ip'];

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subjectLabel(): string
    {
        return $this->subject_type ? class_basename($this->subject_type).' #'.$this->subject_id : '—';
    }
}
