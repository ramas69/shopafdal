import { Controller } from '@hotwired/stimulus';

export default class FavoriteButtonController extends Controller {
    static values = { url: String, favorited: Boolean };
    static targets = ['icon', 'label'];

    async toggle(event) {
        event.preventDefault();
        event.stopPropagation();
        const next = !this.favoritedValue;
        this._render(next);
        try {
            const res = await fetch(this.urlValue, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!res.ok) throw new Error('fail');
            const data = await res.json();
            this._render(data.favorited);
        } catch (err) {
            console.warn('Favorite toggle failed', err);
            this._render(!next);
        }
    }

    _render(state) {
        this.favoritedValue = state;
        this.element.setAttribute('aria-pressed', state ? 'true' : 'false');
        this.element.title = state ? 'Retirer des favoris' : 'Ajouter aux favoris';
        if (this.hasIconTarget) {
            this.iconTarget.setAttribute('fill', state ? 'currentColor' : 'none');
        }
        if (this.hasLabelTarget) {
            this.labelTarget.textContent = state ? 'Favori' : 'Ajouter aux favoris';
        }
        this.element.classList.toggle('text-[var(--color-destructive)]', state);
        this.element.classList.toggle('text-[var(--color-secondary)]', !state);
    }
}
