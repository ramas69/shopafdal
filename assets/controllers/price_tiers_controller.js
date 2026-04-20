import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['list', 'template', 'row'];

    add() {
        const index = this.rowTargets.length;
        const html = this.templateTarget.innerHTML.replaceAll('__INDEX__', index);
        this.listTarget.insertAdjacentHTML('beforeend', html);
    }

    remove(event) {
        event.currentTarget.closest('[data-price-tiers-target="row"]')?.remove();
    }
}
