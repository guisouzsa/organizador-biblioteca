# Auditoria do catálogo bibliográfico universal

Data da auditoria: 2026-11-16. Esta documentação descreve o código efetivamente inspecionado e separa implementação, configuração, teste e recomendação.

## Estado atual

O sistema é Laravel 12/PHP 8.2, Blade, Tailwind/Vite e PostgreSQL/Supabase. O catálogo já distingue o registro bibliográfico (`books`) do exemplar físico (`book_copies`). Circulação, reservas, leitores, permissões, auditoria e etiquetas já estão implementados. O scanner usa ZXing no navegador, mas envia o ISBN ao Laravel; nenhuma chave externa é exposta no frontend.

Uma evolução parcial já estava no worktree antes desta auditoria: `BookWork`, grupo escolar/geral, tipo de material, público, etapa de ensino, categorias hierárquicas e filtros. A migration `2026_11_18_000001_evolve_catalog_to_universal_bibliography.php` está **pendente** no banco configurado e não foi executada.

### Implementado

- ISBN-10/ISBN-13: limpeza, dígito verificador, conversão 10→13 e rejeição de EAN não ISBN.
- Ordem da consulta: catálogo local → Google Books → Open Library.
- Cadastro manual sem ISBN e detecção assistida de semelhantes sem ISBN.
- Comparação de campos conflitantes e revisão humana antes de salvar.
- Procedência em `book_sources`, com identificador, URL, data, JSON bruto e estado de verificação.
- Obra (`book_works`, código/migration pendente), edição (`books`) e exemplar (`book_copies`).
- Classificações múltiplas pela pivot `book_category`; `categories.type` separa dimensões.
- Capas remotas validadas como HTTP(S), fallback visual e associação à edição.

### Parcial ou ausente

- Provedores são classes concretas injetadas diretamente; ainda não há contrato/registro extensível.
- Consulta externa só por ISBN; não há busca externa por título, autor, editora ou assunto.
- Não há fotografia de capa/página de créditos, OCR ou avaliação de confiança.
- `authors` ainda é texto delimitado, não entidade normalizada.
- `book_sources` tem unicidade por livro+provedor, insuficiente para múltiplos registros do mesmo provedor.
- Cache de 30 minutos guarda apenas o resultado temporário por token; não há cache por consulta/ISBN.
- Open Library faz até quatro chamadas adicionais sequenciais para autores e não envia `User-Agent` identificável.
- A migration universal não foi aplicada; telas que consultem suas colunas dependem dela.
- Há arquivos antigos com texto mojibake; isso é defeito de codificação do repositório, não idioma da aplicação.

## Inventário das integrações existentes

### Google Books

- Responsável: Google. URL oficial: https://developers.google.com/books
- Endpoint usado: `GET https://www.googleapis.com/books/v1/volumes`.
- Código: `app/Services/Catalog/GoogleBooksService.php::findByIsbn`.
- Parâmetros: `q=isbn:{isbn13}`, `maxResults=5` e `key` quando configurada.
- Configuração: `GOOGLE_BOOKS_API_KEY` opcional no código, `GOOGLE_BOOKS_BASE_URL` e `GOOGLE_BOOKS_TIMEOUT` aceitos por `config/services.php` (os dois últimos não estavam documentados no `.env.example`).
- Execução: servidor Laravel, timeout padrão de 8 s; trata conexão, 429, demais HTTP e JSON inválido. Não faz retry.
- Dados aproveitados: id externo, URL, título, subtítulo, autores, editora, ano, ISBN-10/13, páginas, descrição, categorias, capa e idioma. Edição não é fornecida pelo normalizador.
- Capa: `volumeInfo.imageLinks.thumbnail`/`smallThumbnail`, forçada para HTTPS.
- Cache: somente token temporário do merge, 30 min; a resposta da API não é cacheada por ISBN.
- Estado real em 2026-11-16: implementada e coberta por testes simulados; requisição real respondeu HTTP 429 `RESOURCE_EXHAUSTED`. Não operacional neste ambiente sem corrigir a cota/chave.

### Open Library

