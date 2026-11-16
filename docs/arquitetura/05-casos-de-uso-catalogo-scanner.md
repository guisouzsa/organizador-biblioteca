# 05 — Casos de uso: Catálogo e Scanner

Atores: **ADM** Administrador · **BIB** Bibliotecário · **AUX** Auxiliar de circulação · **CON** Consulta.
Códigos de erro (`[CÓDIGO]`) estão catalogados em `08-erros-permissoes.md`. Regras (`RN-…`) em `04`.
Estado: ✅ já implementado e testado · ◐ parcialmente · 🆕 a implementar.

## Catálogo

### UC-CAT-001 — Cadastrar um livro encontrado ✅◐
- **Objetivo:** salvar no catálogo um livro identificado por ISBN a partir das fontes bibliográficas.
- **Ator:** BIB, ADM. **Pré:** autenticado com permissão `catalog.create`.
- **Entrada:** ISBN (lido ou digitado), eventuais correções nos campos.
- **Principal:** 1. Ator digita/escaneia ISBN. 2. Sistema normaliza e valida (RN-CAT-01/03). 3. Consulta o catálogo local: não existe. 4. Consulta Google Books e Open Library em paralelo lógico, isoladas. 5. Compara e mescla (prioridade Google; lacunas pelo Open Library) e lista divergências. 6. Exibe formulário preenchido, fontes consultadas e divergências. 7. Ator confere/corrige e confirma. 8. Sistema grava `books`, categorias, `book_sources` (status `confirmed`) e auditoria de criação. 9. Redireciona ao detalhe com "Livro cadastrado no catálogo."
- **Alternativos:** A1 só uma fonte respondeu → mostra qual foi ignorada. A2 ator troca/adiciona categorias/coleção. A3 ator abandona → nada é gravado (RN-CAT-06). A4 ISBN-10 digitado → convertido e gravado com ambos.
- **Exceções:** E1 ISBN inválido → UC-SCN-004. E2 já cadastrado → UC-CAT-003. E3 nenhuma fonte → UC-CAT-002. E4 falha ao gravar → "Não foi possível salvar. Nada foi registrado." e o formulário permanece preenchido. E5 sem permissão → `[SEM_PERMISSAO]`.
- **Banco:** `books`+1; `book_category`; `book_sources`+1..2; `audit_logs`+1. Tudo em uma transação.
- **Regras:** RN-CAT-01…07, RN-SCN-01.
- **Mensagens:** "Resultado encontrado — confira os dados antes de salvar" · "Divergência entre fontes" · "Livro cadastrado no catálogo."
- **Aceitação:** o livro aparece no catálogo e é recuperável por ISBN-10/13, título e autor; a procedência aparece no detalhe; nenhum registro é criado antes da confirmação.
- **Testes:** Feature (cadastro por token, categorias, procedência) ✅; Feature (auditoria de criação) 🆕; Falha de fonte ✅; Integração real com Open Library ✅ (Google exige chave).

### UC-CAT-002 — Livro não encontrado ◐
- **Objetivo:** tratar ISBN válido sem metadados disponíveis, sem inventar informação.
- **Ator:** BIB. **Pré:** ISBN válido (RN-CAT-01).
- **Principal:** 1. ISBN válido; não está no catálogo. 2. Nenhuma fonte retorna dados (`not_found`). 3. Sistema informa e oferece **cadastro manual** com o ISBN já preenchido. 4. Ator preenche e salva como UC-CAT-004 (com ISBN).
- **Alternativos:** A1 só parte dos metadados veio (ex.: só título) → formulário com os campos disponíveis e os demais vazios, marcados como "não informado pela fonte". A2 ator conhece o livro mas não o ISBN → UC-CAT-004. A3 ator tenta de novo mais tarde → "Tentar novamente".
- **Exceções:** E1 ambas as fontes falharam (rede/429) → status `unavailable`: "As fontes estão indisponíveis. Tente novamente ou cadastre manualmente." **Não** afirma que o livro não existe. E2 1 falhou e 1 não achou → trata como "não encontrado" mencionando que uma fonte não respondeu.
- **Banco:** nenhuma até confirmar.
- **Regras:** RN-CAT-05, RN-CAT-06, RN-SCN-04.
- **Mensagens:** "Nenhuma fonte encontrou este ISBN. Você pode cadastrar o livro manualmente." · `[FONTE_INDISPONIVEL]`.
- **Aceitação:** distingue "não encontrado" de "fontes indisponíveis"; o cadastro manual abre com o ISBN; nenhum campo é preenchido por suposição.
- **Testes:** ✅ `not_found`, `unavailable`, link manual com ISBN; 🆕 dados parciais exibem campos vazios; 🆕 429 mostra mensagem de cota.

