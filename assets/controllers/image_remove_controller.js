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

        this.overlayTarget.style.setProperty('opacity', next ? '1' : '0', 'important');
        this.element.style.setProperty('outline', next ? '3px solid #E82538' : 'none', 'important');
        this.element.style.setProperty('outline-offset', next ? '2px' : '0', 'important');
    }
}
