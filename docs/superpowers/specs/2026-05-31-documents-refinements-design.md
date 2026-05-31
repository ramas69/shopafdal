# Spec — Raffinements Documents : auto-submit, suppression équipe, recherche

Date : 2026-05-31
Statut : approuvé (design), en attente plan d'implémentation
Dépend de : features « Dépôt de documents » + « Page Documents » déjà livrées.

## Objectif

Trois raffinements de la feature Documents :
- **A** — Upload auto-submit : le dépôt part dès la sélection du fichier (plus de bouton « Déposer »), avec un spinner dans la zone de dépôt.
- **B** — Suppression élargie : tout membre de la company (pas seulement l'auteur) peut supprimer un document tant que la commande n'est pas confirmée. Corrige au passage une faille potentielle cross-company.
- **C** — Barre de recherche sur la page Documents (filtrage client-side).

---

## Partie A — Upload auto-submit + spinner

### Nouveau controller Stimulus `assets/controllers/auto_upload_controller.js`

Responsabilité : ouvrir le sélecteur de fichier au clic sur la dropzone, et à la sélection d'un fichier, afficher un état de chargement puis soumettre le formulaire.

```javascript
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'dropzone'];

    pick(event) {
        event.preventDefault();
        this.inputTarget.click();
    }

    submit() {
        const file = this.inputTarget.files?.[0];
        if (!file) {
            return;
        }
        if (this.hasDropzoneTarget) {
            this.dropzoneTarget.disabled = true;
            this.dropzoneTarget.innerHTML = `
                <svg class="w-6 h-6 animate-spin text-[var(--color-primary)]" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity="0.25" stroke-width="3"/>
                    <path d="M22 12a10 10 0 0 1-10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                </svg>
                <span class="text-sm font-medium text-[var(--color-foreground)]">Envoi de ${this._escape(file.name)}…</span>
            `;
        }
        this.element.requestSubmit();
    }

    _escape(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }
}
```

### Bloc upload de `templates/order/_documents.html.twig` (uniquement `not is_admin`)

Remplace le form actuel (dropzone bat-upload + preview + bouton « Déposer ») par :

```twig
        <form method="post" enctype="multipart/form-data"
              action="{{ path('app_order_doc_upload', {reference: order.reference}) }}"
              data-controller="auto-upload">
            <input type="file" name="document" required
                   accept="application/pdf,image/jpeg,image/png,image/webp"
                   data-auto-upload-target="input"
                   data-action="change->auto-upload#submit"
                   class="sr-only">
            <button type="button"
                    data-auto-upload-target="dropzone"
                    data-action="click->auto-upload#pick"
                    class="w-full flex flex-col items-center justify-center gap-2 p-5 rounded-lg border-2 border-dashed border-[var(--color-border)] bg-white hover:border-[var(--color-primary)] hover:bg-[var(--color-primary)]/5 transition-colors cursor-pointer disabled:opacity-70 disabled:cursor-wait">
                <svg class="w-7 h-7 text-[var(--color-secondary)]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z"/></svg>
                <span class="text-sm font-medium text-[var(--color-foreground)]">Choisir un fichier</span>
                <span class="text-[10px] text-[var(--color-secondary)]">PDF · JPG · PNG · WebP · max 10 Mo</span>
            </button>
        </form>
```

Notes :
- Le champ garde `name="document"` (attendu par `OrderDocumentController::upload`). Le POST de test existant reste valide.
- `bat-upload` et `submit-loading` sont retirés de ce form (remplacés par `auto-upload`). Ces deux controllers restent utilisés ailleurs (BAT) — ne pas les supprimer.
- Aperçu de l'icône : visible (a) dans l'état de chargement (nom + spinner), (b) dans la liste après recharge (badge PDF/IMG par ligne).
- L'auto-submit est du comportement JS — non couvert par les tests fonctionnels (qui POST directement). Couvert par `lint:twig` + le test d'upload POST inchangé.

---

## Partie B — Suppression par tout membre de la company

### `OrderDocumentController::delete()`

Remplacer la condition d'accès actuelle (auteur + statut) par : **membre de la company de la commande (non admin) ET commande non confirmée** (`DRAFT` ou `PLACED`).

```php
        $order = $document->getOrder();
        /** @var User $user */
        $user = $this->getUser();

        $deletable = in_array($order->getStatus(), [OrderStatus::DRAFT, OrderStatus::PLACED], true);
        $sameCompany = !$user->isAdmin() && $order->getCompany()->getId() === $user->getCompany()?->getId();
        if (!$sameCompany || !$deletable) {
            throw $this->createAccessDeniedException();
        }
```

Sécurité :
- L'ancien code s'appuyait sur `uploadedBy === user` pour garantir l'appartenance. En retirant cette condition, on **doit** vérifier explicitement la company (sinon un membre d'une autre company pourrait supprimer via POST direct). La condition `$sameCompany` ferme cette faille.
- L'admin est exclu de la suppression (`!$user->isAdmin()`) : la suppression est une action client ; le bloc admin est déjà en lecture seule.
- Le reste de `delete()` (suppression fichier disque `@unlink` + `remove` + `flush` + flash + redirect) est inchangé.