### UC-CAT-003 — Livro já cadastrado ◐
- **Objetivo:** impedir duplicidade e oferecer consulta/atualização conscientes.
- **Ator:** BIB. **Pré:** ISBN (13 ou 10) coincide com um `books` existente.
- **Principal:** 1. Sistema detecta o registro existente (um único ponto: `Book::withIsbn`). 2. Mostra capa, título, ISBN e ações: **Abrir registro** / **Editar** / **Atualizar com dados das fontes**. 3. Nenhum registro novo é criado.
- **Alternativos:** A1 ator só queria consultar → abre o detalhe (fim). A2 "Atualizar com dados das fontes" → o sistema consulta as fontes e mostra **diferenças campo a campo** (RN-CAT-11): campos vazios locais podem ser preenchidos por padrão; campos com valores diferentes ficam desmarcados; o ator escolhe; confirma; grava auditoria (RN-CAT-08) e nova `book_sources`. A3 mesma edição? ISBN igual ⇒ mesma edição por definição; ISBN diferente com título igual ⇒ **não** é duplicidade (outra edição). A4 envio duplo/concorrente → violação única tratada como duplicidade (já implementado).
- **Exceções:** E1 as fontes divergem do registro local → exibe divergência; nada é sobrescrito sem marcar. E2 registro foi alterado por outra pessoa durante a atualização → `[CONFLITO_EDICAO]`.
- **Bloqueia:** criação de segundo registro com o mesmo ISBN. **Permite (com confirmação):** atualização de metadados.
- **Banco:** `books` (update), `book_sources` (upsert), `audit_logs`+N.
- **Regras:** RN-CAT-02, 07, 08, 11.
- **Mensagens:** "Este ISBN já está no catálogo." · "Registro existente atualizado com os novos dados."
- **Aceitação:** `COUNT(books)` não muda; atualização exibe diff; auditoria registra quem mudou o quê.
- **Testes:** ✅ duplicidade por ISBN-10/13, confirmação, concorrência (PostgreSQL); 🆕 diff/preenchimento seletivo; 🆕 auditoria; 🆕 `[CONFLITO_EDICAO]`.

### UC-CAT-004 — Cadastrar livro sem ISBN ✅◐
- **Objetivo:** registrar obras antigas, apostilas ou edições sem ISBN.
- **Ator:** BIB. **Pré:** `catalog.create`.
- **Entrada:** **título (obrigatório)**; demais opcionais (autores, editora, ano, edição, páginas, categorias).
- **Principal:** 1. Ator abre `/catalogo/novo`. 2. Preenche. 3. Sistema busca **possíveis duplicatas** por título normalizado (sem acento/caixa) + autores + editora + ano e, se houver, exibe "Parece com: …" (aviso). 4. Ator confirma que é outro livro ou abre o existente. 5. Grava com `book_sources.provider=manual`.
- **Alternativos:** A1 ator descobre o ISBN depois → UC-CAT-005 (adicionar ISBN; unicidade validada). A2 informa ISBN no formulário → segue UC-CAT-001/003.
- **Exceções:** E1 título vazio → `[VALIDACAO]` "O campo título é obrigatório." E2 muitos candidatos → mostra os 5 mais próximos.
- **Banco:** `books`+1, `book_sources`+1 (manual), `audit_logs`.
- **Regras:** RN-CAT-04, RN-CAT-05.
- **Aceitação:** vários livros sem ISBN coexistem; semelhança **avisa** mas não impede; sem ISBN nenhum campo é inventado.
- **Testes:** ✅ coexistência e título obrigatório; 🆕 aviso de possível duplicata (normalização, acentos); 🆕 PostgreSQL.

