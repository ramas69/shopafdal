import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['colorInput', 'colorPanel', 'qtyInput', 'totalPrice', 'totalQty'];
    static values = { unitPrice: Number };

    connect() {
        this.recompute();
    }

    selectColor() {
        const selected = this.colorInputTargets.find(i => i.checked);
        if (!selected) return;
        const index = selected.value;
        this.colorPanelTargets.forEach(panel => {
            panel.classList.toggle('hidden', panel.dataset.colorIndex !== index);
        });
        // Reset quantities in hidden panels
        this.colorPanelTargets.forEach(panel => {
            if (panel.dataset.colorIndex !== index) {
                panel.querySelectorAll('input[type="number"]').forEach(i => i.value = 0);
            }
        });
        this.recompute();
    }

    recompute() {
        let qty = 0;
        this.qtyInputTargets.forEach(input => {
            // Only count visible inputs (inside non-hidden panel)
            if (input.closest('[data-quantities-target="colorPanel"]').classList.contains('hidden')) return;
            qty += parseInt(input.value || '0', 10);
        });
        const totalCents = qty * this.unitPriceValue;
        this.totalPriceTarget.textContent = this.formatPrice(totalCents);
        this.totalQtyTarget.textContent = qty;
    }

    formatPrice(cents) {
        return (cents / 100).toFixed(2).replace('.', ',') + ' €';
    }
}
