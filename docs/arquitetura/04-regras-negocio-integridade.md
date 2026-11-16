# 04 — Regras de negócio, integridade e concorrência

As regras têm identificadores estáveis (`RN-…`) referenciados pelos casos de uso, pelos testes e pelas exceções de
domínio. **Nenhum prazo, limite, multa ou política escolar é inventado**: onde a escola decide, a regra diz
"configurável" e o sistema bloqueia a operação até que alguém configure.

## 1. Regras por módulo

### Catálogo (CAT)
| ID | Regra |
|---|---|
| RN-CAT-01 | Um ISBN só é aceito se tiver 10 ou 13 dígitos válidos (prefixo 978/979 + dígito verificador). Um EAN-13 sem esses prefixos **não** é ISBN. |
| RN-CAT-02 | `isbn13` e `isbn10`, quando informados, são **únicos** no catálogo (garantido pelo banco). |
| RN-CAT-03 | ISBN-10 é convertido para ISBN-13 e ambos são gravados. ISBN-13 com prefixo 979 não tem ISBN-10. |
| RN-CAT-04 | Livro sem ISBN exige **título**. Possível duplicidade (título normalizado + autores + editora + ano) gera **aviso**, nunca bloqueio. |
| RN-CAT-05 | Nenhum metadado é inventado: campo ausente na fonte permanece vazio. |
| RN-CAT-06 | Dados vindos de fontes externas só são gravados **após confirmação humana**. |
| RN-CAT-07 | Cada fonte é registrada em `book_sources` (provedor, id externo, URL, data, situação de verificação). |
| RN-CAT-08 | Alterações de metadados geram `audit_logs` com campo, valor anterior e novo, usuário e data. |
| RN-CAT-09 | Livro com exemplares nunca é excluído; é **arquivado**. Livro sem exemplares/reservas pode ser excluído só por Administrador. |
| RN-CAT-10 | Alterar o **ISBN** de um livro que já tem exemplares exige confirmação explícita e auditoria. |
| RN-CAT-11 | Atualizar um livro existente com dados novos **nunca sobrescreve em silêncio**: mostra diferenças; por padrão só preenche campos vazios; substituir um campo exige marcá-lo. |
| RN-CAT-12 | Mesclar duplicados move exemplares, reservas, categorias e fontes para o registro mantido e arquiva o outro (Administrador, auditado). |
| RN-CAT-13 | Categoria/disciplina em uso não é excluída: é arquivada ou mesclada. |

### Scanner (SCN)
| ID | Regra |
|---|---|
| RN-SCN-01 | **Falha de leitura ≠ livro inexistente.** Só se consulta o catálogo/fontes depois de um ISBN **válido** ter sido obtido. |
| RN-SCN-02 | Ao detectar um código válido a câmera é desligada; uma sessão de câmera produz **no máximo uma** consulta. |
| RN-SCN-03 | Código EAN-13 que não é ISBN é avisado e ignorado, sem consulta externa. |
| RN-SCN-04 | A digitação manual do ISBN e o cadastro manual estão **sempre** disponíveis. |
| RN-SCN-05 | A consulta externa é limitada por IP/usuário (proteção da cota). |

### Acervo (ACE)
| ID | Regra |
|---|---|
| RN-ACE-01 | Todo exemplar pertence a **exatamente um** livro existente (`RESTRICT`). Não se registra exemplar de livro inexistente. |
| RN-ACE-02 | O código de barras de exemplar é **único globalmente**, inclusive entre códigos antigos (`copy_barcodes.code`). |
| RN-ACE-03 | Tombo (`accession_number`) é único quando informado; sua obrigatoriedade é configurável. |
| RN-ACE-04 | O código é gerado pelo sistema e imutável. Reetiquetar **cria** novo código ativo e mantém o antigo resolvendo para o mesmo exemplar, com aviso. |
| RN-ACE-05 | Exemplares nunca são excluídos; saem de circulação com `written_off` + motivo. |
| RN-ACE-06 | Transições permitidas: `active⇄maintenance`, `active→lost`, `lost→active`, `active|maintenance|lost→written_off`. `written_off` é terminal (restauração só por Administrador, com motivo). |
| RN-ACE-07 | **Disponível** (derivado) = `status=active` ∧ sem empréstimo ativo ∧ não retido para reserva de outra pessoa. |
| RN-ACE-08 | Toda mudança de estado, local, condição ou etiqueta gera um `copy_events`. |
| RN-ACE-09 | Registrar N exemplares cria N registros individuais, com códigos próprios, numa **única transação** (tudo ou nada). |
| RN-ACE-10 | Marcar `lost`/`written_off` com empréstimo ativo encerra o empréstimo com `outcome=lost` (RN-CIR-16). `maintenance` exige que o exemplar não esteja emprestado. |

