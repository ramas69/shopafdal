import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['checkbox', 'selectAll', 'counter', 'exportLink', 'bar'];
    static values = { exportBase: String };

    connect() {
        // Portal the floating bar to <body> so `position: fixed` escapes any
        // ancestor with transform/backdrop-filter creating a containing block.
        if (this.hasBarTarget) {
            document.body.appendChild(this.barTarget);
        }
        this.update();
    }

    disconnect() {
        if (this.hasBarTarget) {
            this.barTarget.remove();
        }
    }

    toggleAll() {
        const checked = this.selectAllTarget.checked;
        this.checkboxTargets.forEach(cb => cb.checked = checked);
        this.update();
    }

    update() {
        const ids = this.checkboxTargets.filter(cb => cb.checked).map(cb => cb.value);
        const count = ids.length;
        const total = this.checkboxTargets.length;

        this.counterTarget.textContent = count;
        this.barTarget.classList.toggle('hidden', count === 0);

        if (this.hasSelectAllTarget) {
            this.selectAllTarget.checked = count > 0 && count === total;
            this.selectAllTarget.indeterminate = count > 0 && count < total;
        }

        const params = new URLSearchParams();
        ids.forEach(id => params.append('ids[]', id));
        this.exportLinkTarget.href = this.exportBaseValue + '?' + params.toString();
    }
}
