import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        title: { type: String, default: 'Êtes-vous sûr ?' },
        message: { type: String, default: 'Cette action est irréversible.' },
        label: { type: String, default: 'Confirmer' },
        variant: { type: String, default: 'destructive' },
    };

    gate(event) {
        if (this.element.dataset.confirmed === 'true') return;
        event.preventDefault();
        this._open();
    }

    _open() {
        const backdrop = document.createElement('div');
        backdrop.className = 'fixed inset-0 z-[100] bg-black/40 backdrop-blur-sm flex items-center justify-center p-4 opacity-0 transition-opacity duration-150';

        const variant = this.variantValue;
        const iconBg = variant === 'destructive' ? 'bg-red-50 text-[var(--color-destructive)]' : 'bg-[var(--color-primary-light)] text-[var(--color-primary)]';
        const btnClass = variant === 'destructive'
            ? 'inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-md bg-[var(--color-destructive)] text-white font-medium cursor-pointer transition-colors hover:opacity-90'
            : 'btn btn-primary';

        backdrop.innerHTML = `
            <div class="card w-full max-w-md p-6 shadow-2xl transform scale-95 transition-transform duration-150" role="dialog" aria-modal="true">
                <div class="flex items-start gap-4 mb-5">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full ${iconBg}">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="font-display text-lg font-semibold text-[var(--color-foreground)] mb-1">${this._escape(this.titleValue)}</h3>
                        <p class="text-sm text-[var(--color-secondary)]">${this._escape(this.messageValue)}</p>
                    </div>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" data-role="cancel" class="btn btn-outline">Annuler</button>
                    <button type="button" data-role="confirm" class="${btnClass}">${this._escape(this.labelValue)}</button>
                </div>
            </div>
        `;

        const dialog = backdrop.querySelector('[role="dialog"]');

        const cleanup = (confirmed) => {
            document.removeEventListener('keydown', onKeydown);
            backdrop.classList.add('opacity-0');
            dialog.classList.add('scale-95');
            setTimeout(() => backdrop.remove(), 150);
            if (confirmed) {
                this.element.dataset.confirmed = 'true';
                if (this.element.tagName === 'FORM') {
                    this.element.requestSubmit();
                } else {
                    this.element.click();
                }
            }
        };

        const onKeydown = (e) => {
            if (e.key === 'Escape') cleanup(false);
            if (e.key === 'Enter') cleanup(true);
        };
        document.addEventListener('keydown', onKeydown);

        backdrop.addEventListener('click', (e) => { if (e.target === backdrop) cleanup(false); });
        backdrop.querySelector('[data-role="cancel"]').addEventListener('click', () => cleanup(false));
        backdrop.querySelector('[data-role="confirm"]').addEventListener('click', () => cleanup(true));

        document.body.appendChild(backdrop);
        requestAnimationFrame(() => {
            backdrop.classList.remove('opacity-0');
            dialog.classList.remove('scale-95');
        });
        backdrop.querySelector('[data-role="confirm"]').focus();
    }

    _escape(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
}
