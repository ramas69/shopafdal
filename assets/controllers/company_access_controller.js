import { Controller } from '@hotwired/stimulus';

// Normalise pour recherche sans accents / casse
const normalize = (s) => (s || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');

export default class extends Controller {
    static targets = ['modal', 'backdrop', 'search', 'list', 'counter', 'summary', 'hiddenInputs', 'newName', 'newSiret', 'newError', 'orphanWarning', 'orphanCount'];
    static values = {
        quickCreateUrl: String,
    };

    connect() {
        this._onKey = (e) => { if (e.key === 'Escape' && !this.modalTarget.classList.contains('hidden')) this.close(); };
        document.addEventListener('keydown', this._onKey);
        this._refreshSummary();
    }

    disconnect() {
        document.removeEventListener('keydown', this._onKey);
    }

    open(event) {
        event?.preventDefault();
        this.modalTarget.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        this.searchTarget.value = '';
        this._filter();
        setTimeout(() => this.searchTarget.focus(), 50);
    }

    close() {
        this.modalTarget.classList.add('hidden');
        document.body.style.overflow = '';
    }

    closeOnBackdrop(event) {
        if (event.target === this.backdropTarget) this.close();
    }

    filter() {
        this._filter();
    }

    _filter() {
        const q = normalize(this.searchTarget.value.trim());
        const rows = this.listTarget.querySelectorAll('[data-company-row]');
        rows.forEach((row) => {
            if (q === '') {
                row.classList.remove('hidden');
                return;
            }
            const hay = normalize(row.dataset.searchHay || '');
            row.classList.toggle('hidden', !hay.includes(q));
        });
    }

    toggle(event) {
        this._refreshSummary();
    }

    apply() {
        // Sync hidden inputs du form principal avec les cases cochées du modal
        const checked = Array.from(this.listTarget.querySelectorAll('input[type=checkbox]:checked'));
        this.hiddenInputsTarget.innerHTML = checked
            .map((cb) => `<input type="hidden" name="company_access[]" value="${cb.value}">`)
            .join('');
        this._refreshSummary();
        this.close();
    }

    _refreshSummary() {
        const checked = Array.from(this.listTarget.querySelectorAll('input[type=checkbox]:checked'));
        this.counterTargets.forEach((t) => (t.textContent = checked.length));

        const orphanCount = checked.filter((cb) => cb.dataset.statusKey === 'orphan').length;
        if (this.hasOrphanWarningTarget) {
            this.orphanWarningTarget.classList.toggle('hidden', orphanCount === 0);
            if (this.hasOrphanCountTarget) this.orphanCountTarget.textContent = orphanCount;
        }

        if (checked.length === 0) {
            this.summaryTarget.innerHTML = '<span class="text-xs text-[var(--color-warning,#b45309)] font-medium">Aucune entreprise — ce produit ne sera visible par aucun client.</span>';
            return;
        }

        const badges = checked.slice(0, 5).map((cb) => {
            const isOrphan = cb.dataset.statusKey === 'orphan';
            const cls = isOrphan
                ? 'bg-amber-50 text-amber-700 ring-1 ring-amber-200'
                : 'bg-[var(--color-primary-light)] text-[var(--color-primary)]';
            const suffix = isOrphan ? ' <span class="text-[10px] opacity-70">(à inviter)</span>' : '';
            return `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-medium ${cls}">${this._escape(cb.dataset.name)}${suffix}</span>`;
        });
        const more = checked.length > 5 ? ` +${checked.length - 5} autres` : '';
        this.summaryTarget.innerHTML = badges.join(' ') + (more ? `<span class="text-xs text-[var(--color-secondary)] ml-1">${more}</span>` : '');
    }

    _escape(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    async createNew(event) {
        event.preventDefault();
        const name = this.newNameTarget.value.trim();
        const siret = this.newSiretTarget.value.trim();
        this.newErrorTarget.textContent = '';
        if (name === '') {
            this.newErrorTarget.textContent = 'Nom requis.';
            return;
        }
        try {
            const fd = new FormData();
            fd.append('name', name);
            fd.append('siret', siret);
            const res = await fetch(this.quickCreateUrlValue, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
            });
            const data = await res.json();
            if (!res.ok) {
                this.newErrorTarget.textContent = data.error || 'Erreur à la création.';
                return;
            }
            // Injecte la nouvelle ligne cochée en tête de liste (status orphan par défaut)
            const statusKey = data.status_key || 'orphan';
            const statusColor = {
                active: 'text-emerald-700',
                inactive: 'text-[var(--color-secondary)]',
                invited: 'text-blue-700',
                orphan: 'text-amber-700',
            }[statusKey] || 'text-[var(--color-secondary)]';

            const row = document.createElement('label');
            row.dataset.companyRow = '';
            row.dataset.searchHay = `${data.name} ${data.siret || ''}`;
            row.className = 'flex items-center gap-3 px-3 py-2 hover:bg-[var(--color-muted)] cursor-pointer rounded-md';
            const inviteBtn = data.invite_url
                ? `<a href="${data.invite_url}" class="shrink-0 text-xs font-medium px-2 py-1 rounded-md border border-[var(--color-border-soft)] hover:bg-white hover:border-[var(--color-primary)] hover:text-[var(--color-primary)]" title="Créer une invitation" onclick="event.stopPropagation()">Inviter</a>`
                : '';
            const siretPart = data.siret
                ? ` · <span class="text-[var(--color-secondary)]">${this._escape(data.siret)}</span>`
                : '';
            row.innerHTML = `
                <input type="checkbox" value="${data.id}" data-name="${this._escape(data.name)}" data-status-key="${statusKey}" data-action="change->company-access#toggle" checked
                       class="w-4 h-4 rounded border-[var(--color-border)]">
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-medium text-[var(--color-foreground)] truncate">${this._escape(data.name)}</div>
                    <div class="text-xs ${statusColor}">${this._escape(data.status_label)}${siretPart}</div>
                </div>
                ${inviteBtn}
            `;
            this.listTarget.prepend(row);
            this.newNameTarget.value = '';
            this.newSiretTarget.value = '';
            this._refreshSummary();
        } catch (e) {
            this.newErrorTarget.textContent = 'Erreur réseau.';
        }
    }
}
