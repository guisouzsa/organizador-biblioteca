# 06 — Casos de uso: Acervo físico e Circulação

Tudo aqui é 🆕 (nada implementado). Convenções iguais às do `05`. Permissões e códigos de erro: `08`.
O **exemplar** é identificado por um **código de barras interno** (Code 128, ex.: `EX-000123`), gerado pelo sistema.
O **ISBN identifica o livro (edição)**, nunca o exemplar.

## Acervo

### UC-ACE-001 — Registrar exemplar de um livro existente
- **Objetivo:** criar uma ou mais cópias físicas de um livro já catalogado. **Ator:** BIB, ADM. **Pré:** `copy.create`; livro existe e não arquivado.
- **Entrada:** livro (por ISBN lido/digitado ou busca), quantidade (≥1), local, condição, data/origem de aquisição, tombo (se aplicável).
- **Principal:** 1. Ator abre o livro e escolhe "Registrar exemplares" (ou escaneia o ISBN e escolhe a ação). 2. Informa quantidade/local/condição. 3. Sistema valida. 4. Em **uma transação**: cria N `book_copies`; gera para cada uma o `copy_barcodes` ativo; registra `copy_events(registered)`; audita. 5. Mostra a lista de códigos criados com **"Imprimir etiquetas"**.
- **Alternativos:** A1 tombo informado → validado como único. A2 tombo obrigatório (configuração) e ausente → erro. A3 criar com local vazio → permitido (aparece em "Sem localização" para pendência).
- **Exceções:** E1 livro arquivado → `[LIVRO_ARQUIVADO]`. E2 falha ao gravar **qualquer** cópia → rollback total: "Nenhum exemplar foi registrado." E3 tombo repetido → `[EXEMPLAR_DUPLICADO]`.
- **Banco:** `book_copies`+N, `copy_barcodes`+N, `copy_events`+N, `audit_logs`+1.
- **Regras:** RN-ACE-01, 02, 03, 04, 08, 09.
- **Mensagens:** "3 exemplares registrados: EX-000101 a EX-000103." · "Nenhum exemplar foi registrado."
- **Aceitação:** N cópias com códigos distintos e únicos; todas ligadas ao mesmo livro; tudo ou nada.
- **Testes:** Feature (N cópias, códigos únicos); rollback em falha na 3ª cópia; tombo duplicado; autorização por perfil; PostgreSQL (unicidade).

### UC-ACE-002 — Registrar exemplar de um livro inexistente
- **Ator:** BIB. **Situação:** a pessoa tem o livro físico, mas ele não está no catálogo.
- **Principal:** 1. Ator escaneia/digita o ISBN na tela de exemplares. 2. Sistema não acha o livro. 3. Oferece **"Identificar e cadastrar o livro agora"** (UC-CAT-001/002/004) em fluxo contínuo. 4. Ao salvar o livro, retorna automaticamente ao registro do exemplar com o livro selecionado. 5. Conclui UC-ACE-001.
- **Alternativos:** A1 livro sem ISBN → UC-CAT-004 e volta. A2 ator cancela o cadastro do livro → nenhum exemplar é criado.
- **Exceções:** E1 o exemplar **não pode** ser salvo sem livro (`book_id NOT NULL`, RN-ACE-01). E2 falha no cadastro do livro → não segue para o exemplar.
- **Banco:** `books`+1 (se cadastrado), depois como UC-ACE-001; **duas transações distintas** (livro e exemplares), a segunda só inicia após a primeira confirmada.
- **Mensagem:** "Este livro ainda não está no catálogo. Cadastre-o para registrar o exemplar."
- **Aceitação:** impossível criar exemplar órfão; o ator não perde o contexto entre as duas etapas.
- **Testes:** Feature (redirecionamento com retorno); tentativa de criar exemplar com `book_id` inexistente → erro; E2E scanner.

### UC-ACE-003 — Código de barras do exemplar não funciona
- **Ator:** AUX, BIB. **Situações e tratamento:**