### Circulação (CIR)
| ID | Regra |
|---|---|
| RN-CIR-01 | Só um exemplar **disponível** (RN-ACE-07) pode ser emprestado. Garantido por *lock* de linha + índice único parcial. |
| RN-CIR-02 | O leitor precisa estar `active`. `inactive`, `transferred` e `graduated` não emprestam. |
| RN-CIR-03 | As regras de empréstimo vêm de `loan_rules` do **tipo do leitor**. Sem regra configurada, o empréstimo é bloqueado. |
| RN-CIR-04 | `max_active_loans` (se definido) limita empréstimos ativos simultâneos do leitor. |
| RN-CIR-05 | `block_when_overdue` (configurável): leitor com atraso não faz novo empréstimo. |
| RN-CIR-06 | `due_on` = data local do empréstimo + `loan_days`. Eventual calendário de dias não letivos é decisão pendente. |
| RN-CIR-07 | Renovação exige: empréstimo ativo; renovações < `max_renewals`; não vencido (salvo `renew_when_overdue`); **nenhuma reserva em espera** para o livro. |
| RN-CIR-08 | A devolução é identificada pelo **exemplar**. Exemplar sem empréstimo ativo gera aviso, não erro fatal. |
| RN-CIR-09 | Reserva é por **livro** e só é aceita quando **não há exemplar disponível** (a fila é FIFO por `requested_at`). |
| RN-CIR-10 | Ao devolver um livro com reservas em espera, o exemplar fica **retido** para o 1º da fila (`ready`, `expires_at` = `reservation_hold_hours`). Retenção vencida → `expired` e passa ao próximo. |
| RN-CIR-11 | Um leitor tem no máximo **uma** reserva ativa por livro e não reserva livro que já tem emprestado. |
| RN-CIR-12 | Atraso é **derivado** (`due_on < hoje` ∧ sem devolução), nunca gravado. |
| RN-CIR-13 | Multas **não existem** nesta arquitetura; só entram após decisão da escola. |
| RN-CIR-14 | O histórico de empréstimo é imutável. Correção = **estorno** (`voided`) por Administrador, com motivo e auditoria. |
| RN-CIR-15 | Criar empréstimo, registrar o evento do exemplar e auditar ocorrem na **mesma transação**. Como disponibilidade é derivada, não há estado a dessincronizar. |
| RN-CIR-16 | Exemplar perdido com empréstimo ativo: o empréstimo é encerrado com `outcome=lost` (decisão de cobrança, se houver, é externa ao sistema). |

### Pessoas (PES)
| ID | Regra |
|---|---|
| RN-PES-01 | Matrícula é única por tipo de leitor. |
| RN-PES-02 | Leitor nunca é excluído. Inativar/transferir/formar mantém o histórico. Com empréstimo ativo, a mudança exige tratar o empréstimo (aviso/bloqueio configurável). |
| RN-PES-03 | Anonimização (LGPD) troca nome/contato por marcador e preserva `id`, empréstimos e estatísticas. |
| RN-PES-04 | Coletar o mínimo (nome, matrícula, turma). Contatos de menores são opcionais e visíveis apenas a perfis autorizados. |
| RN-PES-05 | Conta de usuário ≠ leitor; vínculo é opcional. |
| RN-PES-06 | Conta desativada não autentica, mas continua como autora do histórico. |
| RN-PES-07 | O **último** Administrador ativo não pode ser desativado nem rebaixado. |
| RN-PES-08 | Recuperação de acesso por e-mail (requer provedor de e-mail) ou redefinição por Administrador; *tokens* expiram e são de uso único. |

