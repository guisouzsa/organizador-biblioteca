/* Formulários de criação/edição em modal. As páginas e rotas continuam funcionando sem JS. */
const dialog = document.getElementById('form-dialog');
const content = document.getElementById('form-dialog-content');
const title = document.getElementById('form-dialog-title');
const footer = document.getElementById('form-dialog-actions');
let sourceUrl = null;
let trigger = null;
let dirty = false;
let inlineHome = null;
let inlineForm = null;
let inlineActions = null;
let submitting = false;

function contentReady() {
    document.dispatchEvent(new CustomEvent('ui:content-ready', { detail: { root: content } }));
    const form = content.querySelector('form[data-modal-form]');
    const actions = form?.querySelector('[data-form-actions]');
    if (form && actions && footer) {
        if (!form.id) form.id = `modal-form-${Date.now()}`;
        actions.querySelectorAll('button').forEach((button) => button.setAttribute('form', form.id));
        footer.replaceChildren(...actions.children);
        footer.hidden = false;
        if (form === inlineForm) inlineActions = actions;
        actions.remove();
    }
}

function isFormPage(url) {
    const path = new URL(url, location.href).pathname;
    return /\/(novo|editar)$/.test(path) || /\/exemplares\/novo$/.test(path);
}

function prepare(fragment, responseUrl) {
    fragment.querySelectorAll('#error-summary').forEach((el) => el.remove());
    fragment.querySelectorAll('a').forEach((link) => {
        if (link.textContent.trim().toLowerCase() === 'cancelar') {
            link.href = '#';
            link.dataset.formDialogClose = '';
        }
    });
    const form = fragment.querySelector('form');
    if (form) {
        form.dataset.modalForm = '1';
        form.addEventListener('input', () => { dirty = true; });
    }
    sourceUrl = responseUrl;
    return fragment;
}

function extract(html, responseUrl) {
    const doc = new DOMParser().parseFromString(html, 'text/html');
    const main = doc.querySelector('main');
    const pageTitle = doc.querySelector('h1')?.textContent?.trim() || doc.title.split('—')[0].trim() || 'Formulário';
    const wrapper = document.createElement('div');
    wrapper.innerHTML = main?.innerHTML ?? '<p role="alert">Não foi possível carregar o formulário.</p>';
    return { fragment: prepare(wrapper, responseUrl), pageTitle };
}

async function load(url) {
    content.innerHTML = '<p class="form-dialog__loading" role="status">Carregando formulário…</p>';
    const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' }, credentials: 'same-origin' });
    if (!response.ok) throw new Error('Não foi possível carregar o formulário.');
    const result = extract(await response.text(), response.url);
    title.textContent = result.pageTitle;
    content.replaceChildren(result.fragment);
    contentReady();
    content.querySelector('[aria-invalid="true"], input:not([type=hidden]), select, textarea')?.focus({ preventScroll: true });
}

async function open(url, opener) {
    if (!dialog) return;
    trigger = opener;
    dirty = false;
    title.textContent = 'Carregando…';
    dialog.showModal();
    document.documentElement.classList.add('modal-open');
    try { await load(url); } catch (error) {
        content.innerHTML = `<p role="alert" class="form-dialog__loading">${error.message}</p>`;
    }
}

function openInline(form, opener) {
    trigger = opener;
    dirty = false;
    inlineForm = form;
    inlineHome = form.parentElement;
    title.textContent = form.dataset.modalTitle || 'Preencher formulário';
    form.hidden = false;
    form.dataset.modalForm = '1';
    sourceUrl = location.href;
    content.replaceChildren(form);
    contentReady();
    dialog.showModal();
    document.documentElement.classList.add('modal-open');
    form.querySelector('[aria-invalid="true"], input:not([type=hidden]), select, textarea')?.focus({ preventScroll: true });
}

function close() {
    dialog?.close();
}

async function requestClose() {
    if (!dirty || await window.UI?.confirm({
        title: 'Descartar alterações?',
        message: 'Os dados preenchidos ainda não foram salvos.',
        confirmLabel: 'Descartar',
        cancelLabel: 'Continuar editando',
        danger: true,
    })) close();
}

document.addEventListener('click', (event) => {
    const closeButton = event.target.closest('[data-form-dialog-close]');
    if (closeButton && dialog?.open) {
        event.preventDefault();
        requestClose();
        return;
    }
    const link = event.target.closest('a[href]');
    if (!link || link.target || link.hasAttribute('download') || !isFormPage(link.href)) return;
    event.preventDefault();
    open(link.href, link);
});

document.querySelectorAll('form[data-inline-modal]').forEach((form) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = form.dataset.modalButtonClass || 'btn btn-primary mb-6';
    button.textContent = form.dataset.modalTrigger || 'Abrir formulário';
    button.addEventListener('click', () => openInline(form, button));
    form.before(button);
    form.hidden = true;
});

dialog?.addEventListener('cancel', (event) => {
    event.preventDefault();
    requestClose();
});
dialog?.addEventListener('close', () => {
    document.documentElement.classList.remove('modal-open');
    if (inlineForm && inlineHome && document.contains(inlineHome)) {
        if (inlineActions && footer) {
            inlineActions.append(...footer.children);
            inlineForm.append(inlineActions);
        }
        inlineForm.hidden = true;
        inlineForm.removeAttribute('data-modal-form');
        inlineHome.append(inlineForm);
    }
    content.replaceChildren();
    footer?.replaceChildren();
    if (footer) footer.hidden = true;
    trigger?.focus?.({ preventScroll: true });
    trigger = null; dirty = false; inlineHome = null; inlineForm = null; inlineActions = null;
});

content?.addEventListener('submit', async (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.matches('[data-modal-form]')) return;
    event.preventDefault();
    if (!form.reportValidity()) return;
    if (submitting) return;
    submitting = true;
    const submitter = event.submitter;
    submitter?.setAttribute('aria-busy', 'true');
    submitter && (submitter.disabled = true);
    try {
        const response = await fetch(form.action, {
            method: form.method, body: new FormData(form), credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
        });
        const html = await response.text();
        if (isFormPage(response.url) || response.url === sourceUrl) {
            const result = extract(html, response.url);
            title.textContent = result.pageTitle;
            inlineForm = null;
            inlineHome = null;
            content.replaceChildren(result.fragment);
            contentReady();
            dirty = true;
            content.querySelector('[aria-invalid="true"], [role="alert"]')?.focus?.();
        } else {
            dirty = false;
            close();
            location.assign(response.url);
        }
    } catch {
        window.UI?.notify('Não foi possível salvar. Confira sua conexão e tente novamente.', 'error');
        submitter?.removeAttribute('aria-busy');
        if (submitter) submitter.disabled = false;
    } finally {
        submitting = false;
    }
});
