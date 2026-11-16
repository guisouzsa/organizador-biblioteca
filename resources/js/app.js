import './bootstrap';

// Menu lateral (celular/tablet): abre/fecha, fecha com Esc, clique fora ou ao navegar.
const sidebar = document.getElementById('sidebar');
const toggle = document.querySelector('[data-sidebar-toggle]');
const backdrop = document.querySelector('[data-sidebar-backdrop]');

function setSidebar(open) {
    if (!sidebar || !toggle) {
        return;
    }
    sidebar.dataset.open = String(open);
    toggle.setAttribute('aria-expanded', String(open));
    backdrop?.classList.toggle('hidden', !open);
}

toggle?.addEventListener('click', () => setSidebar(sidebar.dataset.open !== 'true'));
backdrop?.addEventListener('click', () => setSidebar(false));
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        setSidebar(false);
    }
});
sidebar?.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setSidebar(false)));

// Evita envio duplo de formulários (empréstimos, cadastros): desabilita o botão após o envio.
document.querySelectorAll('form[data-once]').forEach((form) => {
    form.addEventListener('submit', () => {
        form.querySelectorAll('button[type=submit]').forEach((button) => {
            button.disabled = true;
            button.classList.add('opacity-60');
        });
    });
});
