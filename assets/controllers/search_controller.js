import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'dropdown', 'results'];
    static values = { url: String };

    connect() {
        this._debounceTimer = null;
        this._onDocClick = this._onDocClick.bind(this);
        this._onKeydown = this._onKeydown.bind(this);
    }

    input(event) {
        const value = event.target.value.trim();
        clearTimeout(this._debounceTimer);
        if (value.length < 2) {
            this.close();
            return;
        }
        this._debounceTimer = setTimeout(() => this._fetch(value), 250);
    }

    focus() {
        if (this.inputTarget.value.trim().length >= 2) {
            this._open();
        }
    }

    async _fetch(q) {
        try {
            const res = await fetch(`${this.urlValue}?q=${encodeURIComponent(q)}`, {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
            });
            if (!res.ok) return;
            const data = await res.json();
            this._render(data.groups || []);
        } catch (_) {
            // silent
        }
    }

    _render(groups) {
        if (groups.length === 0) {
            this.resultsTarget.innerHTML = `
                <div class="px-4 py-8 text-center text-sm text-[var(--color-secondary)]">
                    Aucun résultat.
                </div>
            `;
            this._open();
            return;
        }
        const html = groups.map(g => `
            <div>
                <div class="px-4 pt-3 pb-1 text-[10px] font-semibold uppercase tracking-wider text-[var(--color-secondary)]">${g.label}</div>
                ${g.items.map(item => `
                    <a href="${item.url}"
                       class="flex items-center gap-3 px-4 py-2.5 hover:bg-[var(--color-muted)] transition-colors">
                        <div class="w-8 h-8 rounded-md bg-[var(--color-primary)]/10 text-[var(--color-primary)] flex items-center justify-center shrink-0">
                            ${this._icon(item.icon)}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-medium text-sm text-[var(--color-foreground)] truncate">${this._escape(item.title)}</div>
                            <div class="text-xs text-[var(--color-secondary)] truncate">${this._escape(item.subtitle)}</div>
                        </div>
                    </a>
                `).join('')}
            </div>
        `).join('');
        this.resultsTarget.innerHTML = html;
        this._open();
    }

    _open() {
        this.dropdownTarget.classList.remove('hidden');
        document.addEventListener('click', this._onDocClick);
        document.addEventListener('keydown', this._onKeydown);
    }

    close() {
        this.dropdownTarget.classList.add('hidden');
        document.removeEventListener('click', this._onDocClick);
        document.removeEventListener('keydown', this._onKeydown);
    }

    _onDocClick(event) {
        if (!this.element.contains(event.target)) {
            this.close();
        }
    }

    _onKeydown(event) {
        if (event.key === 'Escape') {
            this.close();
            this.inputTarget.blur();
        }
    }

    _icon(name) {
        const icons = {
            list: '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2Z"/></svg>',
            package: '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m7.5 14.25 5.25 5.25 5.25-5.25M7.5 9h10.5V5.25A2.25 2.25 0 0 0 15.75 3H8.25A2.25 2.25 0 0 0 6 5.25V9h1.5Z"/></svg>',
            users: '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766"/></svg>',
            pin: '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/></svg>',
        };
        return icons[name] || '';
    }

    _escape(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    }
}
