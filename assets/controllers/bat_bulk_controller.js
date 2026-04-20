import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['checkbox', 'selectAll', 'submit', 'counter'];

    connect() {
        this.updateCounter();
    }

    toggleAll(event) {
        const checked = event.currentTarget.checked;
        this.checkboxTargets.forEach(cb => { cb.checked = checked; });
        this.updateCounter();
    }

    updateCounter() {
        const count = this.checkboxTargets.filter(cb => cb.checked).length;
        if (this.hasCounterTarget) this.counterTarget.textContent = count;
        if (this.hasSubmitTarget) this.submitTarget.disabled = count === 0;
        if (this.hasSelectAllTarget) {
            const total = this.checkboxTargets.length;
            this.selectAllTarget.checked = total > 0 && count === total;
            this.selectAllTarget.indeterminate = count > 0 && count < total;
        }
    }
}
