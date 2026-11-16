/*
 * Campo de seleção personalizado (padrão ARIA "select-only combobox" + listbox).
 *
 * Melhoria progressiva: o <select> nativo continua sendo a fonte da verdade (envio do formulário,
 * validação, eventos `change`), apenas oculto visualmente. Só é aprimorado em dispositivos com mouse
 * (hover + ponteiro fino); em telas de toque mantém-se o seletor nativo do aparelho, que é o melhor
 * controle para o polegar, estilizado com o mesmo visual (.field).
 *
 * Opt-out: <select data-native>.  Estados: desabilitado, carregando (data-loading), inválido (aria-invalid),
 * sem resultados na busca, busca automática quando há muitas opções (data-search="always|never").
 * Se o conteúdo do <select> mudar por JavaScript, o componente se atualiza sozinho
 * (MutationObserver) ou ao receber o evento `ui:sync`.
 */

const FINE_POINTER = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
const SEARCH_THRESHOLD = 8;
let uid = 0;

class UiSelect {
    constructor(select) {
        this.select = select;
        this.id = `ui-sel-${++uid}`;
        this.open = false;
        this.activeIndex = -1;
        this.typed = '';
        this.typedTimer = null;
        this.build();
        this.bind();
        this.refresh();
    }

    build() {
        const { select } = this;
        this.wrap = document.createElement('div');
        this.wrap.className = 'ui-select';
        select.parentNode.insertBefore(this.wrap, select);
        this.wrap.appendChild(select);
        select.classList.add('ui-select__native');
        select.tabIndex = -1;
        select.setAttribute('aria-hidden', 'true');

        this.button = document.createElement('button');
        this.button.type = 'button';
        this.button.className = 'field ui-select__btn';
        this.button.setAttribute('role', 'combobox');
        this.button.setAttribute('aria-haspopup', 'listbox');
        this.button.setAttribute('aria-expanded', 'false');
        this.button.setAttribute('aria-controls', `${this.id}-list`);
        this.label = document.createElement('span');
        this.label.className = 'ui-select__value';
        this.button.append(this.label);
        this.button.insertAdjacentHTML('beforeend',
            '<svg class="ui-select__chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>');

        const lab = select.id ? document.querySelector(`label[for="${CSS.escape(select.id)}"]`) : null;
        if (lab) {
            lab.id = lab.id || `${this.id}-label`;
            this.button.setAttribute('aria-labelledby', `${lab.id} ${this.id}-btn`);
            this.labelEl = lab;
        } else if (select.getAttribute('aria-label')) {
            this.button.setAttribute('aria-label', select.getAttribute('aria-label'));
        }
        this.button.id = `${this.id}-btn`;
        this.wrap.insertBefore(this.button, select);

        this.popup = document.createElement('div');
        this.popup.className = 'ui-select__popup';
        this.popup.hidden = true;
        this.search = document.createElement('input');
        this.search.type = 'search';
        this.search.className = 'ui-select__search';
        this.search.placeholder = 'Buscar…';
        this.search.setAttribute('aria-label', 'Buscar nas opções');
        this.search.setAttribute('role', 'combobox');
        this.search.setAttribute('aria-expanded', 'true');
        this.search.setAttribute('aria-controls', `${this.id}-list`);
        this.search.autocomplete = 'off';
        this.list = document.createElement('div');
        this.list.id = `${this.id}-list`;
        this.list.className = 'ui-select__list';
        this.list.setAttribute('role', 'listbox');
        this.empty = document.createElement('p');
        this.empty.className = 'ui-select__empty';
        this.empty.hidden = true;
        this.popup.append(this.search, this.list, this.empty);
        this.wrap.appendChild(this.popup);
    }

