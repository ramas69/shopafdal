import { Controller } from '@hotwired/stimulus';
import TomSelect from 'tom-select';
import 'tom-select/dist/css/tom-select.default.min.css';

export default class extends Controller {
    connect() {
        if (this._instance) return;
        this._instance = new TomSelect(this.element, {
            plugins: this.element.multiple ? ['remove_button'] : [],
            allowEmptyOption: true,
            create: false,
            maxOptions: 500,
            controlInput: this.element.dataset.tomSelectSearch === 'true' ? null : null,
        });
    }

    disconnect() {
        if (this._instance) {
            this._instance.destroy();
            this._instance = null;
        }
    }
}
