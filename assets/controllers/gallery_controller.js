import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['main', 'thumb'];

    select(event) {
        const src = event.currentTarget.dataset.src;
        if (!src) return;
        this.mainTarget.src = src;
        this.thumbTargets.forEach(t => {
            t.classList.toggle('border-[var(--color-primary)]', t === event.currentTarget);
            t.classList.toggle('border-transparent', t !== event.currentTarget);
        });
    }
}
