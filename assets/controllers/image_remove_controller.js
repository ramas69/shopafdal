import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input'];

    remove(event) {
        event.preventDefault();
        event.stopPropagation();
        this.inputTarget.disabled = false;
        this.element.style.display = 'none';
    }
}