### Administração (ADM) e Gestão (GES)
| ID | Regra |
|---|---|
| RN-ADM-01 | Alterar configurações, regras de empréstimo, perfis e permissões gera `audit_logs`. |
| RN-ADM-02 | `audit_logs` é *append-only*. |
| RN-ADM-03 | Alterar `loan_rules` vale **daqui para frente**; empréstimos existentes mantêm o `due_on` gravado. |
| RN-GES-01 | Relatórios são somente leitura e respeitam o perfil (dados de leitores restritos). |
| RN-GES-02 | Indicadores informam o **período** e a **data de geração**; números derivam de consultas, não de contadores gravados. |

## 2. Transições de estado

**Exemplar** (administrativo): ver RN-ACE-06. **Disponibilidade** é derivada (RN-ACE-07).

**Reserva:** `waiting → ready → fulfilled` · `waiting|ready → cancelled` · `ready → expired` (e o próximo da fila vira `ready`).

**Empréstimo:** ativo (`returned_at NULL`) → `returned` | `lost` | `voided`. Estados finais são imutáveis.

**Leitor:** `active ⇄ inactive` · `active → transferred|graduated` · reativação por perfil autorizado, com auditoria.

## 3. Transações e concorrência

### 3.1 Operações que exigem transação (`DB::transaction`)
Emprestar · Devolver (inclui reter exemplar para a fila) · Renovar · Reservar/cancelar/expirar · Registrar N exemplares ·
Reetiquetar · Mudar estado do exemplar (com encerramento de empréstimo) · Estornar empréstimo · Mesclar livros ·
Alterar ISBN com exemplares · Qualquer operação que grave histórico + estado.

### 3.2 Estratégia contra empréstimos simultâneos (verificada)
Em dupla proteção:
1. **Lock de linha** do exemplar (`SELECT … FOR UPDATE` → `lockForUpdate()`) serializa quem disputa a mesma cópia.
2. **Índice único parcial** `loans(copy_id) WHERE returned_at IS NULL` é a barreira final, no banco.

Evidência (PostgreSQL 17.5, duas sessões `psql` simultâneas tentando emprestar a mesma cópia):

| Cenário | Resultado |
|---|---|
| 2º `INSERT` de empréstimo ativo na mesma cópia | `ERROR: duplicate key value violates unique constraint "loans_one_active_per_copy"` |
| Devolver e emprestar de novo | permitido; histórico mantido (2 linhas) |
| **Com** lock + índice, 2 sessões simultâneas | A: `INSERT 0 1` · B: `INSERT 0 0` · ativos = **1** |
| **Sem** lock e **sem** índice, 2 sessões simultâneas | ativos = **2** (corrida real) |

O índice parcial também funciona no SQLite (testes). **Limitação:** o SQLite não reproduz concorrência real entre
processos; os testes de corrida devem rodar no PostgreSQL (`phpunit.pgsql.xml`).

### 3.3 Outros pontos
- **Reserva "pronta"**: ao devolver, reserva e retenção ocorrem na mesma transação da devolução.
- **Edições concorrentes de metadados**: *optimistic locking* por `updated_at` no formulário de edição (conflito → "o registro foi alterado por outra pessoa; revise").
- **Idempotência**: envio duplo de formulário já é tratado (violação única → fluxo de duplicidade); o mesmo padrão vale para códigos de exemplar e empréstimos.
- **Banco indisponível**: a transação falha → nenhuma gravação → mensagem "não foi possível salvar" (nunca "salvo").

## 4. Integridade por restrição (resumo)

| Garantia | Mecanismo |
|---|---|
| ISBN único | `UNIQUE(isbn13)`, `UNIQUE(isbn10)` |
| Exemplar sempre ligado a um livro | `book_copies.book_id NOT NULL`, `RESTRICT` |
| Código de exemplar único | `UNIQUE(copy_barcodes.code)` |
| 1 código ativo por exemplar | índice único parcial `(copy_id) WHERE active` |
| 1 empréstimo ativo por exemplar | índice único parcial (acima) |
| 1 reserva ativa por leitor/livro | índice único parcial |
| Matrícula única | `UNIQUE(type, registration_number)` |
| Histórico não apagável | FKs `RESTRICT` + revogar `DELETE` nas tabelas *append-only* |
| Estados válidos | Enums PHP + `CHECK` no banco (PostgreSQL) |
| Datas coerentes | `CHECK(returned_at IS NULL OR returned_at >= loaned_at)` |
