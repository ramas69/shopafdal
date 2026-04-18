import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'overlay', 'icon'];

    toggle() {
        const willRemove = this.inputTarget.disabled;
        this.inputTarget.disabled = !willRemove;
        this.overlayTarget.classList.toggle('opacity-0', !willRemove);
        this.overlayTarget.classList.toggle('pointer-events-none', !willRemove);
        this.element.classList.toggle('ring-2', willRemove);
        this.element.classList.toggle('ring-[var(--color-destructive)]', willRemove);
        if (this.hasIconTarget) {
            this.iconTarget.classList.toggle('rotate-45', willRemove);
        }
    }
}
