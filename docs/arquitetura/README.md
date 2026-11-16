# Organizador Escolar — Arquitetura e casos de uso

Documentação de projeto (investigação + modelagem). **Nada aqui foi implementado ainda**, exceto o catálogo
(marcado ✅ nos documentos).

| # | Documento | Conteúdo |
|---|---|---|
| 01 | [Diagnóstico](01-diagnostico.md) | Inventário do código atual, o que preservar, 16 problemas encontrados (P1–P16) |
| 02 | [Arquitetura modular](02-arquitetura-modular.md) | Módulos, dependências, estrutura de diretórios, responsabilidades por camada, fluxo de exemplo |
| 03 | [Modelagem de domínio](03-modelagem-dominio.md) | Decisões (autor/editora/disciplina), **DER**, dicionário de dados, exclusão e histórico |
| 04 | [Regras de negócio e integridade](04-regras-negocio-integridade.md) | Regras `RN-…`, estados, transações, **prova de concorrência no PostgreSQL** |
| 05 | [Casos de uso: Catálogo e Scanner](05-casos-de-uso-catalogo-scanner.md) | UC-CAT-001…009, UC-SCN-001…015 |
| 06 | [Casos de uso: Acervo e Circulação](06-casos-de-uso-acervo-circulacao.md) | UC-ACE-001…008, UC-CIR-001…016 |
| 07 | [Casos de uso: Pessoas, Admin e Gestão](07-casos-de-uso-pessoas-admin-gestao.md) | UC-PES, UC-ADM, UC-GES |
| 08 | [Erros e permissões](08-erros-permissoes.md) | Classes de erro, códigos, matriz de cenários, **matriz de permissões** |
| 09 | [Rotas e telas](09-rotas-telas.md) | Mapa de rotas e de telas |
| 10 | [Testes, sequência, riscos e decisões](10-testes-plano-riscos.md) | Estratégia de testes, 8 fases, 14 riscos, **17 decisões a confirmar** |

Outros: [`../validation-report.md`](../validation-report.md) (validação do catálogo, roteiro de câmera, segurança).

## Resumo das decisões de arquitetura

1. **Monólito modular leve** no Laravel 12: módulos = subpastas de `Controllers/Services/DTOs/Requests/Policies`; `Models` plano.
2. **Livro (edição) ≠ Exemplar (cópia)**: `books` já é a edição; `book_copies` é novo, `RESTRICT`, nunca excluído.
3. **Estado derivado** (disponível/emprestado/atrasado) em vez de gravado → sem dessincronização. Só o estado administrativo do exemplar é gravado.
4. **Um empréstimo ativo por exemplar** garantido por *lock* de linha + índice único parcial — **verificado no PostgreSQL 17** (com proteção: 1 ativo; sem: 2).
5. **Leitor ≠ Usuário**: `borrowers` (quem pega livros) separado de `users` (quem opera).
6. **Sem valores inventados**: regras de empréstimo em `loan_rules` sem padrão; sem regra = empréstimo bloqueado.
7. **Sem Repository**; DTO só com 4+ campos; Service para regra/transação; CRUD simples fica no controller.
8. **Autor/Editora** continuam como texto; **Disciplina e Categoria** viram uma tabela com `type`.
9. **Histórico imutável**: FKs `RESTRICT`, estorno em vez de exclusão, `audit_logs` e `copy_events` *append-only*.
10. **Fases**: 0 Fundações (auth, enums, auditoria) → 1 Catálogo → 2 Acervo → 3 Pessoas → **4 Circulação (MVP)** → 5 Reservas → 6 Gestão → 7 Administração.
