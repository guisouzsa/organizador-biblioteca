<?php

namespace App\Services\Admin;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/** Auditoria explícita: chamada pelos Services dentro da mesma transação da operação. */
class AuditLogger
{
    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public function record(string $event, ?Model $subject = null, array $old = [], array $new = []): AuditLog
    {
        return AuditLog::create([
            'user_id' => Auth::id(),
            'event' => $event,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip' => request()?->ip(),
        ]);
    }

    /**
     * Diferenças entre dois estados (somente campos que mudaram).
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public static function diff(array $before, array $after): array
    {
        $old = $new = [];
        foreach ($after as $key => $value) {
            $previous = $before[$key] ?? null;
            if ($previous != $value) { // comparação frouxa: "2020" == 2020
                $old[$key] = $previous;
                $new[$key] = $value;
            }
        }

        return [$old, $new];
    }
}