### `templates/order/_documents.html.twig` — bouton supprimer

Condition actuelle :
```twig
{% if not is_admin and can_delete and doc.uploadedBy.id == app.user.id %}
```
Nouvelle condition (retire le check auteur) :
```twig
{% if not is_admin and can_delete %}
```
(`can_delete` = `order.status.value in ['draft', 'placed']`, déjà passé par la page détail. Tout membre voyant la commande est de la même company → cohérent avec la garde serveur.)

---

## Partie C — Barre de recherche (client-side) sur la page Documents

### Nouveau controller Stimulus `assets/controllers/table_filter_controller.js`

Responsabilité : filtrer les lignes d'un tableau selon une requête texte, en comparant à un attribut `data-filter-text` par ligne ; afficher/masquer un message « aucun résultat ».

```javascript
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
```

### `templates/order/_documents_table.html.twig`

Le partial évolue (params : `documents`, `order_route`, **`show_client`** bool). Structure cible :

1. **Barre de recherche** (au-dessus du tableau, dans le `data-controller="table-filter"` qui englobe la recherche + le tableau) :

```twig
{% if documents is empty %}
    <div class="card p-10 text-center">
        <h2 class="font-display text-lg font-semibold text-[var(--color-foreground)] mb-1">Aucun document</h2>
        <p class="text-sm text-[var(--color-secondary)]">Les documents déposés sur les commandes apparaîtront ici.</p>
    </div>
{% else %}
    <div data-controller="table-filter">
        <div class="mb-4">
            <input type="search" placeholder="Rechercher (commande, filiale{% if show_client %}, client{% endif %}, date, montant…)"
                   data-action="input->table-filter#filter"
                   class="form-input w-full max-w-md">
        </div>
        <div class="card overflow-hidden">
            <table class="w-full text-sm">
                <thead> … (colonnes ci-dessous) </thead>
                <tbody>
                    {% for doc in documents %}
                        <tr data-table-filter-target="row"
                            data-filter-text="{{ (doc.order.reference ~ ' ' ~ doc.order.antenna.name ~ ' ' ~ (show_client ? doc.order.company.name : '') ~ ' ' ~ doc.uploadedBy.fullName ~ ' ' ~ doc.originalName ~ ' ' ~ doc.createdAt|date('d/m/Y') ~ ' ' ~ (doc.order.totalCents / 100)|number_format(2, ',', ' ') ~ ' ' ~ doc.order.totalCents)|lower }}"
                            class="border-b border-[var(--color-border)] last:border-0 hover:bg-[var(--color-muted)]/40">
                            … (cellules ci-dessous) …
                        </tr>
                    {% endfor %}
                </tbody>
            </table>
            <div data-table-filter-target="empty" class="hidden p-8 text-center text-sm text-[var(--color-secondary)]">
                Aucun résultat pour cette recherche.
            </div>
        </div>
    </div>
{% endif %}
```

