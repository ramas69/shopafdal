import { Controller } from '@hotwired/stimulus';

export default class CatalogueFiltersController extends Controller {
    static targets = ['colorsInput', 'sizesInput'];

    toggleColor(event) {
        this._toggle(this.colorsInputTarget, event.currentTarget.dataset.value);
        this._submit();
    }

    toggleSize(event) {
        this._toggle(this.sizesInputTarget, event.currentTarget.dataset.value);
        this._submit();
    }

    _toggle(input, value) {
        const current = input.value ? input.value.split(',').filter(Boolean) : [];
        const idx = current.indexOf(value);
        if (idx >= 0) {
            current.splice(idx, 1);
        } else {
            current.push(value);
        }
        input.value = current.join(',');
    }

    _submit() {
        this.element.requestSubmit?.() ?? this.element.submit();
    }
}
