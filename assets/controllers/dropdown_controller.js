import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['menu'];

    connect() {
        this._onDocClick = this._onDocClick.bind(this);
        this._onEscape = this._onEscape.bind(this);
    }

    toggle(event) {
        event.stopPropagation();
        if (this.menuTarget.classList.contains('hidden')) {
            this._open();
        } else {
            this._close();
        }
    }

    close() {
        this._close();
    }

    _open() {
        this.menuTarget.classList.remove('hidden');
        document.addEventListener('click', this._onDocClick);
        document.addEventListener('keydown', this._onEscape);
    }

    _close() {
        this.menuTarget.classList.add('hidden');
        document.removeEventListener('click', this._onDocClick);
        document.removeEventListener('keydown', this._onEscape);
    }

    _onDocClick(event) {
        if (!this.element.contains(event.target)) {
            this._close();
        }
    }

    _onEscape(event) {
        if (event.key === 'Escape') this._close();
    }
}
