<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDOException;
use Throwable;

class CheckDatabaseConnection extends Command
{
    protected $signature = 'db:check-connection {--connection= : Nome da conexão (padrão: a conexão atual)}';

    protected $description = 'Verifica a conexão com o PostgreSQL (somente leitura, sem expor credenciais)';

    public function handle(): int
    {
        $name = $this->option('connection') ?: config('database.default');
        $config = config("database.connections.{$name}");

        $this->line("Verificação de conexão PostgreSQL (conexão: {$name})");
        $this->newLine();

        if (($config['driver'] ?? null) !== 'pgsql') {
            return $this->fail_('A conexão não usa o driver pgsql (DB_CONNECTION=pgsql).');
        }

        foreach (['pdo_pgsql', 'pgsql'] as $extension) {
            if (! extension_loaded($extension)) {
                return $this->fail_("Extensão PHP '{$extension}' não está carregada (habilite no php.ini).");
            }
        }
        $this->ok('Extensões pdo_pgsql e pgsql carregadas.');

        $missing = array_filter(['host' => 'DB_HOST', 'database' => 'DB_DATABASE', 'username' => 'DB_USERNAME', 'password' => 'DB_PASSWORD'],
            fn ($env, $key) => blank($config[$key] ?? null), ARRAY_FILTER_USE_BOTH);
        if ($missing) {
            return $this->fail_('Variáveis ausentes no .env: '.implode(', ', $missing).'.');
        }

        $this->ok(sprintf('Configuração: host=%s porta=%s banco=%s usuário=%s sslmode=%s',
            $config['host'], $config['port'], $config['database'], $config['username'], $config['sslmode'] ?? 'prefer'));

        try {
            $pdo = DB::connection($name)->getPdo();
            $this->ok('Conexão, autenticação e seleção do banco concluídas.');

            $version = DB::connection($name)->selectOne('select version() as v')->v;
            $database = DB::connection($name)->selectOne('select current_database() as d')->d;
            $user = DB::connection($name)->selectOne('select current_user as u')->u;
            $ssl = DB::connection($name)->selectOne('select ssl, version as tls from pg_stat_ssl where pid = pg_backend_pid()');

            $this->ok("Versão: {$version}");
            $this->ok("Banco atual: {$database} | usuário: {$user}");

            if ($ssl && $ssl->ssl) {
                $this->ok("SSL ativo na conexão ({$ssl->tls}).");
            } elseif (($config['sslmode'] ?? 'prefer') === 'require') {
                return $this->fail_('SSL exigido, mas a conexão não está usando SSL.');
            } else {
                $this->warn('  [AVISO] A conexão não está usando SSL.');
            }

            unset($pdo);
        } catch (Throwable $e) {
            return $this->fail_($this->classify($e));
        }

        $this->newLine();
        $this->info('Resultado: conexão com o PostgreSQL VALIDADA (consultas somente leitura).');

        return self::SUCCESS;
    }

    /** Classifica a falha sem vazar credenciais. */
    private function classify(Throwable $e): string
    {
        $message = $e->getMessage();
        $sqlstate = $e instanceof PDOException ? (string) $e->getCode() : '';

        return match (true) {
            (bool) preg_match('/could not translate host name|name or service not known|no such host|getaddrinfo/i', $message) => 'DNS: o host não foi resolvido (host incorreto ou sem rede/IPv6 para este endereço).',
            (bool) preg_match('/timed? out|timeout/i', $message) => 'Rede/porta: tempo esgotado ao conectar (firewall, porta ou IPv6 indisponível).',
            (bool) preg_match('/connection refused|network is unreachable|no route to host|could not connect/i', $message) => 'Rede/porta: não foi possível conectar ao host/porta informados.',
            (bool) preg_match('/password authentication failed|authentication failed|SASL/i', $message) || $sqlstate === '28P01' => 'Autenticação: usuário ou senha rejeitados pelo PostgreSQL.',
            (bool) preg_match('/Tenant or user not found/i', $message) => 'Autenticação: pooler não reconhece o usuário (use postgres.<ref-do-projeto>) ou o host/região está incorreto.',
            (bool) preg_match('/database ".*" does not exist/i', $message) => 'Banco: o banco informado em DB_DATABASE não existe.',
            (bool) preg_match('/ssl|tls/i', $message) => 'SSL: falha na negociação SSL (revise DB_SSLMODE).',
            default => 'Falha não classificada ('.class_basename($e).', SQLSTATE '.($sqlstate ?: 'n/d').'). Revise a configuração.',
        };
    }

    private function ok(string $message): void
    {
        $this->line("  <fg=green>[OK]</>    {$message}");
    }

    private function fail_(string $message): int
    {
        $this->line("  <fg=red>[FALHA]</> {$message}");
        $this->newLine();
        $this->error('Resultado: N�O VALIDADO.');

        return self::FAILURE;
    }
}
