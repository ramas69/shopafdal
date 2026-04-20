import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['track'];
    static values = { total: Number, index: { type: Number, default: 0 } };

    connect() {
        this._render();
    }

    prev(event) {
        event.preventDefault();
        event.stopPropagation();
        if (this.totalValue < 2) return;
        this.indexValue = (this.indexValue - 1 + this.totalValue) % this.totalValue;
        this._render();
    }

    next(event) {
        event.preventDefault();
        event.stopPropagation();
        if (this.totalValue < 2) return;
        this.indexValue = (this.indexValue + 1) % this.totalValue;
        this._render();
    }

    _render() {
        if (!this.hasTrackTarget) return;
        this.trackTarget.style.transform = `translateX(-${this.indexValue * 100}%)`;
    }
}
