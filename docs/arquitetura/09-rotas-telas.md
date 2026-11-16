# 09 — Mapa de rotas e telas

Convenções: URLs em português (já adotado em `/catalogo`), nomes de rota em inglês (`books.index`). Todas as rotas, exceto
`/login` e recuperação de senha, exigem `auth`; autorização por `can:` (ver matriz em `08`). ✅ = existe hoje.
Mutações usam `POST/PUT/DELETE` com CSRF; consultas AJAX do balcão/scanner retornam JSON padronizado.

## 1. Mapa de rotas

### Autenticação e conta
| Método | URL | Nome | Caso de uso |
|---|---|---|---|
| GET/POST | `/login` · POST `/logout` | `login`, `logout` | UC-PES-012 |
| GET/POST | `/esqueci-a-senha`, `/redefinir-senha/{token}` | `password.*` | UC-PES-011 |
| GET/PUT | `/minha-conta` | `account.*` | trocar senha/dados próprios |

### Catálogo (CAT)
| Método | URL | Nome | Caso |
|---|---|---|---|
| GET ✅ | `/catalogo` | `books.index` | CAT-006 |
| GET ✅ | `/catalogo/novo` | `books.create` | CAT-004 |
| POST ✅ | `/catalogo` | `books.store` | CAT-001/003/004 |
| GET ✅ | `/catalogo/scanner` | `books.scanner` | SCN-* |
| POST ✅ | `/catalogo/isbn` | `books.lookup` | SCN-001…006 |
| GET ✅ | `/catalogo/{book}` | `books.show` | CAT-006, ACE-005 |
| GET ✅ | `/catalogo/{book}/editar` | `books.edit` | CAT-005 |
| PUT ✅ | `/catalogo/{book}` | `books.update` | CAT-005 |
| GET/POST | `/catalogo/{book}/atualizar-metadados` | `books.refresh` | CAT-003 (diff com fontes) |
| POST | `/catalogo/{book}/arquivar`, `/restaurar` | `books.archive/restore` | CAT-008 |
| GET/POST | `/catalogo/mesclar` | `books.merge` | CAT-007 |
| DELETE | `/catalogo/{book}` | `books.destroy` | CAT-008 (só sem histórico) |
| Resource | `/categorias`, `/colecoes` | `categories.*`, `collections.*` | ADM-001 |

### Acervo (ACE)
| Método | URL | Nome | Caso |
|---|---|---|---|
| GET | `/acervo` | `copies.index` | ACE-005/008 (busca de exemplares) |
| GET | `/acervo/scanner` | `copies.scanner` | ACE-002/003 (ISBN **ou** código de exemplar) |
| GET/POST | `/catalogo/{book}/exemplares/novo`, `/catalogo/{book}/exemplares` | `copies.create/store` | ACE-001 |
| GET | `/exemplares/{copy}` | `copies.show` | ACE-008 |
| PUT | `/exemplares/{copy}` | `copies.update` | ACE-006/007 (local, condição, notas) |
| POST | `/exemplares/{copy}/estado` | `copies.transition` | ACE-006 |
| POST | `/exemplares/{copy}/etiqueta` | `copies.relabel` | ACE-003 |
| GET | `/exemplares/etiquetas?ids=…` | `copies.labels` | impressão de etiquetas |
| POST | `/acervo/lookup` | `copies.lookup` | JSON: resolve código→exemplar |
| Resource | `/locais` | `locations.*` | ADM-006 |

### Circulação (CIR)
| Método | URL | Nome | Caso |
|---|---|---|---|
| GET | `/circulacao` | `circulation.desk` | balcão (empréstimo/devolução rápidos) |
| POST | `/circulacao/emprestimos` | `loans.store` | CIR-001…006, 015 |
| GET | `/circulacao/emprestimos` · `/{loan}` | `loans.index/show` | histórico/consulta |
| POST | `/circulacao/devolucoes` | `returns.store` | CIR-007/008 |
| POST | `/circulacao/emprestimos/{loan}/renovar` | `loans.renew` | CIR-009/010 |
| POST | `/circulacao/emprestimos/{loan}/estornar` | `loans.void` | CIR-016 |
| GET/POST | `/circulacao/reservas` · `/{reservation}/cancelar` | `reservations.*` | CIR-011…014 |
| POST | `/circulacao/lookup` | `circulation.lookup` | JSON: exemplar + leitor para o balcão |

