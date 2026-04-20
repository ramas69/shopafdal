import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['rejectForm'];

    toggleReject() {
        if (!this.hasRejectFormTarget) return;
        this.rejectFormTarget.classList.toggle('hidden');
        const isOpen = !this.rejectFormTarget.classList.contains('hidden');
        if (isOpen) {
            this.rejectFormTarget.dataset.pollSkip = '';
            this.rejectFormTarget.querySelector('textarea')?.focus();
        } else {
            delete this.rejectFormTarget.dataset.pollSkip;
        }
    }
}
