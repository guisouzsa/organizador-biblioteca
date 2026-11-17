function readableLabel(field) {
    return field.labels?.[0]?.textContent.replace('*', '').trim() || field.name;
}

function isbnIsValid(value) {
    const isbn = value.toUpperCase().replace(/[^0-9X]/g, '');
    if (isbn.length === 13) {
        const sum = [...isbn].reduce((total, digit, index) => total + Number(digit) * (index % 2 ? 3 : 1), 0);
        return sum % 10 === 0;
    }
    if (isbn.length === 10) {
        const sum = [...isbn].reduce((total, digit, index) => total + (digit === 'X' ? 10 : Number(digit)) * (10 - index), 0);
        return sum % 11 === 0;
    }
    return value.trim() === '';
}

function validationMessage(field) {
    if (field.hasAttribute('data-isbn') && !isbnIsValid(field.value)) return 'Informe um ISBN-10 ou ISBN-13 válido.';
    if (field.validity.valueMissing) return `${readableLabel(field)} é obrigatório.`;
    if (field.validity.typeMismatch && field.type === 'email') return 'Informe um endereço de e-mail válido.';
    if (field.validity.typeMismatch) return `Confira o formato de ${readableLabel(field).toLowerCase()}.`;
    if (field.validity.rangeUnderflow || field.validity.rangeOverflow) return `O valor de ${readableLabel(field).toLowerCase()} está fora do limite permitido.`;
    if (field.validity.patternMismatch) return `Confira o formato de ${readableLabel(field).toLowerCase()}.`;
    return field.validationMessage || 'Confira este campo.';
}

function setFieldError(field, show) {
    const id = `${field.id || field.name}-client-error`;
    let message = field.parentElement?.parentElement?.querySelector(`#${CSS.escape(id)}`);
    if (!show) {
        field.removeAttribute('aria-invalid');
        message?.remove();
        return;
    }
    field.setAttribute('aria-invalid', 'true');
    if (!message) {
        message = document.createElement('p');
        message.id = id;
        message.className = 'field-error field-error--client';
        message.setAttribute('role', 'alert');
        const passwordWrapper = field.closest('.password-field');
        if (passwordWrapper) passwordWrapper.after(message); else field.after(message);
    }
    message.textContent = validationMessage(field);
}

function fieldIsValid(field) {
    if (field.hasAttribute('data-isbn')) {
        field.setCustomValidity(isbnIsValid(field.value) ? '' : 'Informe um ISBN válido.');
    }
    return field.checkValidity() && (!field.hasAttribute('data-isbn') || isbnIsValid(field.value));
}

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.noValidate) return;
    const invalid = [...form.elements].filter((field) => typeof field.checkValidity === 'function' && !fieldIsValid(field));
    if (!invalid.length) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    invalid.forEach((field) => setFieldError(field, true));
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
        if (fieldIsValid(field)) setFieldError(field, false);
    }
});

document.addEventListener('focusout', (event) => {
    const field = event.target;
    if ((field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement) && field.value !== '') {
        setFieldError(field, !fieldIsValid(field));
    }
});
