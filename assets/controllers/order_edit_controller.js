import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['row', 'total', 'lineTotal'];

    connect() {
        this.formatter = new Intl.NumberFormat('fr-FR', {
            style: 'currency',
            currency: 'EUR',
            minimumFractionDigits: 2,
        });
        this.recompute();
    }

    recompute() {
        let total = 0;
        this.rowTargets.forEach((row) => {
            const unit = parseInt(row.dataset.unitPrice || '0', 10);
            const qtyInput = row.querySelector('input[type="number"]');
            const removeCheckbox = row.querySelector('input[type="checkbox"][name^="remove"]');
            const qty = Math.max(0, parseInt(qtyInput?.value || '0', 10));
            const isRemoved = removeCheckbox?.checked;

            const lineTotal = isRemoved ? 0 : qty * unit;
            const lineEl = row.querySelector('[data-order-edit-target="lineTotal"]');
            if (lineEl) lineEl.textContent = this.formatter.format(lineTotal / 100);

            row.classList.toggle('opacity-50', isRemoved);

            total += lineTotal;
        });
        this.totalTarget.textContent = this.formatter.format(total / 100);
    }
}
