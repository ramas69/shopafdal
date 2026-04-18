import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { delay: { type: Number, default: 300 } };

    connect() {
        this._timeout = null;
    }

    disconnect() {
        clearTimeout(this._timeout);
    }

    // Debounced submit (for text inputs)
    submit() {
        clearTimeout(this._timeout);
        this._timeout = setTimeout(() => this.element.requestSubmit(), this.delayValue);
    }

    // Immediate submit (for selects / checkboxes)
    submitNow() {
        clearTimeout(this._timeout);
        this.element.requestSubmit();
    }
}
