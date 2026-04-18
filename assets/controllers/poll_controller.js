import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { interval: { type: Number, default: 10000 } };

    connect() {
        this._timer = setInterval(() => {
            if (typeof this.element.reload === 'function') {
                this.element.reload();
            } else {
                window.location.reload();
            }
        }, this.intervalValue);
    }

    disconnect() {
        clearInterval(this._timer);
    }
}
