import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['main', 'thumb'];
    static values = { defaultSrc: String };

    connect() {
        if (!this.hasDefaultSrcValue && this.hasMainTarget) {
            this.defaultSrcValue = this.mainTarget.getAttribute('src') || '';
        }
    }

    select(event) {
        const src = event.currentTarget.dataset.src;
        if (!src) return;
        this.mainTarget.src = src;
        this.defaultSrcValue = src;
        this.thumbTargets.forEach(t => {
            t.classList.toggle('border-[var(--color-primary)]', t === event.currentTarget);
            t.classList.toggle('border-transparent', t !== event.currentTarget);
        });
    }

    peek(event) {
        const src = event.currentTarget.dataset.colorImage;
        if (!src || !this.hasMainTarget) return;
        this.mainTarget.src = src;
    }

    restore() {
        if (this.hasMainTarget && this.defaultSrcValue) {
            this.mainTarget.src = this.defaultSrcValue;
        }
    }
}
