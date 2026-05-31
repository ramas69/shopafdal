import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'dropzone'];

    pick(event) {
        event.preventDefault();
        this.inputTarget.click();
    }

    submit() {
        const file = this.inputTarget.files?.[0];
        if (!file) {
            return;
        }
        if (this.hasDropzoneTarget) {
            this.dropzoneTarget.disabled = true;
            this.dropzoneTarget.innerHTML = `
                <svg class="w-6 h-6 animate-spin text-[var(--color-primary)]" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity="0.25" stroke-width="3"/>
                    <path d="M22 12a10 10 0 0 1-10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                </svg>
                <span class="text-sm font-medium text-[var(--color-foreground)]">Envoi de ${this._escape(file.name)}…</span>
            `;
        }
        this.element.requestSubmit();
    }

    _escape(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }
}