2. **Colonnes** (ordre) :
   - `Document` : badge PDF/IMG + nom (texte simple, non-lien — le téléchargement est dans la colonne Action).
   - `Client` : **uniquement si `show_client`** — `doc.order.company.name`.
   - `Commande` : lien `path(order_route, {reference: doc.order.reference})` → `doc.order.reference` + badge statut ; **sous-ligne** filiale : `doc.order.antenna.name` en petit `text-[var(--color-secondary)]`.
   - `Déposé par` : `doc.uploadedBy.fullName`.
   - `Date` : `doc.createdAt|date('d/m/Y')`.
   - `Montant` : `doc.order.totalCents|price` (filtre Twig `price` existant, ex. `order.totalCents|price`), aligné à droite.
   - `Taille` : `(doc.sizeBytes / 1024)|round` Ko, aligné à droite.
   - `Action` : lien « Télécharger » → `app_order_doc_download`.

   Les `<th>` correspondants suivent le style existant (`px-4 py-3 font-medium`, `text-right` pour Montant/Taille/Action). La colonne `Client` n'apparaît dans `<thead>` que `{% if show_client %}`.

3. **Champs recherchés** (via `data-filter-text`, déjà concaténés ci-dessus) : n° commande, filiale (antenne), nom client (si admin), déposant, nom fichier, date `d/m/Y`, montant (formaté `1 500,00` + valeur en centimes brute pour tolérer la saisie). Tout en minuscules.

### Pages

- `templates/documents/index.html.twig` (client) : include avec `show_client: false`.
- `templates/admin/documents/index.html.twig` (admin) : include avec `show_client: true`.
- Les includes conservent `with_context = false` ; ajouter `show_client` au tableau de params.

---

## Tests (functional, `tests/Functional/OrderDocumentTest.php`)

Partie B (mise à jour des tests de suppression) :
- Renommer/repurposer `testNonAuthorCannotDelete` → **`testCompanyMemberCanDeleteUntilConfirmed`** : un membre `MEMBER` de la même company (≠ auteur) supprime un doc sur commande `DRAFT` → succès (303 redirect, row supprimée).
- Ajouter **`testCrossCompanyMemberCannotDelete`** : un membre d'une autre company tente la suppression → `403`, row conservée.
- `testAuthorDeletesDocumentOnDraftOrder` : reste vert (l'auteur est membre de la company).
- `testDeleteForbiddenOnConfirmedOrder` : reste vert (commande `CONFIRMED` → 403, quel que soit le membre).
- Ajouter **`testAdminCannotDeleteDocument`** : un admin tente `delete` → `403` (suppression réservée aux clients de la company).

Partie C : la recherche est client-side (JS) → non testable en functional sans navigateur. Vérifier que les pages rendent toujours (tests `testClientDocumentsPageShowsOwnCompanyOnly`, `testAdminDocumentsPageListsAll` restent verts) + `lint:twig`.

Partie A : couvert par `lint:twig` + `testClientUploadsPdfDocument` (POST direct inchangé).

## Documentation

- Mettre à jour `AFDAL_DESIGN.md` (itération « Documents : auto-submit, suppression équipe, recherche »).

## Risques / points d'attention

- `doc.order.antenna` : `Order::getAntenna()` est non nullable (JoinColumn `nullable: false`) → toujours présent. `doc.order.company` idem.
- `data-filter-text` inclut le montant en deux formes (formaté `1 500,00` et centimes brut `150000`) pour tolérer différentes saisies utilisateur.
- Le filtre client-side n'agit que sur les lignes déjà chargées (pas de pagination dans cette feature) — acceptable au volume actuel.
- `auto_upload_controller` : si JS désactivé, le formulaire n'a plus de bouton submit visible → dépôt impossible sans JS. Acceptable (le reste de l'app dépend de Stimulus/Turbo). Pas de fallback no-JS demandé.