### Pessoas (PES) e Administração (ADM)
| Método | URL | Nome | Caso |
|---|---|---|---|
| Resource | `/leitores` | `borrowers.*` | PES-001…007 |
| POST | `/leitores/{borrower}/status` · `/anonimizar` | `borrowers.status/anonymize` | PES-006/007 |
| GET | `/leitores/busca` | `borrowers.search` | JSON do balcão (PES-003/004) |
| Resource | `/admin/usuarios` | `users.*` | PES-008/009, ADM-003 |
| GET/PUT | `/admin/regras-de-emprestimo` | `loan-rules.*` | ADM-002 |
| GET/PUT | `/admin/configuracoes` | `settings.*` | ADM-006 |
| GET | `/admin/auditoria` | `audit.index` | ADM-004 |
| GET | `/admin/pendencias` | `admin.issues` | ADM-005 |

### Gestão (GES)
| Método | URL | Nome | Caso |
|---|---|---|---|
| GET | `/painel` | `dashboard` | GES-001 (rota inicial pós-login) |
| GET | `/relatorios/{mais-emprestados\|atrasos\|disponibilidade\|movimentacoes\|turmas}` | `reports.*` | GES-002…007 |
| GET | `/relatorios/{tipo}/exportar` | `reports.export` | GES-006 |

`/` redireciona para `/painel` (autenticado) ou `/login`. Hoje redireciona para `/catalogo` ✅ (mantido até a Fase 0).

## 2. Mapa de telas

Estados comuns de todas as telas: **carregando** (indicador e botão desabilitado durante envio; *skeleton* em listas),
**vazio** (explica e oferece a próxima ação), **erro** (mensagem do catálogo `08` + ação de recuperação),
**sem permissão** (403 clara). Confirmações **obrigatórias** só para ações destrutivas ou irreversíveis.

