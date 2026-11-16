import './bootstrap';

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

// Avisos de sucesso somem sozinhos (erros permanecem até serem lidos).
document.querySelectorAll('[data-autohide]').forEach((el) => {
    setTimeout(() => {
        el.style.transition = 'opacity 300ms';
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 320);
    }, 6000);
});

// Aviso curto programático (ex.: leitura de código): window.notify('texto', 'ok' | 'error')
window.notify = (message, kind = 'ok') => {
    const region = document.getElementById('toast-region');
    if (!region) {
        return;
    }
    const el = document.createElement('div');
    el.className = 'pointer-events-auto max-w-sm rounded-md px-4 py-2.5 text-sm font-medium shadow-lg '
        + (kind === 'error' ? 'bg-rose-700 text-white' : 'bg-slate-900 text-white');
    el.textContent = message;
    region.appendChild(el);
    setTimeout(() => el.remove(), 3500);
};

// Evita envio duplo de formulários (empréstimos, cadastros): desabilita o botão após o envio.
document.querySelectorAll('form[data-once]').forEach((form) => {
    form.addEventListener('submit', () => {
        form.querySelectorAll('button[type=submit]').forEach((button) => {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
        });
    });
});
