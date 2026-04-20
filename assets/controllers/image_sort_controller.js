import { Controller } from '@hotwired/stimulus';
import Sortable from 'sortablejs';

export default class extends Controller {
    static targets = ['grid', 'item', 'handle', 'primaryBadge'];

    connect() {
        const root = this.hasGridTarget ? this.gridTarget : this.element;
        this.sortable = Sortable.create(root, {
            animation: 150,
            handle: '[data-image-sort-target="handle"]',
            ghostClass: 'opacity-40',
            onEnd: () => this._reindex(),
        });
    }

    disconnect() {
        this.sortable?.destroy();
    }

    _reindex() {
        const items = this.itemTargets;
        items.forEach((item, idx) => {
            item.querySelectorAll('input[name^="existing_images["]').forEach((input) => {
                const name = input.getAttribute('name');
                const newName = name.replace(/existing_images\[\d+]/, `existing_images[${idx}]`);
                input.setAttribute('name', newName);
            });
            item.querySelectorAll('select[name^="existing_images["]').forEach((sel) => {
                const name = sel.getAttribute('name');
                const newName = name.replace(/existing_images\[\d+]/, `existing_images[${idx}]`);
                sel.setAttribute('name', newName);
            });
            const badge = item.querySelector('[data-image-sort-target="primaryBadge"]');
            if (badge) badge.classList.toggle('hidden', idx !== 0);
        });
    }
}
