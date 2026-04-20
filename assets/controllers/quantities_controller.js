import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'qtyInput', 'row', 'rowTotal',
        'totalPrice', 'totalQty', 'unitPrice',
        'savings', 'savingsAmount', 'savingsPct',
        'nextTier', 'nextTierDelta', 'nextTierPrice',
    ];
    static values = {
        unitPrice: Number,
        basePrice: Number,
        negotiated: Boolean,
        tiers: Array,
    };

    connect() {
        this.recompute();
    }

    recompute(event) {
        if (event?.target?.matches?.('input[type="number"]')) {
            this._clampInput(event.target);
        }

        let totalQty = 0;
        this.rowTargets.forEach((row, idx) => {
            let rowQty = 0;
            row.querySelectorAll('input[type="number"]:not([disabled])').forEach(input => {
                rowQty += Math.max(0, parseInt(input.value || '0', 10));
            });
            if (this.hasRowTotalTarget && this.rowTotalTargets[idx]) {
                this.rowTotalTargets[idx].textContent = rowQty;
            }
            totalQty += rowQty;
        });

        const unit = this._resolveUnit(totalQty);
        const totalCents = totalQty * unit;
        const baseUnit = this.hasBasePriceValue && this.basePriceValue > 0 ? this.basePriceValue : unit;

        if (this.hasTotalPriceTarget) this.totalPriceTarget.textContent = this._formatPrice(totalCents);
        if (this.hasTotalQtyTarget) this.totalQtyTarget.textContent = totalQty;
        if (this.hasUnitPriceTarget) this.unitPriceTarget.textContent = this._formatPrice(unit);

        // Économies
        if (this.hasSavingsTarget) {
            const saveUnit = Math.max(0, baseUnit - unit);
            const saveTotal = saveUnit * totalQty;
            if (saveTotal > 0) {
                this.savingsTarget.classList.remove('hidden');
                this.savingsTarget.classList.add('inline-flex');
                if (this.hasSavingsAmountTarget) this.savingsAmountTarget.textContent = this._formatPrice(saveTotal);
                if (this.hasSavingsPctTarget) {
                    const pct = baseUnit > 0 ? Math.round((saveUnit / baseUnit) * 100) : 0;
                    this.savingsPctTarget.textContent = pct;
                }
            } else {
                this.savingsTarget.classList.add('hidden');
                this.savingsTarget.classList.remove('inline-flex');
            }
        }

        // Prochain palier (uniquement si pas en négocié)
        if (this.hasNextTierTarget) {
            const next = this._nextTier(totalQty);
            if (next && !this.negotiatedValue) {
                this.nextTierTarget.classList.remove('hidden');
                if (this.hasNextTierDeltaTarget) this.nextTierDeltaTarget.textContent = next.min_qty - totalQty;
                if (this.hasNextTierPriceTarget) this.nextTierPriceTarget.textContent = this._formatPrice(next.unit_cents);
            } else {
                this.nextTierTarget.classList.add('hidden');
            }
        }
    }

    _resolveUnit(totalQty) {
        if (this.negotiatedValue) return this.unitPriceValue;
        let best = this.unitPriceValue;
        if (Array.isArray(this.tiersValue)) {
            for (const t of this.tiersValue) {
                const minQty = Number(t.min_qty || 0);
                const unit = Number(t.unit_cents || 0);
                if (totalQty >= minQty && unit > 0) best = unit;
            }
        }
        return best;
    }

    _nextTier(totalQty) {
        if (!Array.isArray(this.tiersValue)) return null;
        const sorted = [...this.tiersValue].sort((a, b) => Number(a.min_qty) - Number(b.min_qty));
        for (const t of sorted) {
            if (totalQty < Number(t.min_qty)) return t;
        }
        return null;
    }

    _clampInput(input) {
        const val = parseInt(input.value || '0', 10);
        const max = input.max ? parseInt(input.max, 10) : null;
        if (val < 0) input.value = 0;
        else if (max !== null && val > max) input.value = max;
    }

    _formatPrice(cents) {
        return (cents / 100).toFixed(2).replace('.', ',') + ' €';
    }
}
