import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'overlay'];

    connect() {
        this.element.dataset.marked = 'false';
    }

    toggle(event) {
        event.preventDefault();
        event.stopPropagation();

        const marked = this.element.dataset.marked === 'true';
        const next = !marked;

        this.element.dataset.marked = next ? 'true' : 'false';
        this.inputTarget.disabled = !next;

        // Inline styles — bypass any Tailwind compilation issue
        this.overlayTarget.style.opacity = next ? '1' : '0';
        this.overlayTarget.style.pointerEvents = next ? 'auto' : 'none';

        // Visual ring on the wrapper via inline outline
        this.element.style.outline = next ? '2px solid #E82538' : '';
        this.element.style.outlineOffset = next ? '2px' : '';
    }
}
