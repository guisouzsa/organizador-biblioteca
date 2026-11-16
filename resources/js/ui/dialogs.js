/*
 * Modais, confirmações e toasts — uma única implementação para o sistema inteiro.
 *
 * Usa o elemento nativo <dialog> com showModal(): o navegador cuida de prender o foco dentro do
 * modal, tornar o restante da página inerte e fechar com Esc. Aqui acrescentamos: foco inicial correto,
 * devolução do foco, bloqueio de rolagem, fila de modais, ações dinâmicas e prevenção de clique acidental
 * no fundo (clicar fora NÃO fecha).
 *
 * API global (window.UI), usada também pelo scanner:
 *   UI.show({ tone, title, message, actions, persistent, focus }) → Promise<valor da ação>
 *   UI.confirm({ title, message, confirmLabel, cancelLabel, danger }) → Promise<boolean>
 *   UI.notify(texto, 'ok' | 'error')
 *
 * Tons: success | info | warning | error | denied | confirm.
 * Ação: { label, value?, href?, variant?: 'primary' | 'secondary' | 'danger', autofocus?: bool }
 */

const dialog = document.getElementById('ui-dialog');
const iconTemplate = document.getElementById('ui-icons');

let queue = Promise.resolve();

function iconFor(tone) {
    const source = iconTemplate?.content.querySelector(`[data-icon="${tone}"]`);
    return source ? source.firstElementChild.cloneNode(true) : null;
}

function setScrollLock(locked) {
    document.documentElement.classList.toggle('modal-open', locked);
}

function buildAction(action, fallbackValue) {
    const el = document.createElement(action.href ? 'a' : 'button');
    el.className = action.variant === 'danger' ? 'btn btn-danger-solid' : `btn btn-${action.variant ?? 'secondary'}`;
    el.textContent = action.label;
    if (action.href) {
        el.href = action.href;
    } else {
        el.type = 'button';
        el.dataset.value = String(action.value ?? fallbackValue);
    }
    if (action.autofocus) {
        el.setAttribute('autofocus', '');
    }
    return el;
}

function show(options) {
    if (!dialog) {
        return Promise.resolve('dismiss');
    }
    const run = () => new Promise((resolve) => {
        const {
            tone = 'info', title = '', message = '', actions = null, persistent = false, focus = null, extra = null,
        } = options;
        const trigger = document.activeElement;

        dialog.dataset.tone = tone;
        dialog.dataset.persistent = persistent ? 'true' : 'false';
        const icon = document.getElementById('ui-dialog-icon');
        icon.replaceChildren(...[iconFor(tone)].filter(Boolean));
        document.getElementById('ui-dialog-title').textContent = title;
        document.getElementById('ui-dialog-message').textContent = message;
        const extraBox = document.getElementById('ui-dialog-extra');
        extraBox.replaceChildren(...(extra ? [extra] : []));

        const list = actions && actions.length
            ? actions
            : [{ label: tone === 'success' ? 'Continuar' : 'Entendi', value: 'ok', variant: 'primary' }];
        const foot = document.getElementById('ui-dialog-actions');
        foot.replaceChildren();
        // Botão principal por último (à direita, mais próximo do polegar); os secundários antes.
        const primary = list.filter((a) => (a.variant ?? 'secondary') !== 'secondary');
        const secondary = list.filter((a) => (a.variant ?? 'secondary') === 'secondary');
        [...secondary, ...primary].forEach((action, index) => foot.append(buildAction(action, index)));
        if (!foot.querySelector('[autofocus]')) {
            // Padrão: foco no botão principal; em ações destrutivas o foco inicial é o "Cancelar".
            const preferred = tone === 'confirm' ? foot.querySelector('.btn-secondary') : foot.querySelector('.btn-primary');
            (preferred ?? foot.firstElementChild)?.setAttribute('autofocus', '');
        }

        const finish = () => {
            dialog.removeEventListener('close', finish);
            foot.removeEventListener('click', onClick);
            dialog.removeEventListener('cancel', onCancel);
            setScrollLock(false);
            const target = focus ? document.querySelector(focus) : null;
            const back = target ?? (trigger && document.contains(trigger) ? trigger : null);
            back?.focus?.({ preventScroll: true });
            resolve(dialog.returnValue || 'dismiss');
        };
        const onClick = (event) => {
            const button = event.target.closest('button[data-value]');
            if (button) {
                dialog.close(button.dataset.value);
            }
        };
        const onCancel = (event) => {
            if (persistent) {
                event.preventDefault(); // Esc não fecha avisos que exigem decisão
            }
        };

        dialog.returnValue = '';
        foot.addEventListener('click', onClick);
        dialog.addEventListener('cancel', onCancel);
        dialog.addEventListener('close', finish);
        setScrollLock(true);
        dialog.showModal();
    });

    // Um modal por vez: o seguinte espera o anterior ser fechado.
    queue = queue.then(run, run);
    return queue;
}

