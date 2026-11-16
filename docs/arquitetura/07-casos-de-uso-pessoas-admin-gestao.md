# 07 — Casos de uso: Pessoas, Administração e Gestão

Tudo 🆕. Convenções do `05`. Leitor = quem toma livros emprestados (`borrowers`). Usuário = conta que opera o sistema (`users`).

## Pessoas

### UC-PES-001 — Cadastrar leitor (aluno/servidor)
- **Ator:** BIB, ADM. **Pré:** `borrower.create`.
- **Entrada:** tipo (aluno/servidor/outro), nome\*, matrícula/funcional, turma, contato (opcional).
- **Principal:** 1. Ator preenche. 2. Sistema valida e verifica matrícula única por tipo (RN-PES-01). 3. Grava com `status=active`. 4. Mostra a ficha com "Registrar empréstimo".
- **Alternativos:** A1 sem matrícula (ex.: visitante) → permitido para `other`; para aluno, a matrícula é obrigatória (configurável). A2 cadastro em lote por arquivo → **fora do escopo inicial**; exige decisão sobre a origem dos dados (ver §10 do plano).
- **Exceções:** E1 matrícula duplicada → UC-PES-005. E2 nome vazio → `[VALIDACAO]`. E3 sem permissão (AUX) → `[SEM_PERMISSAO]`.
- **Banco:** `borrowers`+1, `audit_logs`+1. **Regras:** RN-PES-01, 04.
- **Aceitação:** leitor pesquisável por matrícula e nome; dados mínimos; auditado.
- **Testes:** feliz; validação; unicidade (SQLite+PostgreSQL); autorização por perfil.

### UC-PES-002 — Atualizar dados do leitor
BIB/ADM. Altera nome, turma, contatos; **a matrícula só muda com permissão e auditoria** (afeta identificação). Registra diff em `audit_logs`. Conflito de edição → `[CONFLITO_EDICAO]`. **Testes:** diff, matrícula duplicada ao editar, conflito.

### UC-PES-003 — Pesquisar leitor
AUX/BIB/ADM. Busca por nome (parcial, sem acento quando possível), matrícula, turma; filtra por status. Resultado mostra status e **pendências** (atrasos, empréstimos ativos). Vazio → "Nenhum leitor encontrado" (+ cadastrar, se permitido). Resposta nunca lista dados de contato para quem não tem permissão. **Testes:** busca, filtros, mascaramento por perfil, paginação.

### UC-PES-004 — Identificar leitor por matrícula
No balcão: campo único aceita matrícula (digitada ou lida de **cartão com código de barras**, se a escola adotar). Resolve para **um** leitor ativo; se existir o mesmo número em tipos diferentes (aluno × servidor) pede para escolher o tipo. Inativo → mostra a ficha com o motivo e **bloqueia** empréstimo (UC-CIR-005). **Testes:** ambiguidade por tipo; inativo; matrícula inexistente (UC-CIR-004).

### UC-PES-005 — Matrícula duplicada
Tentativa de cadastrar/alterar para matrícula existente do mesmo tipo → bloqueia e mostra o leitor existente (nome, turma, status) com "Abrir ficha". Se o existente está `inactive/transferred/graduated`, oferece **reativar** em vez de duplicar. **Banco:** `UNIQUE(type, registration_number)` como garantia final (envio duplo). **Testes:** duplicidade; reativação; corrida.

### UC-PES-006 — Aluno inativo ou transferido
- **Ator:** BIB, ADM. **Principal:** altera `status` (`inactive`, `transferred`, `graduated`) com data e motivo; histórico de empréstimos **permanece** e continua consultável por BIB/ADM.
- **Alternativos:** A1 leitor com **empréstimos ativos** → o sistema lista-os e exige: registrar devolução agora, ou "encerrar como perdido" (RN-CIR-16), ou manter a mudança **bloqueada** (configurável). A2 reativação por BIB/ADM com auditoria.
- **Exceções:** inativar quem tem reserva → as reservas são **canceladas** (`cancelled`, motivo automático) e a fila avança.
- **Regras:** RN-PES-02. **Aceitação:** nenhum empréstimo ativo "órfão"; relatórios históricos inalterados. **Testes:** com/sem empréstimo ativo; reservas canceladas; preservação do histórico.

