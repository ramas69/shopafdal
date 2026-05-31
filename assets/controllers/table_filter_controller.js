import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['row', 'empty'];

    filter(event) {
        const q = event.target.value.trim().toLowerCase();
        let visible = 0;
        this.rowTargets.forEach((row) => {
            const hay = row.dataset.filterText || '';
            const match = q === '' || hay.includes(q);
            row.classList.toggle('hidden', !match);
            if (match) {
                visible++;
            }
        });
        if (this.hasEmptyTarget) {
            this.emptyTarget.classList.toggle('hidden', visible > 0);
        }
    }
}
