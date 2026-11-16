# Relatório de validação do catálogo bibliográfico

Data: 2026-11-16. Escopo: catálogo (sem exemplares, empréstimos ou alunos).

## 1. O que foi validado, e onde

| Item | SQLite | PostgreSQL 17 (local) | Supabase | API real | Câmera virtual | Dispositivo físico |
|---|---|---|---|---|---|---|
| Suíte automatizada (95 testes) | ✅ | ✅ | ❌ não validado | — | — | — |
| Migrations | ✅ | ✅ (SQL revisado e executado) | ❌ **não executadas** | — | — | — |
| Unicidade de ISBN / cascatas / JSON | ✅ | ✅ | ❌ | — | — | — |
| RLS (migration de proteção) | n/a | ✅ comprovado com papel não-dono | ❌ | — | — | — |
| Open Library | — | — | — | ✅ | — | — |
| Google Books | — | — | — | ❌ HTTP 429 sem chave | — | — |
| Falhas HTTP (500/429/timeout/JSON inválido/recusa) | — | — | — | ✅ servidor de falhas real | — | — |
| Scanner ZXing (EAN-13, duplicidade, encerramento) | — | — | — | — | ✅ Chrome | ❌ **não testado** |
| Interface em 3 tamanhos | — | — | — | — | ✅ Chrome | ❌ |

**Supabase:** `DB_HOST`, `DB_USERNAME` e `DB_PASSWORD` seguem vazios; `php artisan db:check-connection` retorna
"NÃO VALIDADO". Nada foi executado no banco remoto.

## 2. Diferenças SQLite × PostgreSQL

| Tema | SQLite | PostgreSQL | Tratamento |
|---|---|---|---|
| `LIKE` | case-insensitive só ASCII | case-sensitive | `ilike` no PostgreSQL (`Book::likeOperator`) |
| Acentos na busca | não ignora | não ignora (sem `unaccent`) | limitação documentada |
| Inteiros | sem limite real | `smallint` (≤ 32767) em ano e páginas | validação limita páginas a 20000 |
| `json` | texto | tipo `json` | `raw_metadata` testado com Unicode |
| `UNIQUE` com `NULL` | vários NULL | vários NULL | livros sem ISBN coexistem (testado) |
| Erro de unicidade | `UniqueConstraintViolationException` | idem (SQLSTATE 23505) | tratado no cadastro (envio duplo) |
| Ordenação (`orderBy title`) | binária | depende do *collation* do banco | revisar no Supabase |
| `migrate` com banco inexistente | — | **o Laravel cria o banco** em modo não interativo | atenção ao conferir `DB_DATABASE` |
| Execução dos testes | `phpunit.xml` | `phpunit.pgsql.xml` (banco **descartável**) | ver seção 6 |

## 3. Segurança

| Tema | Situação |
|---|---|
| Validação no backend | Form Requests com limites de tamanho e tipo; ISBN validado; filtros validados. |
| CSRF | Middleware padrão do Laravel ativo; o scanner envia `X-CSRF-TOKEN`. |
| XSS | Blade escapa tudo; testado com `<script>` em título e autores. |
| URLs de capa | Gravação aceita só `http`/`https`. Dados vindos das APIs descartam `javascript:`, `data:` etc. |
| Hotlink de capas | Imagens carregam direto do Google/Open Library (expõem o IP do usuário a esses serviços). `referrerpolicy=no-referrer` aplicado. Futuro: copiar para o Storage. |
| Chave `GOOGLE_BOOKS_API_KEY` | Só no `.env`; enviada apenas quando configurada; mensagens de erro não a incluem. |
| Cota das APIs | Rota de consulta limitada a 30 req/min por IP. |
| Logs | Exceções de rede não encadeiam a original (continha URL e chave). |
| **Sem autenticação** | ⚠️ Qualquer pessoa com acesso à URL pode cadastrar, editar e consultar. **Não publicar sem login.** |
| **Supabase Data API** | ⚠️ Tabelas no schema `public` ficam expostas pela chave publicável se não houver RLS. A migration `enable_row_level_security` resolve (testada no PostgreSQL local). Alternativa: desativar a Data API do projeto. |
| `APP_DEBUG=true` | ⚠️ Deve ser `false` em produção (vaza detalhes). |
| Controle de acesso | Inexistente (`authorize()` retorna `true`). Implementar com a autenticação. |

