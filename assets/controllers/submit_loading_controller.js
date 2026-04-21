import { Controller } from '@hotwired/stimulus';

/**
 * À appliquer sur un <form> avec data-controller="submit-loading" + data-action="submit->submit-loading#start".
 * Au submit, remplace le contenu du bouton cliqué par un spinner + texte de chargement
 * (data-loading-text sur le bouton, sinon "Chargement…"), et désactive tous les submits du form.
 */
export default class extends Controller {
    start(event) {
        const submitter = event.submitter;
        if (!submitter) return;

        const loadingText = submitter.dataset.loadingText || 'Chargement…';
        const form = this.element;

        // IMPORTANT : on diffère la mutation DOM à la microtâche suivante (setTimeout 0)
        // pour que le navigateur ait fini de construire le FormData AVEC la valeur
        // du submitter (name=action, value=test|save). Si on désactive/mute
        // synchrone, certains navigateurs excluent le submitter du POST et l'action
        // arrive vide côté serveur.
        setTimeout(() => {
            submitter.dataset.originalHtml = submitter.innerHTML;
            submitter.innerHTML = `
                <svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity="0.25" stroke-width="3"/>
                    <path d="M22 12a10 10 0 0 1-10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                </svg>
                <span>${this._escape(loadingText)}</span>
            `;

            form.querySelectorAll('button[type=submit]').forEach((btn) => {
                btn.disabled = true;
                btn.classList.add('opacity-70', 'cursor-not-allowed');
            });
        }, 0);
    }

    _escape(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }
}
