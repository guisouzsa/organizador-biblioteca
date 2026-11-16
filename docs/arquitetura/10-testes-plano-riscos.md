# 10 — Estratégia de testes, sequência de implementação, riscos e decisões

## 1. Estratégia de testes

| Tipo | Ferramenta | Onde roda | O que cobre |
|---|---|---|---|
| **Unitário** | PHPUnit (`tests/Unit`) | SQLite/sem banco | Enums e transições, ISBN, cálculo de prazo/atraso (`Carbon::setTestNow`), `LoanRuleResolver`, normalização de títulos |
| **Feature** | PHPUnit (`tests/Feature/<Modulo>`) | SQLite **e** PostgreSQL | Rotas, Requests, Services, Policies, transações, mensagens |
| **Validação** | Feature | ambos | Cada regra do Form Request (limites, formatos, obrigatórios) |
| **Autorização** | Feature, orientado à **matriz do `08`** | ambos | 1 linha da matriz × 4 perfis = teste de 200/403; recurso em estado inválido; não autenticado → login |
| **Integridade** | Feature | **PostgreSQL** (obrigatório) e SQLite | Unicidades, índices parciais, `RESTRICT`, `CHECK`, cascatas |
| **Concorrência** | PHPUnit com processos paralelos (ex.: `pcntl`/`proc_open`) ou script `psql` | **somente PostgreSQL** | 2 empréstimos da mesma cópia; 2 reservas; 2 cadastros do mesmo ISBN/código |
| **Falhas injetadas** | Feature com *fakes* que lançam exceção em cada passo da transação | ambos | Rollback integral (empréstimo, N exemplares, mesclagem) |
| **Serviços externos** | `Http::fake` (comportamento) + **servidor de falhas real** + chamada real | CI: fake; manual: real | Mock **não** prova integração: a prova é a chamada real (Open Library ✅; Google exige chave) |
| **Supabase** | `db:check-connection` + suíte `phpunit.pgsql.xml` apontando para banco **descartável** | manual/CI com segredo | Prova de conexão e de migrations remotas — **pendente de credenciais** |
| **Navegador** | Puppeteer + Chrome (câmera virtual) em `tests/e2e/` | manual/CI com Chrome | Scanner, balcão, responsividade |
| **Dispositivo físico** | Roteiro manual (`docs/validation-report.md`) | manual | Foco, luz, desempenho, câmera real |

Princípios: (1) **cada UC tem ao menos um teste do fluxo principal e um por exceção**; (2) todo `RN-` tem um teste que
cita o ID no nome (`test_rn_cir_01_...`); (3) testes de integridade e concorrência **rodam no PostgreSQL** — o SQLite
não reproduz corrida entre processos; (4) mensagens de erro são verificadas (contrato com o usuário);
(5) `Carbon::setTestNow` em tudo que depende de data; (6) nenhum teste aponta para o banco do Supabase de produção.

### Cobertura mínima por caso de uso (resumo)
| Módulo | Principal | Exceções | Autorização | PostgreSQL/concorrência |
|---|---|---|---|---|
| CAT | 9 UCs ✅◐ → 🆕 diff/auditoria/mesclar/arquivar | ISBN inválido, duplicado, fonte fora, conflito de edição | catalog.* por perfil | duplicidade concorrente ✅ |
| SCN | 15 situações (✅ maioria no navegador) | EAN não ISBN, permissão, sem câmera | — | — |
| ACE | 8 UCs | tombo/código duplicado, transições inválidas, rollback | por perfil | unicidade de códigos; N cópias tudo-ou-nada |
| CIR | 16 UCs | indisponível, inativo, atraso, limite, sem regra, renovação, reserva | por perfil/estado | **corrida de empréstimo e de reserva**; rollback por passo |
| PES | 12 UCs | matrícula duplicada, último admin, token expirado | acesso negado a `/admin/*` | unicidade de matrícula |
| ADM/GES | 6 + 7 UCs | regra ausente, valores inválidos | só ADM | consultas de relatório com volumes |

Estimativa de crescimento: de 96 para **~450–550** testes ao final das fases (a maioria Feature).

## 2. Sequência recomendada de implementação

Cada fase termina com: testes verdes em **SQLite e PostgreSQL**, build ok, documentação atualizada, commits por
categoria, **sem** migration remota sem autorização.