| Situação | Comportamento |
|---|---|
| Etiqueta danificada/ilegível | Buscar o exemplar **manualmente** (por tombo, por livro/local, ou digitando o código) e abrir a ficha |
| Leitor não reconhece o código | Tentar câmera/outro leitor; fallback: digitar o código impresso abaixo das barras |
| Código **não existe** no banco | `[EXEMPLAR_NAO_ENCONTRADO]`: "Código não encontrado. Confira o número ou busque o livro." + busca por livro/tombo; **nunca** cria exemplar automaticamente |
| Código pertence a **outro** exemplar | Mostra o exemplar correspondente (livro, local, situação) para o ator conferir se leu a etiqueta certa |
| Código digitado errado | Sugere correção (dígitos próximos) apenas como busca; nada é assumido |
| Código antigo (etiqueta substituída) | Resolve para o exemplar com aviso: "Etiqueta antiga — use a etiqueta atual EX-…" |

- **Reetiquetar:** na ficha do exemplar, "Gerar nova etiqueta": 1. Escolhe o motivo. 2. Transação: marca o código atual `active=false` (`replaced_at/reason`), cria novo código ativo, `copy_events(relabeled)`, audita. 3. Imprime. **O histórico e os empréstimos do exemplar permanecem.**
- **Exceções:** E1 impressora indisponível → a etiqueta é gerada e pode ser reimpressa depois. E2 falha na transação → o código antigo continua ativo.
- **Regras:** RN-ACE-02, 04, 08. **Aceitação:** após reetiquetar, ambos os códigos resolvem para o mesmo exemplar; só um está ativo; histórico intacto.
- **Testes:** Feature (busca manual, código antigo com aviso); reetiquetar mantém empréstimos; unicidade global entre códigos; rollback.

### UC-ACE-004 — Exemplar já cadastrado (duplicidade)
- **Situação:** tentativa de registrar um código/tombo que já existe (digitado, importado ou colisão).
- **Comportamento:** bloqueia; exibe o registro correspondente com link: "O tombo 1234 já pertence ao exemplar EX-000045 (Filosofia: volume único)." Oferece abrir a ficha. Nunca sobrescreve.
- **Exceções:** colisão concorrente → violação única tratada como duplicidade (mesmo padrão do catálogo).
- **Regras:** RN-ACE-02/03. **Testes:** Feature + concorrência no PostgreSQL.

### UC-ACE-005 — Livro com várias cópias
- **Objetivo:** gerir várias cópias do mesmo livro com dados próprios.
- **Principal:** na ficha do livro, a aba **Exemplares** lista cada cópia: código, tombo, local, condição, situação (derivada: disponível/emprestado/retido/manutenção/perdido/baixado) e, se emprestado, até quando (visível conforme perfil). Cada cópia pode ter local, condição, notas e aquisição **diferentes**.
- **Alternativos:** A1 filtrar por situação. A2 registrar mais cópias (UC-ACE-001). A3 contadores no catálogo: total / disponíveis.
- **Aceitação:** contadores = consultas (RN-GES-02); identificadores individuais.
- **Testes:** Feature (contagem derivada), N+1 evitado (eager loading).

### UC-ACE-006 — Exemplar perdido, danificado, em manutenção ou baixado
- **Ator:** BIB (ADM para restaurar `written_off`). **Pré:** exemplar existente.
- **Principal:** 1. Ator escolhe a ação: **Condição** (atualiza `condition`), **Manutenção**, **Perdido**, **Baixar**. 2. Informa motivo (obrigatório em perdido/baixa). 3. Sistema valida a transição (RN-ACE-06). 4. Transação: atualiza, `copy_events`, audita; se houver empréstimo ativo e a ação for perdido/baixa → encerra o empréstimo com `outcome=lost` (RN-ACE-10).
- **Alternativos:** A1 "Reencontrado": `lost→active` (`found`). A2 "Restaurar baixa" (ADM): `written_off→active` com motivo. A3 danificado leve: só muda `condition`, continua circulável.
- **Exceções:** E1 transição inválida → `[TRANSICAO_INVALIDA]`. E2 enviar para manutenção com empréstimo ativo → bloqueado: "Registre a devolução antes." E3 motivo ausente → `[VALIDACAO]`.
- **Banco:** `book_copies`, `copy_events`, `loans` (se encerrado), `audit_logs`.
- **Aceitação:** exemplar `lost/written_off/maintenance` **não** pode ser emprestado; histórico completo preservado e consultável.
- **Testes:** matriz de transições (válidas/ inválidas); encerramento de empréstimo; histórico; autorização.

