# Efeitos "gato-livro" — guia de uso

Tudo mora em `public/decor/` (CSS + SVGs) e `resources/views/components/shared/gato.blade.php`.
**Nenhum arquivo existente do site foi alterado.** Prévia: abra `/decor/preview.html` com o servidor rodando.

## Como ligar (1 linha)
Em `resources/views/layouts/app.blade.php` e `guest.blade.php`, dentro do `<head>`:
```html
<link rel="stylesheet" href="{{ asset('decor/efeitos.css') }}">
```

## Onde usar cada um
| # | Elemento | Classe / componente | Onde colocar no site |
|---|----------|--------------------|----------------------|
| 1 | Divisor com patinha | `<hr class="divisor-patas">` | Entre seções do `dashboard.blade.php` (antes de "Em atraso") e fim de páginas longas |
| 2 | Card com patinha ao passar/focar | `.efeito-patinha` | `components/shared/stat-card.blade.php` (cards de números) e cards clicáveis de listas |
| 3 | Link ondulado | `.link-ondulado` | Links de texto "Ver todos", "Ver reservas" no dashboard |
| 4 | Gatinho espiando | `.espia` + `<x-shared.gato nome="espiando" class="espia__gato">` | Topo do card de login (`layouts/guest.blade.php`) |
| 5 | Marcador de página | `.marca-pagina` | Card de destaque (ex.: "Prontas para retirada"), no máximo 1 por tela |
| 6 | Carregando (patinhas) | `<span class="carregando" role="status">…` | Troca de spinner em buscas/scanner (`resources/js/scanner.js`, `camera-input.js`) |
| 7 | Fundo de patinhas | `.fundo-patas` | Tela de login e áreas vazias. Nunca atrás de tabelas/texto |
| 8 | Gato dormindo / lápis-gato | `<x-shared.gato nome="dormindo">` | `components/shared/empty-state.blade.php` (estados vazios); lápis no cabeçalho/rodapé |

## Acessibilidade (decisões)
- Todo enfeite é decorativo: `alt=""` + `aria-hidden="true"`, nunca substitui texto.
- `prefers-reduced-motion`: animações e transições desligam.
- `forced-colors` (alto contraste) e impressão: fundos e fitas somem.
- Hover sempre acompanhado de `:focus-visible` (teclado). O anel de foco do site não é alterado.
- Os cinzas da paleta **não servem para texto**: `#808080` em fundo branco dá ~3,9:1 (abaixo de 4,5:1). Use só em formas/enfeites; texto continua nas cores atuais do site.
- Nenhum botão novo foi inventado; os `.btn` existentes seguem iguais.

## Paleta
Variáveis `--cinza-1…5` no topo de `efeitos.css` (e classes `.color1…5`). Para combinar com o musgo/terracota do site, basta trocar esses 5 valores; os SVGs trazem o cinza fixo (edite o `<style>` do topo de cada um).
