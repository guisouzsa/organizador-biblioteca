# 08 — Matriz de erros e permissões

## 1. Classes de erro e resposta

| Classe | HTTP | Quando | Resposta ao usuário | Registro interno |
|---|---|---|---|---|
| **Validação** | 422 | Formato/limites inválidos | Mensagem por campo (pt-BR), formulário preservado | — |
| **Recurso inexistente** | 404 | Livro/exemplar/leitor/reserva não existe | "Não encontrado" + próxima ação (buscar/cadastrar) | — |
| **Conflito de negócio** | 409 (JSON) / redirect com erro (web) | Regra violada (indisponível, duplicidade, limite…) | Mensagem **específica e acionável**, sem termos técnicos | `Log::info` com código |
| **Falha de infraestrutura** | 500/503 | Banco, disco, exceção inesperada | "Não foi possível concluir. Nada foi gravado. Tente novamente." | `Log::error` com contexto (usuário, rota, id de correlação); **nunca** exibe SQL/stack |
| **Falha externa** | 200 *degradado* | Google/Open Library/e-mail indisponíveis | Informa o que não respondeu e oferece o fluxo manual | `Log::warning` |
| **Falta de autorização** | 401 / 403 | Sem login / sem permissão | "Entre para continuar." / "Você não tem permissão para esta ação." | `audit_logs(access_denied)` |
| **Limite de uso** | 429 | *Rate limit* | "Muitas tentativas. Aguarde um minuto." | — |

Regras gerais: (1) erro de negócio **não** é exceção genérica; vem de `DomainException` com código estável.
(2) Em falha de gravação, **nunca** exibir sucesso. (3) Nenhuma mensagem expõe credenciais, URLs com chave, SQL,
caminhos ou dados de outra pessoa. (4) Cada resposta de erro leva um **id de correlação** para o suporte.

## 2. Catálogo de códigos

| Código | Classe | Mensagem (pt-BR) |
|---|---|---|
| `VALIDACAO` | Validação | "Corrija os campos destacados." |
| `ISBN_INVALIDO` | Validação | "ISBN inválido: o dígito verificador não confere." |
| `ISBN_NAO_ISBN` | Validação | "O código lido é um EAN-13, mas não é um ISBN." |
| `ISBN_DUPLICADO` | Conflito | "Este ISBN já está no catálogo." |
| `CONFLITO_EDICAO` | Conflito | "Este registro foi alterado por outra pessoa. Revise as diferenças." |
| `LIVRO_NAO_ENCONTRADO` | 404 | "Livro não encontrado." |
| `LIVRO_ARQUIVADO` | Conflito | "Este livro está arquivado." |
| `LIVRO_SEM_EXEMPLARES` | Conflito | "Este livro ainda não tem exemplares." |
| `FONTE_INDISPONIVEL` | Externa | "As fontes bibliográficas estão indisponíveis. Cadastre manualmente ou tente de novo." |
| `FONTE_LIMITE` | Externa | "Limite das fontes atingido. Tente novamente em instantes." |
| `EXEMPLAR_NAO_ENCONTRADO` | 404 | "Código não encontrado. Confira o número ou busque o livro." |
| `EXEMPLAR_DUPLICADO` | Conflito | "Este código/tombo já pertence a outro exemplar." |
| `EXEMPLAR_INDISPONIVEL` | Conflito | "Este exemplar não está disponível para empréstimo." |
| `EXEMPLAR_JA_EMPRESTADO` | Conflito | "Este exemplar já está emprestado." |
| `EXEMPLAR_RETIDO_OUTRO` | Conflito | "Exemplar reservado para outro leitor." |
| `TRANSICAO_INVALIDA` | Conflito | "Esta mudança de situação não é permitida." |
| `LEITOR_NAO_ENCONTRADO` | 404 | "Nenhum leitor encontrado." |
| `LEITOR_INATIVO` | Conflito | "Este leitor não está ativo." |
| `LEITOR_COM_ATRASO` | Conflito | "Este leitor tem empréstimos em atraso." |
| `LIMITE_EMPRESTIMOS` | Conflito | "Limite de empréstimos do leitor atingido." |
| `REGRAS_NAO_CONFIGURADAS` | Conflito | "Regras de empréstimo não configuradas para este tipo de leitor." |
| `RENOVACAO_NEGADA` | Conflito | "Não é possível renovar: *motivo*." |
| `RESERVA_DUPLICADA` / `RESERVA_DESNECESSARIA` | Conflito | "Já existe reserva deste livro para o leitor." / "Há exemplar disponível: faça o empréstimo." |
| `MATRICULA_DUPLICADA` | Conflito | "Já existe um leitor com esta matrícula." |
| `CATEGORIA_DUPLICADA` / `EM_USO` | Conflito | "Já existe uma categoria com este nome." / "Em uso: arquive em vez de excluir." |
| `ULTIMO_ADMIN` | Conflito | "É preciso manter ao menos um administrador ativo." |
| `NAO_AUTENTICADO` / `SEM_PERMISSAO` | Autorização | "Entre para continuar." / "Você não tem permissão para esta ação." |
| `LIMITE_REQUISICOES` | 429 | "Muitas tentativas. Aguarde um minuto." |
| `FALHA_INTERNA` / `BANCO_INDISPONIVEL` | Infra | "Não foi possível concluir. Nada foi gravado." |