### UC-PES-007 — Anonimizar leitor (LGPD)
ADM, a pedido formal. Substitui nome/contatos por "Leitor anonimizado #id", limpa matrícula, mantém `id`, empréstimos e estatísticas; **irreversível**, auditado (sem guardar o dado anterior no log). Pré: sem empréstimos ativos. **Testes:** relatórios continuam somando; dado pessoal não aparece em lugar nenhum (busca, exportação).

### UC-PES-008 — Criar usuário do sistema
ADM. Entrada: nome, e-mail, perfil (`admin`, `librarian`, `assistant`, `viewer`). Cria com senha temporária/convite (conforme decisão de e-mail), `active=true`, `must_change_password`. Audita. Exceções: e-mail duplicado; e-mail inválido. **Testes:** unicidade, perfil, auditoria.

### UC-PES-009 — Desativar conta de usuário
ADM. `active=false`; sessões ativas revogadas; histórico mantém a autoria. **RN-PES-07:** o **último** admin ativo não pode ser desativado/rebaixado → `[ULTIMO_ADMIN]`. Reativação possível. **Testes:** login negado; sessões invalidadas; último admin.

### UC-PES-010 — Acesso negado (sem permissão)
Tentativa de uma ação/rota fora do perfil → **403** com "Você não tem permissão para esta ação." (web) / `{code:'SEM_PERMISSAO'}` (JSON); a UI **não exibe** botões que o perfil não pode usar, mas a verificação real é sempre no servidor (Policy). Não autenticado → redireciona ao login e retorna à rota pretendida depois. Tentativa de acessar dados administrativos (ex.: `/admin/*`, auditoria, contatos de leitores) por perfil inadequado é **registrada** em `audit_logs` (`access_denied`). **Testes:** matriz de permissões (`08`) por rota e por perfil; sem vazamento de existência do recurso (404 vs 403 coerente).

### UC-PES-011 — Recuperar acesso
Usuário esquece a senha: 1. "Esqueci a senha" pede o e-mail. 2. Resposta **sempre igual** ("Se o e-mail existir, enviaremos instruções") — evita enumerar contas. 3. Token único, expira (ex.: 60 min — valor a confirmar), uso único. 4. Define nova senha; sessões antigas revogadas; audita. **Alternativo:** sem e-mail configurado → ADM redefine e força troca no 1º acesso. **Exceção:** token expirado/usado → mensagem genérica + novo pedido. **Dependência:** provedor de e-mail (decisão). **Testes:** token válido/expirado/reutilizado; resposta uniforme; *rate limit*.

### UC-PES-012 — Entrar e sair (autenticação)
E-mail+senha, sessão do Laravel (cookie `HttpOnly`, `SameSite=Lax`, `Secure` em HTTPS), proteção CSRF, **limite de tentativas** (bloqueio temporário), regeneração de sessão no login, `last_login_at`. Conta inativa → "Acesso desativado. Procure o administrador." Erros de credencial sempre genéricos. **Testes:** sucesso, falha, bloqueio por tentativas, conta inativa, logout invalida sessão.

## Administração

### UC-ADM-001 — Gerenciar disciplinas, categorias e coleções
BIB/ADM. CRUD de `categories` (`type` discipline|theme) e `collections` (nome, editora, código FNDE, programa, ciclo, área, fonte, **situação de verificação**). Regras: nome único (também por `slug`); **em uso → arquivar/mesclar, não excluir** (RN-CAT-13); mesclar move os vínculos. Coleção só ganha situação `official` quando confirmada em fonte oficial (FNDE/Seduc) — o sistema registra a fonte, **não importa em massa**. **Exceções:** nome duplicado (`[CATEGORIA_DUPLICADA]`); exclusão de item em uso (`[EM_USO]`). **Testes:** unicidade/slug, arquivar, mesclar, vínculos preservados.

### UC-ADM-002 — Configurar regras de empréstimo
ADM. Para cada tipo de leitor define: `loan_days`*, `max_active_loans`, `max_renewals`, `renewal_days`, `renew_when_overdue`*, `block_when_overdue`*, `reservation_hold_hours` (\* obrigatórios). **Sem valores padrão sugeridos.** Antes de existir uma regra para o tipo, os empréstimos desse tipo ficam bloqueados (RN-CIR-03) e o painel mostra "Pendência: configurar regras". Alterar vale daqui para frente (RN-ADM-03); mostra "N empréstimos ativos mantêm o prazo atual". Auditado. **Exceções:** valores ≤0 inválidos. **Testes:** bloqueio sem regra; alteração não muda `due_on` existente; auditoria; permissão exclusiva do ADM.

