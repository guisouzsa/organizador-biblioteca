<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tabelas criadas depois da primeira migration de RLS também precisam de proteção
 * (a Data API do Supabase expõe o schema "public"). Idempotente; sem efeito no SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->toggle('enable');
    }

    public function down(): void
    {
        // Não desativa RLS: a migration original (130000) é a responsável pelo down.
    }

    private function toggle(string $action): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (DB::select('select tablename from pg_tables where schemaname = current_schema()') as $table) {
            DB::statement(sprintf('alter table "%s" %s row level security', str_replace('"', '""', $table->tablename), $action));
        }
    }
};
