<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Supabase expõe o schema "public" pela Data API (PostgREST). Sem RLS, qualquer pessoa com a
 * chave publicável (que é pública por design) poderia ler/gravar estas tabelas.
 *
 * Habilitar RLS sem criar policies nega todo acesso por anon/authenticated. O Laravel acessa
 * o banco diretamente como dono das tabelas (que não é sujeito a RLS), então a aplicação
 * continua funcionando. Não afeta SQLite.
 *
 * Tabelas criadas depois desta migration precisam de RLS próprio (ou desative a Data API).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->toggle('enable');
    }

    public function down(): void
    {
        $this->toggle('disable');
    }

    private function toggle(string $action): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $tables = DB::select('select tablename from pg_tables where schemaname = current_schema()');

        foreach ($tables as $table) {
            DB::statement(sprintf('alter table "%s" %s row level security', str_replace('"', '""', $table->tablename), $action));
        }
    }
};