- Responsável: Internet Archive/Open Library. URL oficial: https://openlibrary.org/developers/api
- Endpoints usados: `GET /isbn/{isbn13}.json` e, para até quatro autores, `GET /authors/{olid}.json`.
- Código: `app/Services/Catalog/OpenLibraryService.php::findByIsbn` e `authors`.
- Autenticação: não exige chave. Configuração: `OPEN_LIBRARY_BASE_URL`, `OPEN_LIBRARY_COVERS_URL`, `OPEN_LIBRARY_TIMEOUT` (ainda ausentes do `.env.example`).
- Execução: servidor Laravel, timeout padrão de 8 s; trata 404, 429, falhas HTTP/JSON e falha parcial de autor.
- Dados aproveitados: OLID da edição, URL, título, subtítulo, autores, editora, ano, ISBN, edição, páginas, descrição, assuntos, idioma e capa por cover id.
- Capa: `https://covers.openlibrary.org/b/id/{id}-L.jpg`.
- Cache: não há cache por ISBN.
- Estado real em 2026-11-16: implementada, testes simulados aprovados e consulta real HTTP 200 para `9788535914849`, retornando edição em português. A política oficial pede identificação do cliente e limita 1 req/s sem identificação ou 3 req/s identificado; o código atual precisa desse cabeçalho.

Não existe no código integração com Biblioteca Nacional, ISBN Brasil, WorldCat, catálogos universitários, OCR ou Supabase Storage de capas.

## Persistência atual

`books` armazena edição: título, subtítulo, autores, editora, ISBNs, edição, ano, páginas, descrição, URL de capa, idioma, formato e coleção. `book_copies` armazena cada cópia física, tombo, localização, condição, aquisição e estado. `book_sources` armazena a origem e o JSON bruto. `categories` + `book_category` permitem múltiplas classificações.

A migration pendente adiciona `book_works`, `books.work_id`, `collection_group`, `material_type`, `audience`, `education_stage` e hierarquia `categories.parent_id`. É aditiva e reversível; não apaga dados. Livros antigos receberiam `collection_group=general`, decisão segura tecnicamente, mas que exige revisão catalográfica posterior.

## Testes executados

- Suíte: 96 testes executados; todos passaram. O gravador de cache do PHPUnit falhou depois da suíte por restrição do diretório do executor.
- Integrações isoladas: 30 testes, 141 asserções, todos aprovados com HTTP simulado.
- Banco: `migrate:status` somente leitura; migration universal pendente. Nenhuma migration ou escrita remota foi executada.
- API real: Open Library HTTP 200; Google Books HTTP 429.

## Fontes adicionais avaliadas

- Biblioteca Nacional do Brasil: há catálogo e iniciativa de dados abertos, mas nesta pesquisa não foi localizada documentação oficial de API bibliográfica pública estável adequada a consultas transacionais. Não integrar nem fazer scraping sem autorização/documentação.
- Agência Brasileira do ISBN/CBL: serviço oficial de registro/consulta, mas não foi confirmada API pública gratuita e documentada. Tratar como consulta manual/comercial até haver contrato técnico.
- WorldCat/OCLC: APIs documentadas, porém exigem conta institucional, WSKey/OAuth e autorização da OCLC; Search API v1 foi descontinuada em 2024/2025. É tecnicamente integrável via v2 somente para instituição elegível, não como fallback público imediato.
- Catálogos universitários: variam entre SRU/Z39.50/OAI-PMH e interfaces sem API. Só devem virar provedores após validar licença, estabilidade e cobertura; OAI-PMH costuma descrever repositórios, não ser um catálogo geral de ISBN.

## Arquitetura recomendada

Criar um contrato `BibliographicProvider` com nome, capacidades e métodos de busca, e um orquestrador que receba uma coleção configurada de provedores. Resultados devem usar o mesmo DTO, conservar identificadores/origem e ser ranqueados por: catálogo local, ISBN exato, identificador de edição, título+autor e, por último, sugestão de OCR. Português recebe bônus de ranking, nunca filtro excludente.

OCR deve ser uma etapa assistiva separada: imagem → extração → termos candidatos → busca → revisão humana. Resultado de OCR nunca deve persistir automaticamente.

## Referências oficiais consultadas

- Google Books API: https://developers.google.com/books/docs/v1/using
- Google Books Volumes: https://developers.google.com/books/docs/v1/reference/volumes
- Open Library APIs e limites: https://openlibrary.org/developers/api
- OCLC Developer FAQ/WSKey: https://www.oclc.org/developer/support/faq.en.html
- Transição WorldCat Search API: https://www.oclc.org/developer/support/worldcat-search-transition.en.html
