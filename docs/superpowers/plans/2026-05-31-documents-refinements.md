# Raffinements Documents — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Upload auto-submit (spinner, sans bouton), suppression de documents par tout membre de la company tant que la commande n'est pas confirmée (avec fermeture d'une faille cross-company), et barre de recherche client-side sur la page Documents.

**Architecture:** Deux petits controllers Stimulus dédiés (`auto-upload`, `table-filter`), modification de la garde de `OrderDocumentController::delete()`, et refonte du partial table partagé (recherche + colonnes Client/Montant + sous-ligne filiale). Filtrage de recherche 100% client-side via attribut `data-filter-text` (le montant est calculé en PHP et le formatage de date est DB-spécifique → pas de SQL).

**Tech Stack:** Symfony 7.4, Twig, Stimulus, PHPUnit (WebTestCase + dama/doctrine-test-bundle).

**Convention :** commits ciblés (`git add <fichiers>`) — ne jamais stager les fichiers non liés présents dans le working tree.

---

## Fichiers

- Créer : `assets/controllers/auto_upload_controller.js`
- Créer : `assets/controllers/table_filter_controller.js`
- Modifier : `templates/order/_documents.html.twig` (bloc upload → auto-upload ; condition bouton supprimer)
- Modifier : `src/Controller/OrderDocumentController.php` (garde `delete()`)
- Modifier : `templates/order/_documents_table.html.twig` (recherche + colonnes + filiale)
- Modifier : `templates/documents/index.html.twig` + `templates/admin/documents/index.html.twig` (param `show_client`)
- Modifier : `tests/Functional/OrderDocumentTest.php` (tests suppression)
- Modifier : `AFDAL_DESIGN.md`

> Note tests : DB de test = PostgreSQL (`.env.test.local`, quirk connu). Helpers `createOrder`, `persistDocument`, `createCompanyWithAntenna`, `createUser` déjà présents.

---

## Task 1 : Partie A — Upload auto-submit + spinner

**Files:**
- Create: `assets/controllers/auto_upload_controller.js`
- Modify: `templates/order/_documents.html.twig`

- [ ] **Step 1 : Créer le controller Stimulus**

`assets/controllers/auto_upload_controller.js` :

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

- [ ] **Step 2 : Remplacer le bloc upload dans `_documents.html.twig`**

Le bloc actuel (dans `{% if not is_admin %}`) est un `<form ... data-controller="bat-upload submit-loading" ...>` contenant l'input, la dropzone, un bloc `preview` (`data-bat-upload-target="preview"`) et un bouton submit « Déposer ». **Remplacer tout ce `<form>...</form>`** par :

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

Laisser le `{% if not is_admin %} ... {% endif %}` autour, et la liste des documents au-dessus, inchangés.

- [ ] **Step 3 : Vérifier**

Run: `php bin/console lint:twig templates/order/_documents.html.twig`
Expected: OK.
Run: `php bin/phpunit --filter testClientUploadsPdfDocument`
Expected: PASS (le POST de test conserve `name="document"` ; l'auto-submit JS n'affecte pas le test serveur).

- [ ] **Step 4 : Commit**

```bash
git add assets/controllers/auto_upload_controller.js templates/order/_documents.html.twig
git commit -m "feat: upload document auto-submit à la sélection (spinner dropzone)"
```

---

## Task 2 : Partie B — Suppression par tout membre de la company (TDD)

**Files:**
- Modify: `tests/Functional/OrderDocumentTest.php`
- Modify: `src/Controller/OrderDocumentController.php`
- Modify: `templates/order/_documents.html.twig`

- [ ] **Step 1 : Mettre à jour / ajouter les tests (échouent)**

