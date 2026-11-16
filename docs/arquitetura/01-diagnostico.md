# 01 — Diagnóstico da arquitetura atual

Baseado na auditoria do repositório em 2026-11-16 (35 commits, branch `main`, árvore limpa).

## 1. Inventário

| Camada | Existente |
|---|---|
| **Models** (5) | `Book`, `Category`, `BookCollection` (tabela `collections`), `BookSource`, `User` (padrão do Laravel, sem uso) |
| **Controllers** (4) | `BookController` (index/create/store/show/edit/update), `IsbnLookupController`, `ScannerController`, `Controller` |
| **Form Requests** (4) | `BookRequest` (abstrata), `StoreBookRequest`, `UpdateBookRequest`, `LookupIsbnRequest` |
| **Services** (7) | `IsbnValidatorService`, `GoogleBooksService`, `OpenLibraryService`, `BookLookupService`, `MetadataComparisonService`, `BookCatalogService`, trait `SanitizesExternalData` |
| **DTOs / Enums / Policies / Resources / Events / Jobs** | **nenhum** |
| **Exceptions** | `ExternalLookupException` |
| **Comandos** | `db:check-connection` |
| **Migrations de domínio** (6) | `categories`, `collections`, `books`, `book_sources`, `book_category`, RLS |
| **Rotas** (8) | `/catalogo`, `/catalogo/novo`, `/catalogo/scanner`, `/catalogo/{book}`, `/catalogo/{book}/editar`, `POST /catalogo`, `PUT /catalogo/{book}`, `POST /catalogo/isbn` |
| **Views** | `layouts/app`, `catalog/{index,create,edit,show,scanner}`, `catalog/partials/{form,lookup-result}`, componentes `layout/{container,header}` e `shared/{book-cover,flash,input}` |
| **JS** | `scanner.js` (ZXing, EAN-13) |
| **Testes** | 96 (SQLite e PostgreSQL 17): ISBN 13, serviços de API 20, catálogo 41, integridade/PostgreSQL 10, robustez de dados externos 10 |
| **Banco** | Supabase **não conectado** (credenciais ausentes). Validado em SQLite e PostgreSQL local |

## 2. Relacionamentos atuais

```
Book ──< book_category >── Category
Book >── BookCollection (collection_id, nullOnDelete)
Book ──< BookSource (cascade; unique book_id+provider)
```

- `Book` representa **uma edição bibliográfica** (o ISBN a identifica). Não há distinção obra × edição, e isso é suficiente.
- Autores = coluna `text` ("A; B"). Editora = coluna `string`.
- Não existe nenhuma entidade de exemplar, pessoa, empréstimo, local ou auditoria.

## 3. O que está certo e deve ser preservado

1. Separação **Livro (bibliográfico) × Exemplar (físico)**: nada no código atual mistura os dois.
2. `IsbnValidatorService`: regras de ISBN isoladas, testadas e independentes de framework.
3. Integrações externas atrás de serviços, com timeout, erros tipados e dados sanitizados.
4. Unicidade de ISBN garantida **pelo banco** (e não só pela aplicação), validada no PostgreSQL.
5. Dados externos só são gravados após confirmação humana; procedência em `book_sources`.
6. Testes que rodam no SQLite **e** no PostgreSQL (`phpunit.pgsql.xml`).

## 4. Problemas encontrados

| # | Achado | Gravidade | Ação |
|---|---|---|---|
| P1 | **Sem autenticação nem autorização** (`authorize()` retorna `true`) | Bloqueante p/ publicar | Fase 0 |
| P2 | Regra "ISBN já existe" em **dois lugares**: `BookLookupService::lookup` e `BookCatalogService::findDuplicate` | Média (divergência futura) | Unificar em `Book::scopeWithIsbn` + um único serviço |
| P3 | `IsbnLookupController` monta dados de apresentação e renderiza HTML (lógica de view no controller) | Média | Mover para um *view model*/Resource |
| P4 | `BookCatalogService::syncRelations` cria coleção **por nome digitado** (um erro de digitação cria coleção nova) | Média | Selecionar coleção existente; criar só em tela de administração |
| P5 | Estados como constantes string (`BookSource::STATUS_*`, `PROVIDER_*`) | Baixa | Enums PHP |
| P6 | Sem histórico de alterações de metadados (edição sobrescreve) | Média | `audit_logs` + diff (UC-CAT-005) |
| P7 | `categories` mistura disciplinas e temas sem distinção | Média | Coluna `type` |
| P8 | Autores em texto livre (sem busca/relatório por autor confiável) | Baixa agora | Decisão no §03 (manter) |
| P9 | Sem arquivamento de livro (não há como "retirar" um registro sem excluir) | Média | `archived_at` |
| P10 | `session`/`cache`/`queue` em `file`/`sync`: o token de consulta de ISBN e o *rate limit* não funcionam com várias instâncias; reservas que expiram exigem *scheduler*/fila | Média | Definir infraestrutura de hospedagem (decisão) |
| P11 | Fuso UTC. Prazos de devolução dependem do **dia local** (Ceará, UTC-3) | Média | `due_on` do tipo *date* + fuso de exibição |
| P12 | Busca não ignora acentos; ordenação depende do *collation* do banco | Baixa | Avaliar `unaccent` no PostgreSQL |
| P13 | Estrutura plana (`Services/`, `Http/Controllers/`) só comporta o catálogo | Baixa agora | Subpastas por módulo (§02) |
| P14 | **Sem testes de autorização e de concorrência** (inexistentes porque as funções não existem) | Alta p/ circulação | Plano no §10 |
| P15 | Supabase não validado; migration de RLS pronta e **não aplicada** | Alta p/ produção | Pendência de credenciais |
| P16 | `Book` tem `likeOperator()`/`scopeSearch` com SQL específico de driver | Baixa | Aceitável; manter coberto por teste em ambos os bancos |

## 5. Dependências entre módulos (hoje)

```
Catálogo  → (nenhum outro módulo)
```

O catálogo é autocontido. Todo o restante (acervo, circulação, pessoas, gestão) é novo e depende dele, de autenticação e de configuração (veja §02).

## 6. Cobertura de testes existente

| Área | Testes | Lacuna |
|---|---|---|
| ISBN | 13 | — |
| Google/Open Library | 20 (mocks + falhas reais injetadas na validação anterior) | Google real nunca respondeu (429 sem chave) |
| Cadastro/edição/duplicidade/busca | 41 | Sem autorização; sem auditoria |
| Integridade no banco | 10 | Só tabelas do catálogo |
| Scanner (navegador) | 20 verificações em Chrome com câmera virtual (fora do `php artisan test`) | Sem aparelho físico |
| Concorrência | 0 | Necessária para empréstimos |
