import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'overlay'];

    connect() {
        // Expose state via class on root, CSS-driven styling from there
        this.element.dataset.marked = 'false';
    }

    toggle(event) {
        event.preventDefault();
        event.stopPropagation();

        const marked = this.element.dataset.marked === 'true';
        const next = !marked;

        this.element.dataset.marked = next ? 'true' : 'false';
        this.inputTarget.disabled = !next;
        this.overlayTarget.classList.toggle('opacity-0', !next);
        this.overlayTarget.classList.toggle('pointer-events-none', !next);
    }
}