## 3. Matriz de cenários

| Situação | Classe | Comportamento esperado |
|---|---|---|
| Livro não existe | 404 | Oferecer identificação (scanner/ISBN) ou cadastro manual |
| Livro existe, exemplar não | Negócio | Oferecer registrar exemplar |
| ISBN inválido | Validação | Informar o erro específico e permitir correção |
| EAN-13 que não é ISBN | Validação | Avisar, continuar lendo, sem consulta externa |
| Código não lido | — | Tentar de novo, trocar câmera, digitar ISBN ou cadastro manual (**não** dizer "livro inexistente") |
| Código já utilizado | Conflito | Mostrar o registro correspondente; nunca sobrescrever |
| API externa indisponível | Externa | Dizer qual falhou; permitir fluxo manual; não gravar nada sozinho |
| API devolve 429 | Externa | Mensagem de cota; sem retentativa automática |
| Banco indisponível | Infra | **Não** afirmar que salvou; nada gravado; tentar de novo |
| Pessoa inexistente | 404 | Permitir pesquisa; cadastro só para quem tem permissão |
| Exemplar indisponível | Conflito | Bloquear; explicar o motivo; sugerir reservar/outra cópia |
| Exemplar emprestado devolvido 2× | Aviso | "Já consta como devolvido" (idempotente) |
| Dois balcões emprestam a mesma cópia | Conflito | Um vence; o outro recebe `EXEMPLAR_JA_EMPRESTADO` |
| Reserva expirada | Negócio | Passa ao próximo da fila; cópia volta a disponível se não há fila |
| Usuário sem permissão | Autorização | Impedir; informar restrição; auditar tentativa |
| Sessão expirada no meio do formulário | Autorização | Reautenticar e **preservar** o que foi digitado |
| Operação incompleta (falha no meio) | Infra | Rollback total; consistência garantida; mensagem clara |
| Resposta de rede perdida após gravar | Infra | Conferir "últimos empréstimos"; reenvio não duplica (índice único) |
| Dado externo malformado | Externa | Campo vira vazio; nunca quebra a tela (já tratado) |
| Capa indisponível | — | Placeholder com título |
| Conta desativada | Autorização | Login negado; histórico preservado |
| Último admin | Conflito | Impedir desativar/rebaixar |
| Edição concorrente | Conflito | Mostrar diferenças; decidir manualmente |

## 4. Papéis e permissões

Perfis propostos (a confirmar com a escola — §10 do plano):

| Perfil | Quem | Escopo |
|---|---|---|
| **ADM** Administrador | Direção/TI | Tudo, incluindo usuários, regras, auditoria, estorno, anonimização |
| **BIB** Bibliotecário | Responsável pela biblioteca | Catálogo, acervo, circulação, leitores, relatórios |
| **AUX** Auxiliar de circulação | Monitor/estagiário | Balcão: emprestar, devolver, renovar, reservar, consultar |
| **CON** Consulta | Professor/coordenação | Somente leitura do catálogo e da disponibilidade |

Legenda: ● permitido · ◐ permitido com restrição · — negado.

| Ação / Permissão | ADM | BIB | AUX | CON |
|---|:-:|:-:|:-:|:-:|
| Consultar catálogo, disponibilidade (`catalog.view`) | ● | ● | ● | ● |
| Cadastrar/editar livro (`catalog.create/update`) | ● | ● | — | — |
| Arquivar livro (`catalog.archive`) | ● | ● | — | — |
| Excluir livro sem histórico / mesclar duplicados | ● | — | — | — |
| Gerir categorias, disciplinas, coleções | ● | ● | — | — |
| Ver exemplares e histórico | ● | ● | ◐ (situação, local) | ◐ (disponível sim/não) |
| Registrar exemplares / reetiquetar | ● | ● | — | — |
| Mudar local / condição | ● | ● | ◐ (local) | — |
| Perdido / manutenção / baixa | ● | ● | — | — |
| Restaurar exemplar baixado | ● | — | — | — |
| Emprestar / devolver (`loan.create/return`) | ● | ● | ● | — |
| Renovar | ● | ● | ● | — |
| Reservar / cancelar reserva | ● | ● | ● | — |
| Forçar empréstimo de cópia retida | ● | ◐ (com motivo) | — | — |
| Estornar empréstimo | ● | — | — | — |
| Ver/pesquisar leitores | ● | ● | ◐ (sem contatos) | — |
| Cadastrar/editar leitor | ● | ● | — | — |
| Inativar leitor / anonimizar | ● | ◐ (inativar) | — | — |
| Dashboard e relatórios (`report.view`) | ● | ● | ◐ (atrasos do dia) | — |
| Exportar relatórios | ● | ● | — | — |
| Usuários e permissões | ● | — | — | — |
| Regras de empréstimo e configurações | ● | — | — | — |
| Auditoria | ● | ◐ (catálogo/próprios) | — | — |

Implementação: `UserRole` (Enum) → mapa de *abilities*; Policies consultam o mapa e o **estado do recurso** (ex.: `LoanPolicy::return`
só se o empréstimo está ativo). Toda rota mutável passa por `auth` + `can:`. Testes verificam a matriz **inteira** (uma linha da
tabela = um teste por perfil, esperado 200/403).