### UC-CAT-005 — Editar e corrigir metadados ◐
- **Ator:** BIB, ADM. **Pré:** livro existente; `catalog.update`.
- **Principal:** 1. Abre `/catalogo/{id}/editar` (carrega `updated_at`). 2. Altera campos. 3. Valida (Form Request). 4. Sistema compara com o estado atual, grava e registra **diff** em `audit_logs` (campo, antes, depois, usuário, IP). 5. Marca a fonte afetada como `confirmed` e/ou acrescenta origem `manual` para os campos editados.
- **Alternativos:** A1 sem alterações → "Nada a alterar". A2 alterar ISBN em livro **com exemplares** → exige confirmação reforçada (RN-CAT-10) e destaca o impacto. A3 arquivar/restaurar → UC-CAT-008.
- **Exceções:** E1 ISBN pertence a outro livro → "Já existe outro livro cadastrado com este ISBN." (+ link). E2 conflito de edição (`updated_at` mudou) → `[CONFLITO_EDICAO]`. E3 sem permissão → `[SEM_PERMISSAO]`.
- **Banco:** `books`, `book_category`, `book_sources`, `audit_logs`.
- **Regras:** RN-CAT-02, 08, 10.
- **Aceitação:** toda mudança relevante tem registro de auditoria consultável; origem dos dados preservada.
- **Testes:** ✅ edição, ISBN de outro livro, manter próprio ISBN, remover categorias; 🆕 auditoria; 🆕 conflito de edição; 🆕 alteração de ISBN com exemplares.

### UC-CAT-006 — Consultar e pesquisar o catálogo ✅
- **Ator:** todos (CON inclusive). **Entrada:** termo (título, autor, ISBN-10/13), disciplina, editora, ano, coleção.
- **Principal:** lista paginada ordenada por título; cartão com capa, título, autores, editora, ano, ISBN, categorias, **e (🆕 após Acervo) nº de exemplares/disponíveis**.
- **Alternativos:** A1 sem resultados → estado vazio com "Limpar filtros". A2 catálogo vazio → orientação para escanear/cadastrar. A3 capa indisponível → placeholder com título.
- **Exceções:** filtro inválido (`year=abc`) → `[VALIDACAO]`.
- **Testes:** ✅ busca/filtros/vazios/validação/SQL-like; 🆕 ocultar arquivados por padrão; 🆕 acentos (se adotado `unaccent`).

### UC-CAT-007 — Mesclar livros duplicados 🆕
- **Ator:** ADM. **Pré:** dois registros do mesmo livro (ex.: um sem ISBN, outro com).
- **Principal:** 1. Seleciona "manter" e "absorver". 2. Sistema mostra o que será movido (exemplares, reservas, categorias, fontes) e conflitos de ISBN. 3. Confirma. 4. Transação: move relações, preenche campos vazios do mantido, arquiva o absorvido, audita.
- **Exceções:** os dois têm ISBN diferente → exige escolher qual vale (RN-CAT-10). Falha parcial → rollback total.
- **Regras:** RN-CAT-12. **Testes:** Feature (movimentação de exemplares/reservas); rollback em falha; PostgreSQL.

### UC-CAT-008 — Arquivar e restaurar livro 🆕
- **Ator:** BIB, ADM. **Principal:** arquivar remove da busca padrão e impede novos exemplares/reservas; exemplares existentes continuam consultáveis e circulam até serem baixados. **Exceção:** livro com empréstimo ativo pode ser arquivado (não afeta a devolução). **Excluir** só se nunca teve exemplares/reservas e só ADM. **Testes:** arquivamento, exclusão bloqueada por `RESTRICT`, auditoria.

### UC-CAT-009 — Gerenciar categorias, disciplinas e coleções 🆕
Ver `UC-ADM-001`.

## Scanner e códigos de barras

Regra-mãe: **RN-SCN-01 — falha de leitura não é inexistência do livro.** Em qualquer falha de leitura o sistema
oferece, nesta ordem: **1) Tentar novamente · 2) Escolher outra câmera · 3) Digitar o ISBN · 4) Cadastrar manualmente.**

Ator em todos: BIB/ADM (e AUX no scanner de exemplares/balcão). Pré: tela `/catalogo/scanner` aberta.

