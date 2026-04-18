import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        interval: { type: Number, default: 10000 },
        url: String,
    };

    connect() {
        this._timer = setInterval(() => this._refresh(), this.intervalValue);
    }

    disconnect() {
        clearInterval(this._timer);
    }

    async _refresh() {
        if (document.hidden) return;
        try {
            const res = await fetch(this.urlValue, { headers: { 'Accept': 'text/html' } });
            if (!res.ok) return;
            const html = await res.text();
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const fresh = doc.querySelector(`[data-controller~="poll"][data-poll-url-value="${this.urlValue}"]`);
            if (fresh && fresh.innerHTML !== this.element.innerHTML) {
                this.element.innerHTML = fresh.innerHTML;
            }
        } catch (_) {
            // silent
        }
    }
}
