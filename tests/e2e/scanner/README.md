# Testes de navegador do scanner (Chrome + câmera virtual)

Estes scripts usam um Chrome real com **câmera virtual** alimentada por um vídeo contendo um código de
barras EAN-13. Eles validam o ZXing, os estados da tela e o encerramento da câmera.

> **Não substituem o teste em um celular/computador com câmera física** (foco, luz, reflexo, desempenho
> do aparelho). Veja `docs/validation-report.md` para o roteiro manual.

## Como executar

Dependências só para estes testes (não entram no `package.json` do projeto):

```bash
cd tests/e2e/scanner
npm init -y && npm i puppeteer-core bwip-js
node gen.mjs                       # gera isbn.png e ean.png
ffmpeg -y -loop 1 -i isbn.png -t 3 -vf "scale=640:480:force_original_aspect_ratio=decrease,pad=640:480:(ow-iw)/2:(oh-ih)/2:white,format=yuv420p" -r 15 isbn.y4m
ffmpeg -y -loop 1 -i ean.png  -t 3 -vf "scale=640:480:force_original_aspect_ratio=decrease,pad=640:480:(ow-iw)/2:(oh-ih)/2:white,format=yuv420p" -r 15 ean.y4m
```

Com o app rodando (`npm run build` e `php artisan serve --no-reload --port=8765`, usando um banco **de teste**):

```bash
node scan-test.mjs    # câmera: leitura, duplicidade, permissões, aba, saída da página, seleção
node ui-test.mjs      # rolagem horizontal, h1, alt, rótulos e alvos de toque em 3 tamanhos de tela
```

Variáveis: `CHROME_PATH` (caminho do Chrome) e `BASE_URL`. A consulta por ISBN usa as APIs reais
(Open Library; o Google Books exige `GOOGLE_BOOKS_API_KEY`).
