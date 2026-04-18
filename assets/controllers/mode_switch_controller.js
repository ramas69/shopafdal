import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['existing', 'new'];

    switch(event) {
        const mode = event.target.value;
        this.existingTarget.classList.toggle('hidden', mode !== 'existing');
        this.newTarget.classList.toggle('hidden', mode !== 'new');
    }
}