| Fase | Entrega | Por que nessa ordem | Pré-requisitos | Critério de saída |
|---|---|---|---|---|
| **0 — Fundações** | Namespacing por módulo (sem mudar comportamento) · Enums · `DomainException` + *handler* · **autenticação** (login/logout/recuperação) · `users.role/active` · Policies + matriz · `audit_logs` + `AuditLogger` · `library_settings` · fuso America/Fortaleza · RLS aplicada | Nada sai do ar sem login; todos os módulos dependem de auth/auditoria/erros | Decisões D1–D5 (§4) | Catálogo atual protegido por perfil; testes de matriz; nenhuma rota pública exceto login |
| **1 — Catálogo completo** | `categories.type/archived_at` · `books.archived_at` · `BookData` DTO · unificar regra de ISBN (P2 do diagnóstico) · atualizar metadados com diff (CAT-003/005) · aviso de duplicata sem ISBN · arquivar · gerir categorias/coleções · auditoria nas edições | Fecha o que já existe antes de construir em cima | Fase 0 | UC-CAT-001…009 aceitos |
| **2 — Acervo** | `locations`, `book_copies`, `copy_barcodes`, `copy_events` · registrar N exemplares · etiquetas Code 128 · estados/transições · scanner de **exemplar** · contadores no catálogo | Circulação precisa de exemplares | Fase 1; decisão de etiqueta/impressora | UC-ACE-001…008 aceitos; testes de transição e unicidade em PostgreSQL |
| **3 — Pessoas (leitores)** | `borrowers` · cadastro/pesquisa/status · identificação por matrícula · anonimização | Circulação precisa de leitores (pode andar **em paralelo** à Fase 2) | Fase 0; decisão LGPD/origem dos leitores | UC-PES-001…007 aceitos |
| **4 — Circulação núcleo** | `loan_rules` + tela de regras · `loans` · empréstimo · devolução · renovação · estorno · balcão | Coração do sistema; exige 2, 3 e regras | Fases 2, 3; **decisão sobre prazos/regras** | UC-CIR-001…010, 015, 016; **teste de corrida no PostgreSQL**; rollback por passo |
| **5 — Reservas** | `reservations` · fila FIFO · retenção · job `ExpireReservations` · agendador | Depende de empréstimos/devolução | Fase 4; infraestrutura de *scheduler* (decisão D4) | UC-CIR-011…014 |
| **6 — Gestão** | Painel · relatórios · exportação | Somente leitura; precisa dos dados anteriores | Fases 2–5 | UC-GES-001…007 |
| **7 — Administração e endurecimento** | Gestão de usuários · auditoria (tela) · pendências · mesclar livros · estorno · LGPD | Operação segura e manutenção | Fases anteriores | UC-ADM/PES restantes; revisão de segurança |

Dependências: `0 → 1 → 2 → 4 → 5`, `0 → 3 → 4`, `2,3,4,5 → 6`. O **MVP operacional** é a Fase 4 (empréstimo e devolução
funcionando, com login e regras configuradas).

Em cada fase: migrations **aditivas**; nenhuma exclusão de dado; revisão do SQL no PostgreSQL local antes de
qualquer execução no Supabase.

## 3. Riscos técnicos

| # | Risco | Impacto | Mitigação |
|---|---|---|---|
| R1 | Supabase **não conectado/validado** (credenciais, IPv6 → Session Pooler) | Bloqueia produção | Validar antes da Fase 0 sair; suíte `pgsql` em banco descartável |
| R2 | **Data API do Supabase** expõe tabelas sem RLS | Vazamento/alteração de dados com a chave pública | Migration de RLS (pronta) em **toda** tabela nova; ou desativar a Data API |
| R3 | **Corrida** em empréstimos/reservas | Duplo empréstimo | `lockForUpdate` + índice único parcial (verificado no PostgreSQL 17); testes de corrida |
| R4 | `pooler` em modo *transaction* quebra *prepared statements*/locks de sessão | Falhas intermitentes | Usar Session Pooler (já definido); testar locks no pooler real |
| R5 | Dados pessoais de **menores** (LGPD) | Risco legal | Dados mínimos, perfis, anonimização, auditoria de acesso, base legal definida pela escola |
| R6 | Fuso horário/vencimento no dia local | Atrasos errados | `due_on` (date), fuso America/Fortaleza, testes com `setTestNow` |
| R7 | Infraestrutura não definida (sessão/cache/fila/agendador em `file`/`sync`) | Rate limit, token de consulta e expiração de reservas incorretos em múltiplas instâncias | Definir hospedagem; cache/sessão compartilhados; `schedule:run` |
| R8 | **Etiquetas/leitor** (simbologia, impressora, qualidade de câmera) | Leitura ruim de código interno | Code 128 com margem; teste em aparelho real antes de rotular o acervo |
| R9 | Qualidade dos metadados externos (ex.: título sem relação) | Catálogo poluído | Confirmação humana obrigatória (já existe) + procedência |
| R10 | Cota do Google Books (429) | Fonte inutilizável | Chave de API; Open Library como fallback; cache curto se necessário |
| R11 | Importação de dados legados (tombos, alunos) sem procedência | Dados incorretos | Sem importação em massa até haver origem confiável e prova de procedência |
| R12 | Crescimento de complexidade em `Book`/serviços | Manutenção | Regras nos Services; módulos por pasta; revisão a cada fase |
| R13 | Dependência de **e-mail** para recuperar senha | Fluxo incompleto | Redefinição por ADM como alternativa; escolher provedor |
| R14 | Cobertura de câmera só em navegador virtual | Falhas em aparelhos reais | Roteiro manual em Android/computador antes de usar na escola |

