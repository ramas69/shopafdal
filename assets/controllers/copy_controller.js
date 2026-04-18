import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { text: String };

    async copy() {
        try {
            await navigator.clipboard.writeText(this.textValue);
            this._flash();
        } catch (_) {
            // fallback
            const ta = document.createElement('textarea');
            ta.value = this.textValue;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); } catch (__) {}
            ta.remove();
            this._flash();
        }
    }

    _flash() {
        const original = this.element.innerHTML;
        this.element.innerHTML = `
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
            </svg>
            Copié !
        `;
        this.element.classList.add('text-[var(--color-success)]');
        setTimeout(() => {
            this.element.innerHTML = original;
            this.element.classList.remove('text-[var(--color-success)]');
        }, 1500);
    }
}
