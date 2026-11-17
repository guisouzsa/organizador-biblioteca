function readableLabel(field) {
    return field.labels?.[0]?.textContent.replace('*', '').trim() || field.name;
}

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.noValidate) return;
    const invalid = [...form.elements].filter((field) => typeof field.checkValidity === 'function' && !field.checkValidity());
    if (!invalid.length) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    invalid.forEach((field) => field.setAttribute('aria-invalid', 'true'));
    const first = invalid[0];
    window.UI?.show({
        tone: 'error',
        title: 'Revise o formulário',
        message: `Preencha corretamente: ${[...new Set(invalid.map(readableLabel))].join(', ')}.`,
    }).then(() => first.focus({ preventScroll: false }));
}, true);

document.addEventListener('input', (event) => {
    const field = event.target;
    if (field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement) {
        if (field.checkValidity()) field.removeAttribute('aria-invalid');
    }
});
