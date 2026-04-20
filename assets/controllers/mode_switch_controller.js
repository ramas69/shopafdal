import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['existing', 'new', 'afdal'];

    switch(event) {
        const mode = event.target.value;
        if (this.hasExistingTarget) this.existingTarget.classList.toggle('hidden', mode !== 'existing');
        if (this.hasNewTarget) this.newTarget.classList.toggle('hidden', mode !== 'new');
        if (this.hasAfdalTarget) this.afdalTarget.classList.toggle('hidden', mode !== 'afdal');
    }
}
