function initializePasswordToggles(root = document) {
    root.querySelectorAll('[data-password-toggle]').forEach((button) => {
        if (button.dataset.ready === 'true') return;
        const input = document.getElementById(button.getAttribute('aria-controls'));
        if (!(input instanceof HTMLInputElement)) return;

        button.dataset.ready = 'true';
        button.addEventListener('click', () => {
            const showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            button.setAttribute('aria-pressed', String(!showing));
            button.setAttribute('aria-label', `${showing ? 'Mostrar' : 'Ocultar'} ${input.name.replaceAll('_', ' ')}`);
            button.querySelector('[data-password-label]').textContent = showing ? 'Mostrar' : 'Ocultar';
            input.focus({ preventScroll: true });
            input.setSelectionRange(input.value.length, input.value.length);
        });
    });
}

initializePasswordToggles();
document.addEventListener('ui:content-ready', (event) => initializePasswordToggles(event.detail?.root ?? document));