| ID | Situação | Comportamento esperado | Mensagem | Banco | Aceitação / Teste |
|---|---|---|---|---|---|
| UC-SCN-001 | Código lido corretamente (ISBN-13 válido) | Câmera desliga; "ISBN identificado"; consulta; segue UC-CAT-001/003/002 | "ISBN identificado: 978…" | — | 1 única consulta por leitura; câmera encerrada · ✅ navegador (câmera virtual) |
| UC-SCN-002 | Código ilegível/parcial (borrado, cortado) | Permanece em "Lendo código…"; nada é consultado; após ~15 s sem leitura sugere ajuda (iluminação, distância, limpar a lente) e as 4 opções | "Não conseguimos ler o código. Aproxime, melhore a luz ou digite o ISBN." | — | Nenhuma consulta; opções visíveis · 🆕 JS: temporizador de dica |
| UC-SCN-003 | EAN-13 que **não** é ISBN (789…, 5…) | Avisa e **continua lendo**; sem consulta externa | "O código 789… não é um ISBN (deve começar com 978 ou 979)." | — | 0 requisições · ✅ navegador |
| UC-SCN-004 | ISBN inválido (dígito verificador errado; lido ou digitado) | Mostra erro e permite corrigir/digitar | "ISBN-13 inválido: o dígito verificador não confere." | — | ✅ manual e API; 🆕 leitura com dígito errado simulada |
| UC-SCN-005 | ISBN válido sem cadastro | UC-CAT-001 (se há fontes) ou UC-CAT-002 | ver CAT | — | ✅ |
| UC-SCN-006 | ISBN já cadastrado | UC-CAT-003: abrir/editar, sem duplicar | "Este livro já está no catálogo" | — | ✅ |
| UC-SCN-007 | Câmera sem permissão (negada/bloqueada) | Estado de erro; **campo manual continua usável** | "Permissão da câmera negada. Libere o acesso nas configurações do navegador ou digite o ISBN." | — | ✅ navegador (permissão negada) |
| UC-SCN-008a | Nenhuma câmera detectada | Erro + alternativas | "Nenhuma câmera foi encontrada neste dispositivo. Digite o ISBN." | — | ✅ (emulado) |
| UC-SCN-008b | Câmera ocupada por outro aplicativo | Erro + "Tentar novamente" | "Não foi possível acessar a câmera. Ela pode estar em uso por outro aplicativo." | — | ◐ mapeado por `NotReadableError`; **não testado em aparelho** |
| UC-SCN-008c | Contexto inseguro (HTTP não-localhost) | Não abre câmera; explica | "A câmera exige HTTPS (ou localhost)…" | — | ✅ (emulado) |
| UC-SCN-009 | Falha de iluminação/foco | Igual a UC-SCN-002; considerar botão de lanterna (se `torch` disponível) e foco | idem | — | 🆕 **somente em dispositivo físico** (roteiro manual) |
| UC-SCN-010 | Leitura repetida do mesmo código | Ignorada (uma sessão = uma consulta) | — | — | ✅ 1 requisição (corrigido por token de sessão) |
| UC-SCN-011 | Código diferente na mesma sessão | Como a câmera desliga ao primeiro ISBN válido, o 2º livro exige novo "Iniciar". Códigos **não-ISBN** não encerram a sessão | "Pronto. Toque em Iniciar câmera para ler outro livro." | — | 🆕 fluxo "ler outro" em 1 toque |
| UC-SCN-012 | Usuário interrompe a leitura | Câmera para; estado "Câmera desligada" | "Câmera desligada." | — | ✅ |
| UC-SCN-013 | Digitação manual do ISBN | Mesmo caminho do scanner (UC-SCN-004/005/006); aceita ISBN-10/13 com/sem hifens | "Digite um ISBN para pesquisar." (vazio) | — | ✅ |
| UC-SCN-014 | Aba oculta / sair da página | Câmera encerrada imediatamente | "Câmera desligada ao sair da aba…" | — | ✅ (lógica); comportamento real do navegador no aparelho 🆕 |
| UC-SCN-015 | Limite de consultas (429 interno) | Bloqueia consulta por ~1 min | "Muitas consultas seguidas. Aguarde cerca de um minuto." | — | ✅ |

**Extensão futura (Acervo):** o mesmo componente lê **códigos de exemplar** (Code 128). O `RN-SCN-03` muda de
"rejeitar não-ISBN" para "classificar o código": ISBN → catálogo · código interno → exemplar · outro → ignorar.