### Antes de disponibilizar publicamente
1. Autenticação (login) e perfis (ex.: bibliotecário × leitor), com `authorize()` real.
2. Aplicar a migration de RLS (ou desativar a Data API) e conferir em *Advisors → Security* do Supabase.
3. `APP_DEBUG=false`, `APP_ENV=production`, HTTPS obrigatório e cabeçalhos de segurança (CSP).
4. Configurar `GOOGLE_BOOKS_API_KEY` (restrita à Books API).
5. Rate limit também em cadastro/edição e proteção contra bots.

## 4. Integrações externas

- **Cache:** não implementado. Cada leitura faz 2 chamadas. Recomendado cache curto por ISBN (ex.: 10 min)
  *se* o volume crescer ou o Google exigir; o limite de 30/min já protege a cota.
- **Retentativas:** não implementadas de propósito: uma falha vira "indisponível", com cadastro manual como
  saída. Retentativas automáticas agravariam o HTTP 429.
- **Qualidade dos dados:** o Open Library devolveu um título sem relação para o ISBN fictício 9789999999991.
  Por isso o usuário sempre confirma os dados antes de salvar.
- **Procedência:** cada fonte é gravada em `book_sources` com `retrieved_at`, `raw_metadata` e situação
  (`unverified` → `confirmed` quando o usuário salva).

## 5. Roteiro manual da câmera (dispositivo físico)

Pré-requisito: abrir o sistema por **HTTPS** (ou `localhost`). Em celular na rede local, use um túnel HTTPS
(ex.: ngrok) — `http://192.168.x.x` **não** libera a câmera. Tenha 3 livros: um com ISBN-13 (978/979), um
antigo só com ISBN-10 e um objeto com código de barras comum (produto de supermercado).

### Android (Chrome)
1. Abrir `/catalogo/scanner`. **Esperado:** "Câmera desligada", câmera não ativa (sem ícone de câmera na barra).
2. Tocar **Iniciar câmera**. **Esperado:** pedido de permissão; após permitir, imagem em tempo real e a
   mensagem "Lendo código…".
3. Verificar a **câmera traseira** no início. Se abrir a frontal, anotar o modelo do aparelho.
4. Aproximar o código do livro (15–25 cm) dentro da moldura. **Esperado:** "ISBN identificado", câmera
   **desliga sozinha**, "Pesquisando…" e o formulário de confirmação.
5. Conferir que apareceu **uma** consulta só e que o ícone de câmera do navegador sumiu.
6. Código de produto comum. **Esperado:** aviso "não é um ISBN" e a leitura continua.
7. Livro só com ISBN-10: ler o EAN-13 do verso (se houver) ou digitar o ISBN-10 manualmente.
8. Trocar de câmera no seletor (se houver mais de uma). **Esperado:** reinicia na escolhida.
9. Com a câmera ligada, **trocar de app/aba**. **Esperado:** câmera desliga e aparece o aviso.
10. Com a câmera ligada, tocar em "Catálogo". **Esperado:** ícone de câmera some.
11. Negar a permissão (Configurações do site → Câmera → Bloquear) e tentar de novo. **Esperado:** mensagem
    clara e campo de ISBN manual usável.
12. Testar em pouca luz, com reflexo (capa plastificada) e com o código inclinado. Anotar o que falha.
13. Salvar um livro e conferir no catálogo; tentar ler o **mesmo** livro: deve avisar que já existe.

### Computador (Chrome/Edge/Firefox)
1. Repetir 1–2 e 4–6 usando a webcam; segurar o livro a ~20 cm.
2. Com duas webcams, escolher no seletor.
3. Fechar a aba com a câmera ligada: a luz da webcam deve apagar.
4. Em um segundo navegador, bloquear a câmera e confirmar a mensagem.
5. Confirmar que, sem câmera/permissão, o cadastro manual e o ISBN digitado funcionam.

Registrar para cada item: aparelho, navegador/versão, resultado e observações.

## 6. Como rodar a suíte no PostgreSQL

Use um banco **descartável** (o `RefreshDatabase` apaga as tabelas):

```bash
DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5432 DB_DATABASE=biblioteca_test \
DB_USERNAME=postgres DB_PASSWORD=... vendor/bin/phpunit -c phpunit.pgsql.xml
```

Nunca aponte para o banco do Supabase de produção.
