import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['panel', 'backdrop'];

    connect() {
        this._handleEscape = this._handleEscape.bind(this);
    }

    open() {
        this.panelTarget.classList.remove('-translate-x-full');
        this.backdropTarget.classList.remove('opacity-0', 'pointer-events-none');
        document.addEventListener('keydown', this._handleEscape);
    }

    close() {
        this.panelTarget.classList.add('-translate-x-full');
        this.backdropTarget.classList.add('opacity-0', 'pointer-events-none');
        document.removeEventListener('keydown', this._handleEscape);
    }

    _handleEscape(event) {
        if (event.key === 'Escape') {
            this.close();
        }
    }
}
