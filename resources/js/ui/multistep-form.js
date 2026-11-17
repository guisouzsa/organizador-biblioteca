function fieldLabel(field) {
    if (!field.id) return field.name;
    return field.form?.querySelector(`label[for="${CSS.escape(field.id)}"]`)?.textContent.replace('*', '').trim() || field.name;
}

function initializeMultistep(root = document) {
    root.querySelectorAll('form[data-multistep-form]').forEach((form) => {
        if (form.dataset.multistepReady) return;
        const steps = [...form.querySelectorAll(':scope > fieldset[data-step]')];
        const actions = form.querySelector('[data-form-actions]');
        const submit = actions?.querySelector('button[type="submit"]');
        const cancel = actions?.querySelector('[data-discard]');
        if (steps.length < 3 || !actions || !submit) return;
        form.dataset.multistepReady = 'true';

        const progress = document.createElement('div');
        progress.className = 'form-steps';
        progress.setAttribute('aria-label', 'Progresso do formulário');
        const progressList = document.createElement('ol');
        progress.append(progressList);
        form.insertBefore(progress, steps[0]);

        const review = document.createElement('section');
        review.className = 'form-review';
        review.dataset.stepPanel = 'review';
        review.innerHTML = '<p class="eyebrow">Revisão</p><h3>Confira antes de salvar</h3><dl class="form-review__list"></dl>';
        steps.at(-1).after(review);

        const labels = [...steps.map((step) => step.dataset.step), 'Revisão'];
        labels.forEach((label) => {
            const item = document.createElement('li');
            item.textContent = label;
            progressList.append(item);
        });

        const back = document.createElement('button');
        back.type = 'button'; back.className = 'btn btn-secondary'; back.textContent = 'Voltar';
        const next = document.createElement('button');
        next.type = 'button'; next.className = 'btn btn-primary'; next.textContent = 'Continuar';
        cancel?.after(back);
        actions.append(next);

        let current = Math.max(0, steps.findIndex((step) => step.querySelector('[aria-invalid="true"]')));
        const panels = [...steps, review];

        function refreshReview() {
            const list = review.querySelector('dl');
            list.replaceChildren();
            [...form.elements].filter((field) => field.name && !field.name.startsWith('_') && field.type !== 'hidden' && field.type !== 'submit' && field.type !== 'button')
                .forEach((field) => {
                    if ((field.type === 'checkbox' || field.type === 'radio') && !field.checked) return;
                    const value = field.selectedOptions?.[0]?.textContent || field.value;
                    if (!value) return;
                    const row = document.createElement('div');
                    const term = document.createElement('dt'); term.textContent = fieldLabel(field);
                    const detail = document.createElement('dd'); detail.textContent = field.type === 'checkbox' ? 'Sim' : value;
                    row.append(term, detail); list.append(row);
                });
        }

        async function validateStep() {
            const invalid = [...steps[current].querySelectorAll('input, select, textarea')].filter((field) => !field.checkValidity());
            if (!invalid.length) return true;
            invalid.forEach((field) => field.setAttribute('aria-invalid', 'true'));
            await window.UI?.show({ tone: 'error', title: 'Revise esta etapa', message: `Preencha corretamente: ${invalid.map(fieldLabel).join(', ')}.`, focus: `#${invalid[0].id}` });
            invalid[0].focus();
            return false;
        }

        function render() {
            panels.forEach((panel, index) => { panel.hidden = index !== current; });
            [...progressList.children].forEach((item, index) => {
                item.classList.toggle('is-current', index === current);
                item.classList.toggle('is-complete', index < current);
                if (index === current) item.setAttribute('aria-current', 'step'); else item.removeAttribute('aria-current');
            });
            back.hidden = current === 0;
            next.hidden = current === panels.length - 1;
            submit.hidden = current !== panels.length - 1;
            if (current === panels.length - 1) refreshReview();
            progress.dataset.current = `${current + 1} de ${panels.length}`;
            form.dispatchEvent(new CustomEvent('multistep:change', { bubbles: true, detail: { current } }));
        }

        next.addEventListener('click', async () => {
            if (await validateStep()) { current += 1; render(); progress.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }
        });
        back.addEventListener('click', () => { current -= 1; render(); });
        form.addEventListener('input', (event) => event.target.removeAttribute?.('aria-invalid'));
        render();
    });
}

initializeMultistep();
document.addEventListener('ui:content-ready', (event) => initializeMultistep(event.detail?.root ?? document));