### UC-ACE-007 — Mudar localização
Ator BIB/AUX. Troca `location_id`; grava `copy_events(location_changed)` com local anterior/novo. Em lote: selecionar várias cópias e um local (transação única). Local desativado não aceita novos exemplares. **Testes:** evento com antes/depois; lote tudo-ou-nada.

### UC-ACE-008 — Consultar exemplar e histórico
Ator: BIB, AUX, ADM (CON vê só situação). Entrada: código (câmera/digitação/busca). Saída: livro, local, condição, situação derivada, empréstimo atual (se houver) e **linha do tempo** (`copy_events`). Estados vazios/erros: `[EXEMPLAR_NAO_ENCONTRADO]` (UC-ACE-003). **Testes:** ordenação cronológica; códigos antigos; autorização de campos.

## Circulação

Pré-condição global: **regras de empréstimo configuradas** para o tipo do leitor (RN-CIR-03). Sem isso, as telas
mostram "Configure as regras de empréstimo em Administração → Regras" (UC-ADM-002).

### UC-CIR-001 — Realizar empréstimo
- **Ator:** AUX, BIB, ADM. **Pré:** `loan.create`; regras configuradas.
- **Entrada:** código do exemplar (câmera/digitação) e leitor (matrícula/nome).
- **Principal:** 1. Ator identifica o **leitor** (UC-PES-004) — o cartão do leitor mostra nome, turma e pendências. 2. Lê o exemplar. 3. Transação (ver `02 §4`): bloqueia a cópia, confere circulável/sem empréstimo ativo/sem retenção alheia, leitor ativo, regra, limite, atraso. 4. Cria `loans` (`due_on` = hoje + `loan_days`), `copy_events(loaned)`, auditoria; se havia reserva `ready` do leitor → `fulfilled`. 5. Exibe recibo: livro, código, **devolver até dd/mm**.
- **Alternativos:** A1 vários livros em sequência para o mesmo leitor (mantém o leitor selecionado). A2 reserva `ready` deste leitor → empréstimo da cópia retida.
- **Exceções:** UC-CIR-002…006, 015. Qualquer falha → **nada** é gravado.
- **Banco:** `loans`+1, `copy_events`+1, `audit_logs`+1, `reservations` (se atendida).
- **Regras:** RN-CIR-01…06, 10, 15; RN-ACE-07.
- **Mensagens:** "Empréstimo registrado. Devolver até 02/12." 
- **Aceitação:** exemplar passa a constar como emprestado (derivado); `due_on` conforme regra; histórico criado.
- **Testes:** Feature (feliz); limites; **concorrência**: 2 empréstimos simultâneos da mesma cópia → 1 sucesso e 1 `[EXEMPLAR_JA_EMPRESTADO]` (PostgreSQL); rollback quando o evento falha (injetado); autorização.

### UC-CIR-002 — Exemplar indisponível
Situações e resposta (bloqueiam o empréstimo):

| Motivo | Código | Mensagem ao operador |
|---|---|---|
| Já emprestado | `[EXEMPLAR_JA_EMPRESTADO]` | "Este exemplar já está emprestado (devolução prevista em 02/12)." (nome do leitor só para BIB/ADM) |
| Em manutenção | `[EXEMPLAR_INDISPONIVEL]` | "Exemplar em manutenção." |
| Perdido / baixado | `[EXEMPLAR_INDISPONIVEL]` | "Exemplar perdido/baixado — não circula." |
| Retido para outra pessoa | `[EXEMPLAR_RETIDO_OUTRO]` | "Exemplar reservado para outro leitor até 18h de 20/11." |

Alternativos: oferecer **reservar** o livro (UC-CIR-011) ou buscar outra cópia disponível do mesmo livro (lista).
**Testes:** uma por motivo; sugestão de outra cópia.

