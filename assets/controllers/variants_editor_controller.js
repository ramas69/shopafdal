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

    duplicate(event) {
        const sourceRow = event.currentTarget.closest('[data-variants-editor-target="row"]');
        if (!sourceRow) return;

        const index = this._nextIndex();
        const html = this.templateTarget.innerHTML.replaceAll('__INDEX__', index);
        sourceRow.insertAdjacentHTML('afterend', html);
        const newRow = sourceRow.nextElementSibling;

        const fields = ['size', 'color', 'color_hex', 'sku'];
        for (const f of fields) {
            const src = sourceRow.querySelector(`[name$="[${f}]"]`);
            const dst = newRow.querySelector(`[name$="[${f}]"]`);
            if (src && dst) dst.value = src.value;
        }

        const skuInput = newRow.querySelector('[name$="[sku]"]');
        if (skuInput?.value) skuInput.value = `${skuInput.value}-COPY`;

        this._refreshEmpty();
        newRow.querySelector('[name$="[size]"]')?.focus();
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