function confirmDialog({
    title, message, confirmLabel = 'Confirmar', cancelLabel = 'Cancelar', danger = false, detail = null,
}) {
    let extra = null;
    if (detail) {
        extra = document.createElement('p');
        extra.className = 'modal__detail';
        extra.textContent = detail;
    }
    return show({
        tone: 'confirm',
        title,
        message,
        extra,
        actions: [
            { label: cancelLabel, value: 'cancel', variant: 'secondary' },
            { label: confirmLabel, value: 'confirm', variant: danger ? 'danger' : 'primary' },
        ],
    }).then((value) => value === 'confirm');
}

function notify(message, kind = 'ok') {
    const region = document.getElementById('toast-region');
    if (!region || !message) {
        return;
    }
    const el = document.createElement('div');
    el.className = `toast toast--${kind === 'error' ? 'error' : 'ok'}`;
    el.setAttribute('role', kind === 'error' ? 'alert' : 'status');
    const text = document.createElement('span');
    text.textContent = message;
    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'toast__close';
    close.setAttribute('aria-label', 'Fechar aviso');
    close.textContent = '×';
    close.addEventListener('click', () => el.remove());
    el.append(text, close);
    region.appendChild(el);
    setTimeout(() => el.remove(), kind === 'error' ? 8000 : 5000);
}

window.UI = { show, confirm: confirmDialog, notify };
window.notify = notify; // compatibilidade

// ---- Dados enviados pelo servidor (flash) ----
try {
    const flash = JSON.parse(document.getElementById('ui-flash')?.textContent || '{}');
    if (flash.toast) {
        notify(flash.toast);
    }
    if (flash.modal) {
        const { tone, title, message, actions, focus } = flash.modal;
        show({
            tone,
            title,
            message,
            focus,
            actions: (actions ?? []).map((a) => ({ ...a, variant: a.variant ?? 'secondary' }))
                .concat([{ label: tone === 'success' ? 'Continuar' : 'Entendi', value: 'ok', variant: 'primary' }]),
        });
    }
} catch {
    /* sem dados de flash */
}

// ---- Confirmação declarativa: <form data-confirm="Mensagem" data-confirm-title="…" data-confirm-label="…" data-confirm-danger> ----
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm') || form.dataset.confirmed === '1') {
        return;
    }
    event.preventDefault();
    event.stopImmediatePropagation();
    const submitter = event.submitter ?? null;
    confirmDialog({
        title: form.dataset.confirmTitle || 'Confirmar ação',
        message: form.dataset.confirm,
        detail: form.dataset.confirmDetail || null,
        confirmLabel: form.dataset.confirmLabel || 'Confirmar',
        cancelLabel: form.dataset.confirmCancel || 'Cancelar',
        danger: form.hasAttribute('data-confirm-danger'),
    }).then((ok) => {
        if (ok) {
            form.dataset.confirmed = '1';
            form.requestSubmit(submitter);
        }
    });
}, true);

// ---- Descartar alterações: formulários com data-dirty-guard + links [data-discard] ----
document.querySelectorAll('form[data-dirty-guard]').forEach((form) => {
    let dirty = false;
    form.addEventListener('input', () => { dirty = true; });
    form.addEventListener('submit', () => { dirty = false; });
    form.querySelectorAll('[data-discard]').forEach((link) => {
        link.addEventListener('click', (event) => {
            if (!dirty) {
                return;
            }
            event.preventDefault();
            confirmDialog({
                title: 'Descartar alterações?',
                message: 'Você preencheu dados que ainda não foram salvos. Se sair agora, eles serão perdidos.',
                confirmLabel: 'Descartar',
                cancelLabel: 'Continuar editando',
                danger: true,
            }).then((ok) => {
                if (ok) {
                    window.location.href = link.href;
                }
            });
        });
    });
});

// ---- Validação: leva o foco ao primeiro problema ----
const summary = document.getElementById('error-summary');
if (summary) {
    summary.focus({ preventScroll: false });
} else {
    document.querySelector('[aria-invalid="true"]')?.focus?.({ preventScroll: false });
}