Dans `tests/Functional/OrderDocumentTest.php`, **remplacer entièrement** la méthode `testNonAuthorCannotDelete` (qui asserte aujourd'hui un 403 pour un membre non-auteur) par la version « membre peut supprimer » :

```php
    public function testCompanyMemberCanDeleteUntilConfirmed(): void
    {
        $client = static::createClient();
        [$company, $antenna] = $this->createCompanyWithAntenna();
        $owner = $this->createUser('client', $company, CompanyRole::OWNER);
        $other = $this->createUser('client', $company, CompanyRole::MEMBER);
        $order = $this->createOrder($company, $antenna, $owner, OrderStatus::DRAFT);
        $doc = $this->persistDocument($order, $owner);
        $docId = $doc->getId();

        $client->loginUser($other);
        $client->request('POST', '/commandes/documents/' . $docId . '/delete');

        self::assertResponseRedirects();
        $this->em()->clear();
        self::assertNull($this->em()->getRepository(OrderDocument::class)->find($docId));
    }
```

Puis **ajouter** ces deux tests :

```php
    public function testCrossCompanyMemberCannotDelete(): void
    {
        $client = static::createClient();
        [$companyA, $antennaA] = $this->createCompanyWithAntenna('Alpha');
        [$companyB] = $this->createCompanyWithAntenna('Beta');
        $ownerA = $this->createUser('client', $companyA, CompanyRole::OWNER);
        $outsiderB = $this->createUser('client', $companyB, CompanyRole::OWNER);
        $order = $this->createOrder($companyA, $antennaA, $ownerA, OrderStatus::DRAFT);
        $doc = $this->persistDocument($order, $ownerA);

        $client->loginUser($outsiderB);
        $client->request('POST', '/commandes/documents/' . $doc->getId() . '/delete');

        self::assertResponseStatusCodeSame(403);
        self::assertNotNull($this->em()->getRepository(OrderDocument::class)->find($doc->getId()));
    }

    public function testAdminCannotDeleteDocument(): void
    {
        $client = static::createClient();
        [$company, $antenna] = $this->createCompanyWithAntenna();
        $owner = $this->createUser('client', $company, CompanyRole::OWNER);
        $admin = $this->createUser('admin');
        $order = $this->createOrder($company, $antenna, $owner, OrderStatus::DRAFT);
        $doc = $this->persistDocument($order, $owner);

        $client->loginUser($admin);
        $client->request('POST', '/commandes/documents/' . $doc->getId() . '/delete');

        self::assertResponseStatusCodeSame(403);
        self::assertNotNull($this->em()->getRepository(OrderDocument::class)->find($doc->getId()));
    }
```

(Garder `testAuthorDeletesDocumentOnDraftOrder` et `testDeleteForbiddenOnConfirmedOrder` tels quels.)

- [ ] **Step 2 : Lancer → échec attendu**

Run: `php bin/phpunit --filter "testCompanyMemberCanDeleteUntilConfirmed|testCrossCompanyMemberCannotDelete|testAdminCannotDeleteDocument"`
Expected: `testCompanyMemberCanDeleteUntilConfirmed` FAILS (statut 403 actuel car le membre n'est pas l'auteur → la row reste, `assertNull` échoue). Les deux autres peuvent déjà passer (l'ancienne garde refuse cross-company via le check auteur, et l'admin n'est pas l'auteur) — ils seront re-vérifiés après le changement.

- [ ] **Step 3 : Modifier la garde de `delete()`**

Dans `src/Controller/OrderDocumentController.php`, méthode `delete()`, remplacer :

```php
        $deletable = in_array($order->getStatus(), [OrderStatus::DRAFT, OrderStatus::PLACED], true);
        if ($document->getUploadedBy()->getId() !== $user->getId() || !$deletable) {
            throw $this->createAccessDeniedException();
        }
```

par :

```php
        $deletable = in_array($order->getStatus(), [OrderStatus::DRAFT, OrderStatus::PLACED], true);
        $sameCompany = !$user->isAdmin() && $order->getCompany()->getId() === $user->getCompany()?->getId();
        if (!$sameCompany || !$deletable) {
            throw $this->createAccessDeniedException();
        }
```

- [ ] **Step 4 : Mettre à jour la condition du bouton supprimer**

Dans `templates/order/_documents.html.twig`, remplacer :

```twig
                    {% if not is_admin and can_delete and doc.uploadedBy.id == app.user.id %}
```

par :

```twig
                    {% if not is_admin and can_delete %}
```

- [ ] **Step 5 : Lancer → succès attendu**

Run: `php bin/phpunit --filter "testCompanyMemberCanDeleteUntilConfirmed|testCrossCompanyMemberCannotDelete|testAdminCannotDeleteDocument|testAuthorDeletesDocumentOnDraftOrder|testDeleteForbiddenOnConfirmedOrder"`
Expected: PASS (5 tests).
Run: `php bin/console lint:twig templates/order/_documents.html.twig`
Expected: OK.

- [ ] **Step 6 : Commit**

```bash
git add src/Controller/OrderDocumentController.php templates/order/_documents.html.twig tests/Functional/OrderDocumentTest.php
git commit -m "feat: suppression document par tout membre de la company (fix garde cross-company)"
```

---

## Task 3 : Partie C — Barre de recherche client-side

**Files:**
- Create: `assets/controllers/table_filter_controller.js`
- Modify: `templates/order/_documents_table.html.twig`
- Modify: `templates/documents/index.html.twig`
- Modify: `templates/admin/documents/index.html.twig`

- [ ] **Step 1 : Créer le controller Stimulus**

`assets/controllers/table_filter_controller.js` :

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

- [ ] **Step 2 : Réécrire `templates/order/_documents_table.html.twig`**

Remplacer **tout le contenu** du fichier par :

```twig
{# @param documents (OrderDocument[])
   @param order_route (string) route name for the order detail link
   @param show_client (bool) afficher la colonne Client (nom de la company) — admin
   @note download uses app_order_doc_download which already authorizes both client (same company) and admin #}
{% if documents is empty %}
    <div class="card p-10 text-center">
        <h2 class="font-display text-lg font-semibold text-[var(--color-foreground)] mb-1">Aucun document</h2>
        <p class="text-sm text-[var(--color-secondary)]">Les documents déposés sur les commandes apparaîtront ici.</p>
    </div>
{% else %}
    <div data-controller="table-filter">
        <div class="mb-4">
            <input type="search"
                   placeholder="Rechercher (commande, filiale{% if show_client %}, client{% endif %}, date, montant…)"
                   data-action="input->table-filter#filter"
                   class="form-input w-full max-w-md">
        </div>
        <div class="card overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-[var(--color-border)] text-left text-xs uppercase tracking-wide text-[var(--color-secondary)]">
                        <th class="px-4 py-3 font-medium">Document</th>
                        {% if show_client %}<th class="px-4 py-3 font-medium">Client</th>{% endif %}
                        <th class="px-4 py-3 font-medium">Commande</th>
                        <th class="px-4 py-3 font-medium">Déposé par</th>
                        <th class="px-4 py-3 font-medium">Date</th>
                        <th class="px-4 py-3 font-medium text-right">Montant</th>
                        <th class="px-4 py-3 font-medium text-right">Taille</th>
                        <th class="px-4 py-3 font-medium text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    {% for doc in documents %}
                        <tr data-table-filter-target="row"
                            data-filter-text="{{ (doc.order.reference ~ ' ' ~ doc.order.antenna.name ~ ' ' ~ (show_client ? doc.order.company.name : '') ~ ' ' ~ doc.uploadedBy.fullName ~ ' ' ~ doc.originalName ~ ' ' ~ doc.createdAt|date('d/m/Y') ~ ' ' ~ (doc.order.totalCents / 100)|number_format(2, ',', ' ') ~ ' ' ~ doc.order.totalCents)|lower }}"
                            class="border-b border-[var(--color-border)] last:border-0 hover:bg-[var(--color-muted)]/40">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <span class="shrink-0 w-9 h-9 rounded-md bg-[var(--color-muted)] flex items-center justify-center text-xs font-semibold text-[var(--color-secondary)]">
                                        {{ doc.isPdf ? 'PDF' : 'IMG' }}
                                    </span>
                                    <span class="font-medium text-[var(--color-foreground)] truncate">{{ doc.originalName }}</span>
                                </div>
                            </td>
                            {% if show_client %}
                                <td class="px-4 py-3 text-[var(--color-foreground)]">{{ doc.order.company.name }}</td>
                            {% endif %}
                            <td class="px-4 py-3">
                                <a href="{{ path(order_route, {reference: doc.order.reference}) }}"
                                   class="inline-flex flex-col gap-0.5 hover:underline">
                                    <span class="inline-flex items-center gap-2">
                                        <span class="font-medium text-[var(--color-foreground)]">{{ doc.order.reference }}</span>
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium text-white"
                                              style="background-color: {{ doc.order.status.progressColor() }};">{{ doc.order.status.label() }}</span>
                                    </span>
                                    <span class="text-xs text-[var(--color-secondary)]">{{ doc.order.antenna.name }}</span>
                                </a>
                            </td>
                            <td class="px-4 py-3 text-[var(--color-body)]">{{ doc.uploadedBy.fullName }}</td>
                            <td class="px-4 py-3 text-[var(--color-secondary)]">{{ doc.createdAt|date('d/m/Y') }}</td>
                            <td class="px-4 py-3 text-right text-[var(--color-foreground)]">{{ doc.order.totalCents|price }}</td>
                            <td class="px-4 py-3 text-right text-[var(--color-secondary)]">{{ (doc.sizeBytes / 1024)|round }} Ko</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ path('app_order_doc_download', {document: doc.id}) }}"
                                   class="inline-flex items-center gap-1.5 text-xs font-medium text-[var(--color-primary)] hover:underline">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                                    Télécharger
                                </a>
                            </td>
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

- [ ] **Step 3 : Passer `show_client` dans la page client**

Dans `templates/documents/index.html.twig`, l'include actuel est :

```twig
{{ include('order/_documents_table.html.twig', {documents: documents, order_route: 'app_order_detail'}, with_context = false) }}
```

Le remplacer par :

```twig
{{ include('order/_documents_table.html.twig', {documents: documents, order_route: 'app_order_detail', show_client: false}, with_context = false) }}
```

- [ ] **Step 4 : Passer `show_client` dans la page admin**

Dans `templates/admin/documents/index.html.twig`, remplacer l'include par :

```twig
{{ include('order/_documents_table.html.twig', {documents: documents, order_route: 'app_admin_order_detail', show_client: true}, with_context = false) }}
```

- [ ] **Step 5 : Vérifier**

Run: `php bin/console lint:twig templates/order/_documents_table.html.twig templates/documents/index.html.twig templates/admin/documents/index.html.twig`
Expected: OK.
Run: `php bin/phpunit --filter "testClientDocumentsPageShowsOwnCompanyOnly|testAdminDocumentsPageListsAll"`
Expected: PASS (les pages rendent toujours avec la recherche + nouvelles colonnes).

- [ ] **Step 6 : Commit**

```bash
git add assets/controllers/table_filter_controller.js templates/order/_documents_table.html.twig templates/documents/index.html.twig templates/admin/documents/index.html.twig
git commit -m "feat: barre de recherche client-side sur la page documents (+ colonnes client/montant/filiale)"
```

---

## Task 4 : Documentation

**Files:**
- Modify: `AFDAL_DESIGN.md`

- [ ] **Step 1 : Ajouter une itération**

Dans `AFDAL_DESIGN.md`, section « Itérations post-livraison », après le bloc « Page Documents + UX upload », ajouter :

```markdown
**Documents : auto-submit, suppression équipe, recherche** (2026-05-31) :
- Upload : auto-submit à la sélection du fichier (controller Stimulus `auto-upload`, spinner dans la dropzone) — plus de bouton « Déposer ». Remplace `bat-upload`/`submit-loading` sur ce form.
- Suppression : élargie à **tout membre de la company** (plus seulement l'auteur) tant que la commande est `DRAFT`/`PLACED`. Garde `delete()` durcie : `!isAdmin && order.company === user.company && deletable` (ferme une faille cross-company et exclut l'admin).
- Recherche : barre de recherche client-side sur la page Documents (controller `table-filter`, filtrage sur `data-filter-text` : n° commande, filiale, client [admin], déposant, fichier, date, montant). Colonnes ajoutées : Montant, filiale en sous-ligne, et Client (admin via param `show_client`).
```

- [ ] **Step 2 : Commit**

```bash
git add AFDAL_DESIGN.md
git commit -m "docs: itération documents (auto-submit, suppression équipe, recherche)"
```

---

## Vérification finale

- [ ] **Tests feature verts**

Run: `php bin/phpunit --filter OrderDocumentTest`
Expected: OK (tous, anciens + nouveaux).

- [ ] **Suite complète (pas de régression vs baseline)**

Run: `php bin/phpunit`
Expected: 6 échecs pré-existants liés au test DB PostgreSQL (`DashboardController` DATEDIFF + redirects), aucun nouvel échec.

- [ ] **Lint Twig**

Run: `php bin/console lint:twig templates/order templates/documents templates/admin/documents`
Expected: OK.

- [ ] **Rappel prod** : `php bin/console asset-map:compile` (2 nouveaux controllers Stimulus `auto-upload` + `table-filter` → doivent être compilés dans l'importmap au déploiement).