### UC-CIR-003 — Exemplar inexistente
Código sem correspondência → `[EXEMPLAR_NAO_ENCONTRADO]` e UC-ACE-003 (busca manual, código antigo). O empréstimo **não** é criado e **nenhum exemplar é criado** automaticamente. **Teste:** código inexistente/código mal formatado.

### UC-CIR-004 — Leitor não encontrado
Busca por matrícula/nome sem resultado → `[LEITOR_NAO_ENCONTRADO]`: "Nenhum leitor encontrado. Confira a matrícula ou pesquise por nome." Para quem tem `borrower.create` oferece "Cadastrar leitor" (UC-PES-001); AUX sem a permissão só vê a mensagem. **Teste:** mensagem por perfil.

### UC-CIR-005 — Leitor sem autorização para empréstimo
Leitor `inactive`/`transferred`/`graduated` → `[LEITOR_INATIVO]` "Este leitor não está ativo. Procure a coordenação." Tipo sem regra → `[REGRAS_NAO_CONFIGURADAS]`. Limite atingido → `[LIMITE_EMPRESTIMOS]` "Este leitor já tem N empréstimos (limite N)". **Testes:** um por motivo; leitor reativado volta a emprestar.

### UC-CIR-006 — Leitor com atrasos
Se `block_when_overdue`: bloqueia com `[LEITOR_COM_ATRASO]` listando os empréstimos vencidos (livro, dias de atraso). Se não bloquear: **aviso** destacado e o operador confirma. Nenhuma multa (RN-CIR-13). **Testes:** ambos os modos de configuração; atraso derivado com `Carbon::setTestNow`.

### UC-CIR-007 — Devolver exemplar
- **Ator:** AUX, BIB, ADM. **Pré:** `loan.return`.
- **Principal:** 1. Ator lê o código do exemplar. 2. Sistema encontra o **empréstimo ativo** do exemplar. 3. Mostra livro, leitor, `due_on` e se há atraso. 4. Ator confirma (e, opcionalmente, ajusta condição/observação). 5. Transação: `returned_at`, `returned_by`, `outcome=returned`; `copy_events(returned)`; se existe reserva `waiting` para o livro → UC-CIR-014. 6. Mensagem: "Devolução registrada." (+ "Atraso de N dias" se houver).
- **Alternativos:** A1 devolver com dano → atualizar `condition`/mandar para manutenção (UC-ACE-006) na mesma tela. A2 devolver vários em lote.
- **Exceções:** E1 sem empréstimo ativo → UC-CIR-008. E2 falha → nada alterado; o exemplar continua emprestado e o operador é avisado.
- **Regras:** RN-CIR-08, 10, 12, 15. **Testes:** feliz; atraso; devolução repetida (idempotência: 2ª vez → aviso); transação; retenção para fila.

### UC-CIR-008 — Devolução de exemplar sem empréstimo, ou "diferente"
| Situação | Tratamento |
|---|---|
| Exemplar **sem** empréstimo ativo | Aviso (não erro): "Este exemplar não consta como emprestado." Mostra último empréstimo e situação; permite apenas conferir/reguardar |
| Leitor traz **outra cópia do mesmo livro** (cópia A emprestada, devolve B) | Sistema detecta que B não está emprestada, mas o leitor tem a cópia A do mesmo livro: **oferece devolver A** e sugere corrigir etiquetas/conferir (registrado) |
| Livro **de outro livro** | Sem empréstimo ativo → aviso simples |
| Código ilegível | UC-ACE-003 |
**Regras:** RN-CIR-08. **Testes:** os quatro cenários.

### UC-CIR-009 — Renovar empréstimo
Ator: AUX, BIB, ADM, (leitor, em portal futuro — fora de escopo). Pré: `loan.renew`; empréstimo ativo.
Principal: 1. Seleciona empréstimo (por exemplar ou leitor). 2. Sistema valida RN-CIR-07. 3. Cria `loan_renewals` (previous/new `due_on`), atualiza `due_on` (= hoje ou vencimento atual + `renewal_days`, conforme regra), `copy_events(renewed)`, audita. 4. "Renovado até dd/mm."
Exceções: limite de renovações → `[RENOVACAO_NEGADA]` "Limite de renovações atingido"; reserva em espera → "Há outra pessoa aguardando este livro"; leitor inativo; sem regra.
**Testes:** cada negativa; contador; `due_on` novo; auditoria.