    bind() {
        const { button, search, list, select } = this;

        button.addEventListener('click', () => (this.open ? this.close(true) : this.openList()));
        button.addEventListener('keydown', (e) => this.onKey(e));
        search.addEventListener('keydown', (e) => this.onKey(e));
        search.addEventListener('input', () => this.filter(search.value));

        // mousedown (e não click) para não tirar o foco do campo antes de selecionar
        list.addEventListener('mousedown', (e) => {
            const option = e.target.closest('[role="option"]');
            if (option) {
                e.preventDefault();
                if (option.getAttribute('aria-disabled') !== 'true') {
                    this.choose(Number(option.dataset.index));
                }
            }
        });
        list.addEventListener('mousemove', (e) => {
            const option = e.target.closest('[role="option"]');
            if (option && Number(option.dataset.index) !== this.activeIndex) {
                this.setActive(Number(option.dataset.index), false);
            }
        });

        this.onDocDown = (e) => {
            if (this.open && !this.wrap.contains(e.target)) {
                this.close(false);
            }
        };
        this.onReposition = (e) => {
            if (this.open && !(e && this.popup.contains(e.target))) {
                this.position();
            }
        };
        document.addEventListener('mousedown', this.onDocDown);
        window.addEventListener('resize', this.onReposition);
        window.addEventListener('scroll', this.onReposition, true);

        this.labelEl?.addEventListener('click', (e) => { e.preventDefault(); button.focus(); });
        select.form?.addEventListener('reset', () => setTimeout(() => this.refresh(), 0));
        select.addEventListener('ui:sync', () => this.refresh());
        select.addEventListener('change', () => this.syncValue());

        new MutationObserver(() => this.refresh()).observe(select, {
            childList: true, subtree: true, attributes: true, attributeFilter: ['disabled', 'data-loading', 'aria-invalid', 'required'],
        });
    }

    get options() {
        return [...this.select.options];
    }

    refresh() {
        const { select, button } = this;
        const loading = select.hasAttribute('data-loading');
        const disabled = select.disabled || loading;
        button.disabled = disabled;
        button.toggleAttribute('aria-busy', loading);
        if (select.getAttribute('aria-invalid') === 'true') {
            button.setAttribute('aria-invalid', 'true');
        } else {
            button.removeAttribute('aria-invalid');
        }
        const withSearch = select.dataset.search === 'always'
            || (select.dataset.search !== 'never' && this.options.length > SEARCH_THRESHOLD);
        this.search.hidden = !withSearch;
        this.renderOptions();
        this.syncValue();
        if (this.open && disabled) {
            this.close(false);
        }
    }

    renderOptions() {
        this.list.replaceChildren();
        this.options.forEach((opt, index) => {
            const el = document.createElement('div');
            el.id = `${this.id}-opt-${index}`;
            el.setAttribute('role', 'option');
            el.dataset.index = String(index);
            el.setAttribute('aria-selected', 'false');
            if (opt.disabled) {
                el.setAttribute('aria-disabled', 'true');
            }
            if (opt.value === '') {
                el.classList.add('is-placeholder');
            }
            const text = document.createElement('span');
            text.textContent = opt.textContent.trim();
            el.append(text);
            el.insertAdjacentHTML('beforeend',
                '<svg class="ui-select__check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5 9-10"/></svg>');
            this.list.append(el);
        });
        this.empty.textContent = this.options.length === 0 ? 'Sem opções disponíveis.' : 'Nenhum resultado para a busca.';
        this.empty.hidden = this.options.length !== 0;
    }

    syncValue() {
        const { select, label } = this;
        const selected = select.selectedOptions[0] ?? this.options[0];
        const loading = select.hasAttribute('data-loading');
        label.textContent = loading ? 'Carregando…' : (selected ? selected.textContent.trim() : 'Sem opções');
        label.classList.toggle('is-placeholder', !selected || selected.value === '' || loading);
        this.list.querySelectorAll('[role="option"]').forEach((el) => {
            el.setAttribute('aria-selected', String(Number(el.dataset.index) === select.selectedIndex));
        });
    }

    visibleOptions() {
        return [...this.list.querySelectorAll('[role="option"]:not([hidden]):not([aria-disabled="true"])')];
    }

    openList() {
        if (this.button.disabled) {
            return;
        }
        this.open = true;
        this.popup.hidden = false;
        this.button.setAttribute('aria-expanded', 'true');
        this.search.value = '';
        this.filter('');
        this.position();
        const current = this.select.selectedIndex >= 0 ? this.select.selectedIndex : 0;
        this.setActive(current, true);
        if (!this.search.hidden) {
            this.search.focus({ preventScroll: true });
        }
    }

    close(returnFocus) {
        if (!this.open) {
            return;
        }
        this.open = false;
        this.popup.hidden = true;
        this.button.setAttribute('aria-expanded', 'false');
        this.button.removeAttribute('aria-activedescendant');
        if (returnFocus) {
            this.button.focus({ preventScroll: true });
        }
    }

