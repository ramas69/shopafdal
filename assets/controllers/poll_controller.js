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
        if (document.hidden || !this.element.id) return;
        // Skip if this element has a currently-open dropdown menu (avoid closing it during user interaction)
        const openMenu = this.element.querySelector('[data-dropdown-target="menu"]:not(.hidden)');
        if (openMenu) return;
        // Skip if an interactive panel explicitly marks itself (e.g. BAT reject form open, editing in progress)
        if (this.element.querySelector('[data-poll-skip]')) return;
        // Skip if focus is on any input/textarea/select inside — user is typing
        const active = document.activeElement;
        if (active && this.element.contains(active) && /^(INPUT|TEXTAREA|SELECT)$/.test(active.tagName)) return;
        try {
            const res = await fetch(this.urlValue, {
                headers: { 'Accept': 'text/html' },
                credentials: 'same-origin',
                cache: 'no-store',
            });
            if (!res.ok) return;
            const html = await res.text();
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const fresh = doc.getElementById(this.element.id);
            if (fresh && fresh.innerHTML.trim() !== this.element.innerHTML.trim()) {
                this.element.innerHTML = fresh.innerHTML;
            }
        } catch (_) {
            // silent
        }
    }
}