### UC-CIR-010 — Renovar empréstimo vencido
Depende de `renew_when_overdue`. **Não** permitido → `[RENOVACAO_NEGADA]` "Empréstimo vencido: registre a devolução." **Permitido** → renova a partir **de hoje** (não do vencimento antigo) e mantém o registro de que estava vencido (`loan_renewals.previous_due_on < hoje`). **Testes:** ambos os modos.

### UC-CIR-011 — Reservar livro
Ator: AUX, BIB, ADM. Pré: `reservation.create`; leitor ativo.
Principal: 1. Seleciona livro e leitor. 2. Sistema confirma que **não há exemplar disponível** (RN-CIR-09). 3. Cria `reservations(waiting)` com `requested_at`. 4. Informa a posição na fila.
Exceções: há cópia disponível → `[RESERVA_DESNECESSARIA]` "Há exemplar disponível: faça o empréstimo."; leitor já reservou → `[RESERVA_DUPLICADA]`; leitor já está com o livro → bloqueado; livro sem exemplares/arquivado → `[LIVRO_SEM_EXEMPLARES]`. **Testes:** FIFO; duplicada; concorrência de 2 reservas (ordem estável).

### UC-CIR-012 — Cancelar reserva
Ator: quem reservou (portal futuro), AUX/BIB/ADM. `waiting|ready → cancelled` com motivo. Se estava `ready`, a cópia retida é liberada e a retenção passa ao próximo da fila (ou volta a disponível). Exceção: reserva já `fulfilled` → `[TRANSICAO_INVALIDA]`. **Testes:** cancelar `ready` promove o próximo; auditoria.

### UC-CIR-013 — Livro reservado por outra pessoa
Ao tentar emprestar uma cópia **retida** (`ready`) para outro leitor → `[EXEMPLAR_RETIDO_OUTRO]`. Ao tentar **renovar** livro com fila → negado (RN-CIR-07). Ao consultar o catálogo → "N reservas". Exceção administrativa: BIB pode **forçar** o empréstimo com motivo (auditado) — *a confirmar* (decisão §10). **Testes:** retida/renovação/forçado.

### UC-CIR-014 — Devolução de livro com reserva pendente
Dentro da transa��o da devolu��o: 1. Seleciona a 1� `waiting` por `requested_at`. 2. `status=ready`, `copy_id`, `ready_at`, `expires_at` (= agora + `reservation_hold_hours`, se definido). 3. A c�pia deixa de estar dispon�vel (RN-ACE-07). 4. Mostra ao operador: "Reservado para JO�O (turma 2� A). Separar na estante de reservas." Job `ExpireReservations` (agendado): `ready` vencida ? `expired`, repete a sele��o para o pr�ximo; sem fila ? c�pia volta a dispon�vel.
Exceções: falha do job → próxima execução reprocessa (idempotente). **Testes:** promoção; expiração; ordem; sem fila.

### UC-CIR-015 — Falha durante a gravação de um empréstimo
Qualquer erro dentro da transação (queda do banco, violação de índice, evento não gravado) → **rollback integral**. O operador vê: "Não foi possível registrar o empréstimo. Nada foi gravado — tente novamente." **O sistema nunca mostra recibo/sucesso sem `COMMIT`.** Violação do índice único (corrida) → `[EXEMPLAR_JA_EMPRESTADO]`. Falha parcial por rede (resposta perdida) → ao reabrir a tela, consultar "últimos empréstimos" para conferir (idempotência por `copy` ativo). Erro é logado com contexto (usuário, exemplar), sem expor detalhes. **Testes:** rollback com falha injetada em cada passo; corrida no PostgreSQL; resposta sem sucesso quando o banco está indisponível.

### UC-CIR-016 — Estornar empréstimo (correção)
Ator: ADM. Para empréstimo lançado por engano: `outcome=voided` + motivo, restaura a disponibilidade (derivada), `copy_events`, auditoria. Não apaga. Exceção: empréstimo já devolvido há mais de N dias exige... (política a confirmar). **Testes:** estorno libera o exemplar; histórico mantido; só ADM.
