import JsBarcode from 'jsbarcode';

// Gera o código de barras (Code 128) de cada etiqueta. O texto legível fica abaixo das barras,
// permitindo digitar o código se a leitura falhar (UC-ACE-003).
document.querySelectorAll('svg[data-barcode]').forEach((svg) => {
    try {
        JsBarcode(svg, svg.dataset.barcode, {
            format: 'CODE128',
            width: 2,
            height: 44,
            margin: 6,
            displayValue: true,
            fontSize: 14,
            textMargin: 2,
        });
    } catch {
        svg.insertAdjacentText('afterend', 'Não foi possível gerar o código de barras.');
    }
});

document.querySelector('[data-print]')?.addEventListener('click', () => window.print());