    position() {
        const rect = this.button.getBoundingClientRect();
        const below = window.innerHeight - rect.bottom - 12;
        const above = rect.top - 12;
        const wanted = Math.min(this.popup.scrollHeight || 280, 320);
        const up = below < Math.min(wanted, 200) && above > below;
        const room = Math.max(120, Math.min(320, up ? above : below));
        Object.assign(this.popup.style, {
            left: `${rect.left}px`,
            width: `${rect.width}px`,
            maxHeight: `${room}px`,
            top: up ? 'auto' : `${rect.bottom + 6}px`,
            bottom: up ? `${window.innerHeight - rect.top + 6}px` : 'auto',
        });
        this.list.style.maxHeight = `${Math.max(80, room - (this.search.hidden ? 12 : 60))}px`;
    }

    filter(term) {
        const needle = term.trim().toLocaleLowerCase('pt-BR');
        let shown = 0;
        this.list.querySelectorAll('[role="option"]').forEach((el, i) => {
            const match = needle === '' || this.options[i].textContent.toLocaleLowerCase('pt-BR').includes(needle);
            el.hidden = !match;
            shown += match ? 1 : 0;
        });
        this.empty.hidden = shown > 0 || this.options.length === 0;
        if (this.options.length > 0 && shown === 0) {
            this.empty.textContent = 'Nenhum resultado para a busca.';
        }
        const first = this.visibleOptions()[0];
        this.setActive(first ? Number(first.dataset.index) : -1, true);
    }

    setActive(index, scroll) {
        this.activeIndex = index;
        this.list.querySelectorAll('[role="option"]').forEach((el) => {
            el.classList.toggle('is-active', Number(el.dataset.index) === index);
        });
        const target = this.list.querySelector(`[data-index="${index}"]`);
        const owner = this.search.hidden ? this.button : this.search;
        if (target) {
            owner.setAttribute('aria-activedescendant', target.id);
            if (scroll) {
                target.scrollIntoView({ block: 'nearest' });
            }
        } else {
            owner.removeAttribute('aria-activedescendant');
        }
    }

    move(delta) {
        const visible = this.visibleOptions();
        if (visible.length === 0) {
            return;
        }
        const pos = visible.findIndex((el) => Number(el.dataset.index) === this.activeIndex);
        const next = visible[Math.min(visible.length - 1, Math.max(0, pos + delta))];
        this.setActive(Number(next.dataset.index), true);
    }

    choose(index) {
        const { select } = this;
        if (index >= 0 && index !== select.selectedIndex) {
            select.selectedIndex = index;
            select.dispatchEvent(new Event('input', { bubbles: true }));
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
        this.syncValue();
        this.close(true);
    }

    onKey(e) {
        const inSearch = e.target === this.search;
        const key = e.key;

        if (!this.open) {
            if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(key)) {
                e.preventDefault();
                this.openList();
            } else if (key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
                this.typeAhead(key);
            }
            return;
        }

        switch (key) {
            case 'ArrowDown': e.preventDefault(); this.move(1); break;
            case 'ArrowUp': e.preventDefault(); this.move(-1); break;
            case 'Home': if (!inSearch) { e.preventDefault(); this.move(-9999); } break;
            case 'End': if (!inSearch) { e.preventDefault(); this.move(9999); } break;
            case 'PageDown': e.preventDefault(); this.move(8); break;
            case 'PageUp': e.preventDefault(); this.move(-8); break;
            case 'Enter': e.preventDefault(); if (this.activeIndex >= 0) { this.choose(this.activeIndex); } break;
            case ' ': if (!inSearch) { e.preventDefault(); if (this.activeIndex >= 0) { this.choose(this.activeIndex); } } break;
            case 'Escape': e.preventDefault(); e.stopPropagation(); this.close(true); break;
            case 'Tab': if (this.activeIndex >= 0 && this.activeIndex !== this.select.selectedIndex) { this.choose(this.activeIndex); } else { this.close(false); } break;
            default:
                if (!inSearch && key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
                    this.typeAhead(key);
                }
        }
    }

    typeAhead(char) {
        clearTimeout(this.typedTimer);
        this.typed += char.toLocaleLowerCase('pt-BR');
        this.typedTimer = setTimeout(() => { this.typed = ''; }, 700);
        const match = this.options.findIndex((o) => !o.disabled && o.textContent.trim().toLocaleLowerCase('pt-BR').startsWith(this.typed));
        if (match < 0) {
            return;
        }
        if (this.open) {
            this.setActive(match, true);
        } else {
            this.choose(match);
        }
    }
}

export function enhanceSelects(root = document) {
    if (!FINE_POINTER) {
        return;
    }
    root.querySelectorAll('select:not([multiple]):not([data-native]):not(.ui-select__native)').forEach((select) => {
        if (!select.closest('.ui-select')) {
            new UiSelect(select);
        }
    });
}

enhanceSelects();
