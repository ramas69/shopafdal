import { Controller } from '@hotwired/stimulus';

// Usage: data-controller="enter-submit" sur un <form>.
// Enter dans un textarea = submit. Shift+Enter = retour à la ligne.
export default class extends Controller {
    connect() {
        this.element.addEventListener('keydown', this._handler = (e) => {
            if (e.key !== 'Enter' || e.shiftKey || e.isComposing) return;
            if (e.target.tagName !== 'TEXTAREA') return;
            e.preventDefault();
            if (typeof this.element.requestSubmit === 'function') {
                this.element.requestSubmit();
            } else {
                this.element.submit();
            }
        });
    }

    disconnect() {
        this.element.removeEventListener('keydown', this._handler);
    }
}