| Tela | Objetivo | Quem | Ações | Dados exibidos | Vazio | Erros possíveis | Confirmações | Navega para |
|---|---|---|---|---|---|---|---|---|
| **Login** | Entrar | todos | entrar, "esqueci a senha" | e-mail, senha | — | credencial inválida, conta inativa, bloqueio por tentativas | — | Painel |
| **Painel** | Visão do dia | BIB, ADM (AUX parcial) | atalhos Emprestar/Devolver/Escanear | indicadores GES-001, pendências | "Sem dados ainda" | consulta falhou (cartão com erro isolado) | — | Relatórios, Balcão, Pendências |
| **Catálogo** ✅ | Pesquisar livros | todos | buscar, filtrar, abrir, escanear, cadastrar | capa, título, autores, editora, ano, ISBN, categorias, nº exemplares/disponíveis 🆕 | "O catálogo ainda não tem livros" / "Nenhum livro corresponde" | filtro inválido | — | Livro, Scanner, Novo |
| **Scanner** ✅ | Identificar/cadastrar por ISBN | BIB, ADM | iniciar/parar, trocar câmera, digitar ISBN | vídeo, estado, resultado, divergências | "A imagem da câmera aparecerá aqui" | UC-SCN-002…015 | — (cadastro exige confirmar) | Livro, Cadastro |
| **Novo/Editar livro** ✅ | Cadastrar/corrigir | BIB, ADM | salvar, cancelar, atualizar existente | campos do livro, categorias, coleção | — | validação, duplicidade, conflito de edição | alterar ISBN com exemplares; sobrescrever campos | Livro |
| **Detalhe do livro** ✅ | Ver ficha | todos | editar, arquivar, registrar exemplares, reservar | metadados, fontes + verificação, **abas Exemplares/Reservas** 🆕 | "Nenhuma fonte registrada"; "Sem exemplares" | livro arquivado | arquivar | Exemplar, Edição |
| **Atualizar metadados** 🆕 | Aplicar dados novos | BIB | marcar campos, aplicar | diff local × fontes | "Nada a atualizar" | fonte indisponível | aplicar | Livro |
| **Mesclar livros** 🆕 | Unificar duplicados | ADM | escolher manter/absorver, confirmar | pré-visualização do que move | — | conflito de ISBN | **sim (irreversível)** | Livro |
| **Registrar exemplares** 🆕 | Criar cópias | BIB, ADM | quantidade, local, condição, tombo, imprimir etiquetas | livro, códigos gerados | — | tombo duplicado, livro arquivado | — | Etiquetas, Livro |
| **Exemplares (busca)** 🆕 | Achar cópias | BIB, AUX | buscar por código/tombo/livro/local | lista com situação derivada | "Nenhum exemplar encontrado" | código inexistente | — | Exemplar |
| **Exemplar** 🆕 | Ficha e histórico | BIB, AUX(◐) | mudar local/condição/estado, reetiquetar | livro, códigos (ativo/antigos), situação, empréstimo atual, **linha do tempo** | "Sem movimentações" | transição inválida | perdido, baixa, restauração | Livro, Empréstimo |
| **Etiquetas** 🆕 | Imprimir códigos | BIB | imprimir | código Code 128 + tombo + título curto | — | impressora/layout | — | Exemplar |
| **Balcão** 🆕 | Emprestar/devolver rápido | AUX, BIB, ADM | identificar leitor, ler exemplar, confirmar, devolver, renovar | cartão do leitor (pendências), recibo, prazo | "Identifique o leitor" | UC-CIR-002…008, 015 | devolver com dano; forçar retido | Empréstimos, Reservas |
| **Empréstimos** 🆕 | Consultar histórico | BIB, ADM, AUX(◐) | filtrar (ativos/atrasados/período), renovar, estornar | livro, exemplar, leitor, datas, atraso | "Nenhum empréstimo" | — | estorno (ADM) | Exemplar, Leitor |
| **Reservas** 🆕 | Gerir fila | AUX, BIB, ADM | reservar, cancelar | fila por livro, posição, prontas/expiração | "Sem reservas" | duplicada, desnecessária | cancelar | Livro, Leitor |
| **Leitores** 🆕 | Pesquisar/gerir | BIB, ADM (AUX leitura ◐) | buscar, cadastrar, editar, mudar status | nome, matrícula, turma, status, pendências | "Nenhum leitor" | matrícula duplicada | inativar com empréstimos; anonimizar | Ficha |
| **Ficha do leitor** 🆕 | Ver histórico | BIB, ADM | emprestar, editar, mudar status | dados, empréstimos ativos/histórico, reservas | "Sem empréstimos" | — | inativar/anonimizar | Empréstimo |
| **Usuários** 🆕 | Gerir contas | ADM | criar, perfil, desativar, redefinir senha | nome, e-mail, perfil, último acesso | — | último admin, e-mail duplicado | desativar | Auditoria |
| **Regras de empréstimo** 🆕 | Definir prazos/limites | ADM | salvar por tipo de leitor | campos de `loan_rules`, aviso de impacto | "Nenhuma regra configurada — empréstimos bloqueados" | valores inválidos | salvar (mostra impacto) | Painel |
| **Configurações / Locais** 🆕 | Parâmetros | ADM (locais: BIB) | editar, criar local | nome, prefixo, tombo, locais | "Sem locais" | local em uso | desativar local | — |
| **Disciplinas/Categorias/Coleções** 🆕 | Classificação | BIB, ADM | criar, arquivar, mesclar | nome, tipo, nº de livros, situação de verificação | — | duplicado, em uso | arquivar/mesclar | Catálogo filtrado |
| **Auditoria** 🆕 | Rastrear ações | ADM | filtrar, exportar | data, usuário, evento, entidade, diff | "Nenhuma atividade" | — | — | Entidade |
| **Pendências** 🆕 | Dados faltantes | BIB, ADM | ir à correção | listas por tipo (UC-ADM-005) | "Nenhuma pendência" | — | processar expirações | Exemplar/Leitor |
| **Relatórios** 🆕 | Analisar | BIB, ADM | período, filtros, exportar | tabelas/totais com período e data de geração | "Sem dados no período" | exportação falhou | — | Livro/Leitor |

Telas **não** previstas (sem caso de uso): portal do aluno, notificações, multas, importação em massa, recomendações.
Adicioná-las exige primeiro um caso de uso aprovado.
