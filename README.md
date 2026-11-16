# Biblioteca Escolar

Sistema web de catalogação e gerenciamento de biblioteca escolar.

**Stack:** Laravel 12 (PHP 8.2+), Blade, Tailwind CSS 4, Vite, PostgreSQL (Supabase).

## Requisitos

- PHP 8.2+ com as extensões `pdo_pgsql` e `pgsql` habilitadas
- Composer 2
- Node.js 20+ e npm

> Laravel 13 exige PHP 8.3+. O projeto está no Laravel 12 por compatibilidade com o PHP 8.2 local.

## Instalação

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
```

## Banco de dados (Supabase PostgreSQL)

O Laravel conecta diretamente ao PostgreSQL pelo PDO. Preencha no `.env` os valores do botão **Connect** do projeto Supabase:

| Variável | Observação |
|---|---|
| `DB_HOST` | Host do **Session Pooler** (IPv4) ou da conexão direta (somente IPv6 no plano gratuito) |
| `DB_PORT` | `5432` (Session Pooler / direta). Evite o Transaction Pooler (`6543`) com PDO |
| `DB_DATABASE` | `postgres` |
| `DB_USERNAME` | Pooler: `postgres.<ref-do-projeto>`; direta: `postgres` |
| `DB_PASSWORD` | Senha do banco (Project Settings → Database) |
| `DB_SSLMODE` | `require` |

Valide a conexão (somente leitura: `SELECT version()`, `current_database()`; não cria nem altera nada):

```bash
php artisan db:check-connection
```

O `.env` nunca é versionado. Não coloque senhas ou chaves em arquivos versionados.

## Desenvolvimento

```bash
npm run build                          # compila Tailwind/JS
php artisan serve --no-reload          # servidor local
npm run dev                            # Vite em modo desenvolvimento (outro terminal)
```

No XAMPP (`variables_order = "GPCS"`), `php artisan serve` sem `--no-reload` falhou com "No application encryption key"; com `--no-reload` funciona.

## Estrutura

```
app/Http/Controllers   Controllers
app/Console/Commands   Comandos Artisan (ex.: db:check-connection)
resources/views/layouts       Layouts Blade
resources/views/components    Componentes (layout/, shared/)
resources/views/pages         Páginas
resources/css/app.css         Tailwind CSS 4
resources/js/app.js           Entrada JavaScript (Vite)
docs/                         Documentação técnica
```

## Documentação

- [Supabase Storage (estratégia futura)](docs/supabase-storage.md)
