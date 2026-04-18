import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['list', 'template', 'row', 'empty'];

    add() {
        const index = this._nextIndex();
        const html = this.templateTarget.innerHTML.replaceAll('__INDEX__', index);
        this.listTarget.insertAdjacentHTML('beforeend', html);
        this._refreshEmpty();
    }

    remove(event) {
        const row = event.currentTarget.closest('[data-variants-editor-target="row"]');
        if (row) row.remove();
        this._refreshEmpty();
    }

    _nextIndex() {
        return this.rowTargets.length;
    }

    _refreshEmpty() {
        if (this.hasEmptyTarget) {
            this.emptyTarget.classList.toggle('hidden', this.rowTargets.length > 0);
        }
    }
}
