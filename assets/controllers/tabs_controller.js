import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['tab', 'panel'];

    connect() {
        const hash = window.location.hash.replace('#', '');
        const validIds = this.tabTargets.map(t => t.dataset.tabId);
        const initial = validIds.includes(hash) ? hash : this.tabTargets[0]?.dataset.tabId;
        this._activate(initial);
    }

    select(event) {
        const id = event.currentTarget.dataset.tabId;
        if (!id) return;
        this._activate(id);
        history.replaceState(null, '', `#${id}`);
    }

    _activate(id) {
        this.tabTargets.forEach(tab => {
            const active = tab.dataset.tabId === id;
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
            if (active) {
                tab.classList.add('text-[var(--color-primary)]', 'border-[var(--color-primary)]');
                tab.classList.remove('text-[var(--color-secondary)]', 'border-transparent');
            } else {
                tab.classList.remove('text-[var(--color-primary)]', 'border-[var(--color-primary)]');
                tab.classList.add('text-[var(--color-secondary)]', 'border-transparent');
            }
        });
        this.panelTargets.forEach(panel => {
            panel.classList.toggle('hidden', panel.dataset.panelId !== id);
        });
    }
}
