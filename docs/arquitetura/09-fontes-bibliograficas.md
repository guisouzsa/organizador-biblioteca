# Fontes bibliográficas e estratégia de identificação

Auditoria realizada em 17/11/2026. Uma página de pesquisa não é considerada API. O projeto não usa scraping nem endpoints não documentados.

## Fontes ativas

| Fonte | Uso | Autenticação | Cobertura e limites |
|---|---|---|---|
| Google Books API v1 | ISBN, título, autor, editora e palavras-chave; capas quando fornecidas | Chave de API recomendada para dados públicos | Cobertura ampla e variável. Resultado é uma edição/volume e sempre exige conferência humana. |
| Open Library Search/Books API | ISBN e busca textual; obras, edições e capas | Pública; User-Agent e contato configuráveis | Boa cobertura internacional, comunitária e potencialmente incompleta. Distingue Work e Edition. |
| Catálogo local | Primeira etapa de toda pesquisa | Sessão do sistema | Fonte prioritária; evita chamadas externas e duplicidade local. |
| Cadastro manual | Obras não encontradas ou sem ISBN | Sessão do sistema | Nenhum dado é inventado. A procedência fica registrada como manual. |

As chamadas externas idênticas são armazenadas em cache por uma hora, inclusive respostas sem correspondência. O token usado para confirmar e gravar a procedência continua válido por 30 minutos.

## Fontes brasileiras avaliadas

| Fonte | Resultado da avaliação | Decisão |
|---|---|---|
| Rede Bibliodata / IBICT | Catálogo coletivo com MARC21/AACR2. A documentação pública localizada descreve pesquisa e cooperação, mas não publica uma API aberta estável para consulta do aplicativo. | Não integrar sem documentação e autorização formal. |
| FNDE — Guias Digitais do PNLD | Guias públicos por ciclo e etapa. A íntegra das obras pode exigir Gov.br. Não foi localizada API pública documentada de metadados das obras para consumo livre. Os dados abertos do FNDE priorizam fornecimento/distribuição, não um catálogo bibliográfico universal. | Não raspar. Preparar futura importação somente a partir de conjunto oficial aberto ou API autorizada. |
| CBL — Agência Brasileira do ISBN | Serviço oficial de atribuição de ISBN. Não foi localizada documentação de API pública de consulta bibliográfica. | Não integrar nem automatizar a interface web. |
| Oasisbr / IBICT | Agregador de produção científica, não catálogo geral de livros. Usa infraestrutura de repositórios e metadados; não é adequado como fonte primária de obras escolares. | Não integrar ao fluxo geral de livros. |
| BDTD / IBICT | Teses e dissertações. Útil apenas para esse tipo documental, que não é o foco atual do modelo. | Não integrar ao fluxo geral. Avaliar quando o sistema modelar teses/dissertações. |
| Biblioteca Nacional | Há catálogo público, mas não foi encontrada, na documentação oficial consultada, uma API pública estável e claramente autorizada para este caso de uso. | Não inventar endpoint. Solicitar orientação técnica antes de integrar. |

## Fontes internacionais avaliadas

| Fonte | Resultado | Decisão |
|---|---|---|
| WorldCat / OCLC | A API consulta ISBN/OCLC e registros bibliográficos, porém exige assinatura institucional elegível e WSKey. A API Search 1.0 encerrou suporte em 31/12/2024 e está em retirada. | Implementar somente após a biblioteca fornecer contrato e credenciais para a API vigente. |
| Library of Congress | API JSON pública cobre principalmente itens digitais, não todo o catálogo. O catálogo completo oferece SRU/Z39.50, mas acrescentaria XML/MARC e cobertura pouco direcionada ao Brasil. | Candidata futura, não adicionada nesta etapa. |

## PNLD e materiais didáticos

PNLD é uma classificação de programa, não sinônimo de livro didático. Ciclo do PNLD, ano de publicação e edição são informações diferentes. Uma futura fonte PNLD deve fornecer explicitamente: código da obra, ciclo, etapa, componente curricular, ano/série, volume e tipo (estudante/professor), mantendo o ISBN e os dados da edição separados.

Até existir fonte oficial autorizada, esses campos devem ser confirmados pelo operador e registrados por coleção/categoria, sem inferência automática.

## Estratégia implementada

1. Pesquisar primeiro no catálogo local.
2. Validar e normalizar ISBN-10/ISBN-13.
3. Consultar Google Books e Open Library de forma isolada; falha de uma não bloqueia a outra.
4. Armazenar resultados em cache para evitar chamadas repetidas.
5. Deduplicar por ISBN ou título/autores normalizados.
6. Classificar por correspondência de ISBN, título, autor, editora e preferência por português.
7. Exibir divergências e procedência; nunca selecionar silenciosamente uma edição.
8. Exigir confirmação humana antes de gravar.
9. Manter OCR apenas como gerador de consulta; texto extraído nunca é salvo automaticamente como metadado.

## Documentação oficial consultada

- Google Books API: https://developers.google.com/books/docs/v1/using
- Google Books Volumes: https://developers.google.com/books/docs/v1/reference/volumes
- Open Library Search API: https://openlibrary.org/dev/docs/api/search
- Open Library Books/ISBN API: https://openlibrary.org/dev/docs/api/books
- FNDE Guias Digitais: https://www.fnde.gov.br/guialivro
- FNDE Dados Abertos: https://www.gov.br/fnde/pt-br/acesso-a-informacao/dados-abertos/dados-abertos-1/
- Rede Bibliodata: https://bibliodata.prd.ibict.br/catalogo/
- Rede Bibliodata FAQ: https://bibliodata.prd.ibict.br/faq/
- WorldCat Search API: https://www.oclc.org/developer/api/oclc-apis/worldcat-search-api.en.html
- Library of Congress APIs: https://www.loc.gov/apis
- Library of Congress SRU/Z39.50: https://www.loc.gov/standards/z3950/lcserver.html