## 4. Decisões que precisam de confirmação

Nenhuma delas foi assumida. Onde o sistema precisa de um valor, ele **bloqueia** a função até ser configurado.

| # | Decisão | Opções / recomendação | Bloqueia |
|---|---|---|---|
| D1 | **Autenticação**: usar `Auth` nativo do Laravel (controllers próprios) vs. starter kit (Breeze) | Recomendado: nativo, sem dependência | Fase 0 |
| D2 | **Recuperação de senha**: provedor de e-mail (SMTP/Resend) ou só redefinição por ADM | — | Fase 0 (parcial) |
| D3 | **Perfis**: ADM/BIB/AUX/CON atendem? Professores podem reservar? Aluno terá login (portal)? | Recomendado começar com os 4 perfis; portal fora de escopo | Fase 0 |
| D4 | **Hospedagem** (sessão/cache/fila/agendador/HTTPS) | A definir; afeta R7 | Fase 0/5 |
| D5 | **Uma escola por instalação** ou multi-escola (`school_id`) | Recomendado: uma escola; multi-escola custa caro depois | Modelagem (agora) |
| D6 | **Regras de empréstimo** (prazo, limite, renovações, bloqueio por atraso, retenção de reserva) | Definidas pela escola/coordenação; **nada padronizado** | Fase 4 |
| D7 | **Multas** | Recomendado: não ter; se houver, caso de uso próprio | — |
| D8 | **Calendário escolar** (dias não letivos pausam o prazo?) | Pode ser fase posterior | Fase 4 (opcional) |
| D9 | **Tombo/patrimônio**: já existe numeração? É obrigatório? Há acervo legado a catalogar? | Define `accession_number` e migração | Fase 2 |
| D10 | **Etiqueta**: simbologia (Code 128 recomendado), impressora, formato | Testar em aparelho | Fase 2 |
| D11 | **Escala de conservação** (`good/fair/poor/damaged` proposta) e motivos de baixa | A escola ajusta | Fase 2 |
| D12 | **Origem dos leitores** (cadastro manual, planilha da secretaria, outro sistema) e base legal LGPD | Recomendado: manual no início | Fase 3 |
| D13 | **Reserva**: só quando não há exemplar disponível? BIB pode "forçar" empréstimo de cópia retida? | Recomendado: sim para a 1ª, com motivo auditado para a 2ª | Fase 5 |
| D14 | **Estorno**: prazo máximo para estornar empréstimo já devolvido | Definir | Fase 7 |
| D15 | **Idioma/fuso**: pt-BR e `America/Fortaleza` | Recomendado | Fase 0 |
| D16 | Normalizar **autores/editoras** no futuro? | Decidir se surgir "livros por autor" | — |
| D17 | Credenciais do **Supabase** (host do Session Pooler, usuário, senha) e `GOOGLE_BOOKS_API_KEY` | Fornecer em `.env` local | Antes de produção |

## 5. Restrições respeitadas nesta etapa

Somente investigação, documentação e modelagem. **Nenhum código de domínio, migration ou dependência foi adicionado.**
O catálogo existente continua inalterado e compatível: todas as mudanças propostas em tabelas existentes são
**aditivas** (`books.archived_at`, `categories.type/archived_at`, `users.role/active/last_login_at`). O único
experimento executado foi a verificação de concorrência em um PostgreSQL descartável (tabelas temporárias, fora do
projeto), cujo resultado consta em `04 §3.2`.