### UC-ADM-003 — Gerenciar usuários e permissões
ADM lista/edita usuários e perfis (UC-PES-008/009). Mudança de perfil gera auditoria (antes/depois) e vale na próxima requisição. Tela mostra a **matriz do perfil** (somente leitura) para transparência. **Exceção:** RN-PES-07. **Testes:** efeito imediato da mudança; auditoria; último admin.

### UC-ADM-004 — Consultar histórico de atividades (auditoria)
ADM (BIB vê somente seus registros e os do catálogo). Filtros: período, usuário, evento, entidade. Lista append-only com diff legível; exportar CSV. Nunca editável. Estado vazio: "Nenhuma atividade no período." **Testes:** filtros; imutabilidade (sem rota de edição/exclusão; `UPDATE` negado no banco); autorização.

### UC-ADM-005 — Identificar operações incompletas e recuperar
Painel de **pendências** (somente leitura + ações guiadas): exemplares sem local; exemplares sem código ativo (inconsistência); empréstimos vencidos há mais de N dias; reservas `ready` expiradas não processadas; livros sem exemplares com reservas; leitores inativos com empréstimo ativo; tentativas de integração falhas. Cada item leva à ação corretiva (ex.: "processar expirações agora"). Como não há estado dessincronizável (disponibilidade derivada), as pendências são de **dados faltantes**, não de corrupção. **Testes:** cada consulta de pendência com dados de exemplo; comando `library:check-integrity` retorna 0 em base consistente.

### UC-ADM-006 — Configurações gerais
ADM: nome da biblioteca, prefixo do código de barras (afeta **novos** códigos), obrigatoriedade do tombo, locais (`locations`), (futuro) calendário. Mudança de prefixo não altera códigos existentes. Auditado. **Testes:** prefixo só em novos exemplares; validação.

## Gestão

Todos somente leitura (RN-GES-01/02): informam **período** e **data de geração**; respeitam o perfil.

| ID | Caso | Ator | Conteúdo | Estados vazios/erros | Testes |
|---|---|---|---|---|---|
| UC-GES-001 | Painel (dashboard) | BIB, ADM (AUX: parcial) | Exemplares total/disponíveis/emprestados/indisponíveis; empréstimos hoje; atrasos; devoluções previstas hoje/semana; reservas prontas; pendências administrativas | "Sem dados ainda" (base vazia); número nunca é adivinhado | Totais conferem com consultas; cache curto opcional |
| UC-GES-002 | Livros mais emprestados | BIB, ADM | Ranking por livro (não por exemplar) no período, filtro por disciplina | Período sem empréstimos | Agrupamento por `book_id`; período |
| UC-GES-003 | Empréstimos em atraso | BIB, ADM, AUX | Leitor, livro, `due_on`, dias de atraso, turma; exportar | "Nenhum atraso" | Derivação de atraso; permissões de dados de leitor |
| UC-GES-004 | Disponibilidade do acervo | BIB, ADM | Por livro/disciplina/local: total, disponíveis, emprestados, manutenção, perdidos, baixados | "Nenhum exemplar registrado" | Contagens por estado derivado |
| UC-GES-005 | Movimentações | BIB, ADM | Linha do tempo de `copy_events`/empréstimos por período, usuário, tipo | Período vazio | Filtros; ordenação |
| UC-GES-006 | Exportar (CSV) | BIB, ADM | Relatórios acima; **sem** contatos de leitores por padrão | Falha de geração → erro genérico | Colunas; escape de células (injeção de fórmula) |
| UC-GES-007 | Leitores por turma/uso | BIB, ADM | Empréstimos por turma; leitores sem empréstimos | — | Agregação; anonimizados contam sem identificar |

Desempenho: relatórios usam consultas indexadas (`loans(due_on)`, `loans(borrower_id)`, `copy_events(copy_id, created_at)`);
cache só quando medido necessário.
