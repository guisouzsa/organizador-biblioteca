import './bootstrap';
import './ui/dialogs';
import './ui/select';
import './ui/form-dialog';

// Painel lateral: coluna fixa no desktop; no celular abre pelo botão "Mais" da barra inferior.
const sidebar = document.getElementById('sidebar');
const toggles = document.querySelectorAll('[data-sidebar-toggle]');
const backdrop = document.querySelector('[data-sidebar-backdrop]');
let lastFocus = null;

function setSidebar(open) {
    if (!sidebar) {
        return;
    }
    sidebar.dataset.open = String(open);
    toggles.forEach((t) => t.setAttribute('aria-expanded', String(open)));
    backdrop?.classList.toggle('hidden', !open);
    document.body.classList.toggle('overflow-hidden', open && window.innerWidth < 1024);
    if (open) {
        lastFocus = document.activeElement;
        sidebar.querySelector('a, button')?.focus();
    } else if (lastFocus) {
        lastFocus.focus?.();
        lastFocus = null;
    }
}

toggles.forEach((t) => t.addEventListener('click', () => setSidebar(sidebar.dataset.open !== 'true')));
document.querySelector('[data-sidebar-close]')?.addEventListener('click', () => setSidebar(false));
backdrop?.addEventListener('click', () => setSidebar(false));
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && sidebar?.dataset.open === 'true') {
        setSidebar(false);
    }
});
sidebar?.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setSidebar(false)));

// Tabelas viram listas de fichas no celular: copia o texto do cabeçalho para cada célula (data-label).
document.querySelectorAll('main table').forEach((table) => {
    const heads = [...table.querySelectorAll('thead th')].map((th) => th.textContent.trim());
    if (!heads.length) {
        return;
    }
    table.querySelectorAll('tbody tr').forEach((row) => {
        [...row.children].forEach((cell, i) => {
            if (cell.tagName === 'TD' && !cell.hasAttribute('data-label')) {
                cell.setAttribute('data-label', heads[i] ?? '');
            }
        });
    });
});

// Atalho de pesquisa no cabeçalho do celular: chega ao catálogo com o campo de busca em foco.
if (location.hash === '#q') {
    document.getElementById('q')?.focus();
}

// Filtros recolhíveis: abertos no desktop, recolhidos no celular.
document.querySelectorAll('details[data-open-lg]').forEach((d) => {
    if (window.matchMedia('(min-width: 1024px)').matches) {
        d.open = true;
    }
});

// Capas: se a imagem falhar ao carregar, troca por um estado alternativo discreto (nunca uma capa inventada).
function coverFallback(img) {
    if (img.dataset.failed) {
        return;
    }
    img.dataset.failed = '1';
    const box = document.createElement('div');
    box.className = img.className.replace('cover', '').trim() + ' cover cover--empty';
    box.setAttribute('role', 'img');
    box.setAttribute('aria-label', 'Capa indisponível');
    box.innerHTML = '<span class="cover__label">Capa indisponível</span>';
    img.replaceWith(box);
}
document.addEventListener('error', (event) => {
    if (event.target instanceof HTMLImageElement && event.target.matches('img.cover')) {
        coverFallback(event.target);
    }
}, true);
document.querySelectorAll('img.cover').forEach((img) => {
    if (img.complete && img.naturalWidth === 0) {
        coverFallback(img);
    }
});

// Busca no catálogo: mostra o indicador de carregamento enquanto a página responde.
document.querySelectorAll('form[data-loading]').forEach((form) => {
    form.addEventListener('submit', () => {
        document.querySelector('[data-loading-indicator]')?.removeAttribute('hidden');
    });
});

// Evita envio duplo de formulários (empréstimos, cadastros): desabilita o botão após o envio.
document.querySelectorAll('form[data-once]').forEach((form) => {
    form.addEventListener('submit', () => {
        form.querySelectorAll('button[type=submit]').forEach((button) => {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
        });
    });
});

// Cadastro bibliográfico: realça os campos próprios do acervo escolar sem esconder informação.
document.querySelectorAll('[data-book-form]').forEach((form) => {
    const group = form.querySelector('[data-collection-group]');
    const schoolFields = form.querySelectorAll('[data-school-field]');
    const update = () => {
        const school = group?.value === 'school';
        schoolFields.forEach((field) => {
            field.closest('div')?.classList.toggle('book-field--contextual', school);
            field.setAttribute('aria-disabled', String(!school));
        });
    };
    group?.addEventListener('change', update);
    update();
});
