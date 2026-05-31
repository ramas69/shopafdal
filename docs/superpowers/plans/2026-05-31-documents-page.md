# Page Documents + déplacement bloc + UX upload — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ajouter une page Documents listant tous les documents liés aux commandes (client scopé à sa company, admin tout), déplacer le bloc documents sous la conversation sur la page commande, et enrichir l'upload (icône fichier + spinner) via les controllers Stimulus existants.

**Architecture:** Deux méthodes de repository (`findForCompany`, `findAllRecent`), deux controllers fins (`DocumentController` client + `Admin\DocumentController`), un partial table partagé, deux pages qui étendent le shell dashboard. Le téléchargement réutilise la route existante `app_order_doc_download`. L'UX upload réutilise les controllers Stimulus `bat-upload` + `submit-loading` (aucun nouveau JS).

**Tech Stack:** Symfony 7.4, Doctrine ORM 3, MySQL/MariaDB, Twig, Stimulus, PHPUnit (WebTestCase + dama/doctrine-test-bundle).

**Convention routes :** controllers avec préfixe de classe `#[Route('/...')]` + `#[IsGranted(...)]` (cf. `FavoriteController`, `Admin\OrderController`).

---

## Fichiers

- Modifier : `src/Repository/OrderDocumentRepository.php` (méthodes `findForCompany`, `findAllRecent`)
- Créer : `src/Controller/DocumentController.php` (client, `/documents`)
- Créer : `src/Controller/Admin/DocumentController.php` (admin, `/admin/documents`)
- Créer : `templates/order/_documents_table.html.twig` (partial table partagé)
- Créer : `templates/documents/index.html.twig` (page client)
- Créer : `templates/admin/documents/index.html.twig` (page admin)
- Modifier : `templates/dashboard/_shell.html.twig` (2 entrées nav + cas `file` dans macro `nav_icon`)
- Modifier : `templates/order/detail.html.twig` (déplacer le bloc documents dans l'aside)
- Modifier : `templates/order/_documents.html.twig` (upload → dropzone bat-upload + submit-loading)
- Modifier : `tests/Functional/OrderDocumentTest.php` (tests repo + pages)
- Modifier : `AFDAL_DESIGN.md`

> Note tests : le test local tourne sur PostgreSQL (`.env.test.local`) — quirk connu, table `order_documents` déjà présente. Les helpers `createOrder`, `persistDocument`, `tmpFile` existent déjà dans `OrderDocumentTest` et sont réutilisés.

---

## Task 1 : Repository — `findForCompany` + `findAllRecent` (TDD)

**Files:**
- Modify: `tests/Functional/OrderDocumentTest.php`
- Modify: `src/Repository/OrderDocumentRepository.php`

- [ ] **Step 1 : Écrire le test (échoue)**

Ajouter dans `OrderDocumentTest` (la classe importe déjà `Order`, `OrderDocument`, `CompanyRole`, `OrderStatus` ; ajouter l'import `use App\Repository\OrderDocumentRepository;` en tête si absent) :

```php
    public function testFindForCompanyScopesAndOrders(): void
    {
        self::bootKernel();
        [$companyA, $antennaA] = $this->createCompanyWithAntenna('Alpha');
        [$companyB, $antennaB] = $this->createCompanyWithAntenna('Beta');
        $ownerA = $this->createUser('client', $companyA, CompanyRole::OWNER);
        $ownerB = $this->createUser('client', $companyB, CompanyRole::OWNER);
        $orderA = $this->createOrder($companyA, $antennaA, $ownerA);
        $orderB = $this->createOrder($companyB, $antennaB, $ownerB);
        $this->persistDocument($orderA, $ownerA, 'alpha-1.pdf');
        $this->persistDocument($orderB, $ownerB, 'beta-1.pdf');

        $repo = static::getContainer()->get(OrderDocumentRepository::class);
        $docsA = $repo->findForCompany($companyA);

        self::assertCount(1, $docsA);
        self::assertSame('alpha-1.pdf', $docsA[0]->getOriginalName());
    }

    public function testFindAllRecentReturnsEveryDocument(): void
    {
        self::bootKernel();
        [$companyA, $antennaA] = $this->createCompanyWithAntenna('Alpha');
        [$companyB, $antennaB] = $this->createCompanyWithAntenna('Beta');
        $ownerA = $this->createUser('client', $companyA, CompanyRole::OWNER);
        $ownerB = $this->createUser('client', $companyB, CompanyRole::OWNER);
        $this->persistDocument($this->createOrder($companyA, $antennaA, $ownerA), $ownerA, 'a.pdf');
        $this->persistDocument($this->createOrder($companyB, $antennaB, $ownerB), $ownerB, 'b.pdf');

        $repo = static::getContainer()->get(OrderDocumentRepository::class);
        self::assertGreaterThanOrEqual(2, count($repo->findAllRecent()));
    }
```

- [ ] **Step 2 : Lancer → échec attendu**

Run: `php bin/phpunit --filter "testFindForCompanyScopesAndOrders|testFindAllRecentReturnsEveryDocument"`
Expected: FAIL — `Error: Call to undefined method ...::findForCompany()`.

- [ ] **Step 3 : Implémenter les méthodes**

Dans `src/Repository/OrderDocumentRepository.php`, ajouter l'import `use App\Entity\Company;` en tête, puis ces méthodes dans la classe :

```php
    /** @return OrderDocument[] */
    public function findForCompany(Company $company): array
    {
        return $this->createQueryBuilder('d')
            ->join('d.order', 'o')
            ->andWhere('o.company = :company')
            ->setParameter('company', $company)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return OrderDocument[] */
    public function findAllRecent(): array
    {
        return $this->createQueryBuilder('d')
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
```

- [ ] **Step 4 : Lancer → succès attendu**

Run: `php bin/phpunit --filter "testFindForCompanyScopesAndOrders|testFindAllRecentReturnsEveryDocument"`
Expected: PASS (2 tests).

- [ ] **Step 5 : Commit**

```bash
git add src/Repository/OrderDocumentRepository.php tests/Functional/OrderDocumentTest.php
git commit -m "feat: requêtes documents par company + globale"
```

---

## Task 2 : Page client + partial table partagé (TDD)

**Files:**
- Modify: `tests/Functional/OrderDocumentTest.php`
- Create: `src/Controller/DocumentController.php`
- Create: `templates/order/_documents_table.html.twig`
- Create: `templates/documents/index.html.twig`

- [ ] **Step 1 : Écrire le test (échoue)**

Ajouter dans `OrderDocumentTest` :

```php
    public function testClientDocumentsPageShowsOwnCompanyOnly(): void
    {
        $client = static::createClient();
        [$companyA, $antennaA] = $this->createCompanyWithAntenna('Alpha');
        [$companyB, $antennaB] = $this->createCompanyWithAntenna('Beta');
        $ownerA = $this->createUser('client', $companyA, CompanyRole::OWNER);
        $ownerB = $this->createUser('client', $companyB, CompanyRole::OWNER);
        $this->persistDocument($this->createOrder($companyA, $antennaA, $ownerA), $ownerA, 'alpha-doc.pdf');
        $this->persistDocument($this->createOrder($companyB, $antennaB, $ownerB), $ownerB, 'beta-doc.pdf');

        $client->loginUser($ownerA);
        $crawler = $client->request('GET', '/documents');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('alpha-doc.pdf', $client->getResponse()->getContent());
        self::assertStringNotContainsString('beta-doc.pdf', $client->getResponse()->getContent());
    }
```

- [ ] **Step 2 : Lancer → échec attendu**

Run: `php bin/phpunit --filter testClientDocumentsPageShowsOwnCompanyOnly`
Expected: FAIL — route `/documents` inexistante (404).

- [ ] **Step 3 : Créer le controller client**

`src/Controller/DocumentController.php` :

```php
<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\OrderDocumentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/documents')]
#[IsGranted('ROLE_CLIENT_MANAGER')]
final class DocumentController extends AbstractController
{
    #[Route('', name: 'app_documents', methods: ['GET'])]
    public function index(OrderDocumentRepository $documents): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('documents/index.html.twig', [
            'documents' => $documents->findForCompany($user->getCompany()),
        ]);
    }
}
```

- [ ] **Step 4 : Créer le partial table partagé**

`templates/order/_documents_table.html.twig` :

```twig
{# @param documents (OrderDocument[]), order_route (string: route name to the order detail) #}
{% if documents is empty %}
    <div class="card p-10 text-center">
        <h2 class="font-display text-lg font-semibold text-[var(--color-foreground)] mb-1">Aucun document</h2>
        <p class="text-sm text-[var(--color-secondary)]">Les documents déposés sur les commandes apparaîtront ici.</p>
    </div>
{% else %}
    <div class="card overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-[var(--color-border)] text-left text-xs uppercase tracking-wide text-[var(--color-secondary)]">
                    <th class="px-4 py-3 font-medium">Document</th>
                    <th class="px-4 py-3 font-medium">Commande</th>
                    <th class="px-4 py-3 font-medium">Déposé par</th>
                    <th class="px-4 py-3 font-medium">Date</th>
                    <th class="px-4 py-3 font-medium text-right">Taille</th>
                    <th class="px-4 py-3 font-medium text-right">Action</th>
                </tr>
            </thead>
            <tbody>
                {% for doc in documents %}
                    <tr class="border-b border-[var(--color-border)] last:border-0 hover:bg-[var(--color-muted)]/40">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="shrink-0 w-9 h-9 rounded-md bg-[var(--color-muted)] flex items-center justify-center text-xs font-semibold text-[var(--color-secondary)]">
                                    {{ doc.isPdf ? 'PDF' : 'IMG' }}
                                </span>
                                <a href="{{ path('app_order_doc_download', {document: doc.id}) }}"
                                   class="font-medium text-[var(--color-primary)] hover:underline truncate">{{ doc.originalName }}</a>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <a href="{{ path(order_route, {reference: doc.order.reference}) }}"
                               class="inline-flex items-center gap-2 hover:underline">
                                <span class="font-medium text-[var(--color-foreground)]">{{ doc.order.reference }}</span>
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium text-white"
                                      style="background-color: {{ doc.order.status.progressColor() }};">{{ doc.order.status.label() }}</span>
                            </a>
                        </td>
                        <td class="px-4 py-3 text-[var(--color-body)]">{{ doc.uploadedBy.fullName }}</td>
                        <td class="px-4 py-3 text-[var(--color-secondary)]">{{ doc.createdAt|date('d/m/Y') }}</td>
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
    </div>
{% endif %}
```

- [ ] **Step 5 : Créer la page client**

`templates/documents/index.html.twig` :

```twig
{% extends 'dashboard/_shell.html.twig' %}

{% block title %}Documents — Afdal{% endblock %}

{% block content %}
<div class="mb-8">
    <div class="text-xs font-medium text-[var(--color-secondary)] uppercase tracking-wide mb-1">Documents</div>
    <h1 class="font-display text-2xl font-semibold text-[var(--color-foreground)]">{{ documents|length }} document{{ documents|length > 1 ? 's' : '' }}</h1>
    <p class="text-[var(--color-secondary)] text-sm mt-1">déposés sur les commandes de {{ app.user.company.name }}</p>
</div>

{{ include('order/_documents_table.html.twig', {documents: documents, order_route: 'app_order_detail'}) }}
{% endblock %}
```

- [ ] **Step 6 : Lancer → succès attendu**

Run: `php bin/phpunit --filter testClientDocumentsPageShowsOwnCompanyOnly`
Expected: PASS.
Run: `php bin/console lint:twig templates/order/_documents_table.html.twig templates/documents/index.html.twig`
Expected: OK.

- [ ] **Step 7 : Commit**

```bash
git add src/Controller/DocumentController.php templates/order/_documents_table.html.twig templates/documents/index.html.twig tests/Functional/OrderDocumentTest.php
git commit -m "feat: page documents client (scopée à la company)"
```

---

## Task 3 : Page admin (TDD)

**Files:**
- Modify: `tests/Functional/OrderDocumentTest.php`
- Create: `src/Controller/Admin/DocumentController.php`
- Create: `templates/admin/documents/index.html.twig`

- [ ] **Step 1 : Écrire les tests (échouent)**

Ajouter dans `OrderDocumentTest` :

```php
    public function testAdminDocumentsPageListsAll(): void
    {
        $client = static::createClient();
        [$companyA, $antennaA] = $this->createCompanyWithAntenna('Alpha');
        $ownerA = $this->createUser('client', $companyA, CompanyRole::OWNER);
        $admin = $this->createUser('admin');
        $this->persistDocument($this->createOrder($companyA, $antennaA, $ownerA), $ownerA, 'alpha-doc.pdf');

        $client->loginUser($admin);
        $client->request('GET', '/admin/documents');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('alpha-doc.pdf', $client->getResponse()->getContent());
    }

    public function testClientCannotAccessAdminDocuments(): void
    {
        $client = static::createClient();
        [$company] = $this->createCompanyWithAntenna();
        $user = $this->createUser('client', $company, CompanyRole::OWNER);

        $client->loginUser($user);
        $client->request('GET', '/admin/documents');

        self::assertResponseStatusCodeSame(403);
    }
```

- [ ] **Step 2 : Lancer → échec attendu**

Run: `php bin/phpunit --filter "testAdminDocumentsPageListsAll|testClientCannotAccessAdminDocuments"`
Expected: FAIL — `testAdminDocumentsPageListsAll` 404 (route absente). (`testClientCannotAccessAdminDocuments` peut déjà passer en 404→ mais attend 403 ; il passera après création de la route sous `/admin`.)

- [ ] **Step 3 : Créer le controller admin**

`src/Controller/Admin/DocumentController.php` :

```php
<?php

namespace App\Controller\Admin;

use App\Repository\OrderDocumentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/documents')]
#[IsGranted('ROLE_ADMIN')]
final class DocumentController extends AbstractController
{
    #[Route('', name: 'app_admin_documents', methods: ['GET'])]
    public function index(OrderDocumentRepository $documents): Response
    {
        return $this->render('admin/documents/index.html.twig', [
            'documents' => $documents->findAllRecent(),
        ]);
    }
}
```

- [ ] **Step 4 : Créer la page admin**

`templates/admin/documents/index.html.twig` :

```twig
{% extends 'dashboard/_shell.html.twig' %}

{% block title %}Documents — Afdal Admin{% endblock %}

{% block content %}
<div class="mb-8">
    <div class="text-xs font-medium text-[var(--color-secondary)] uppercase tracking-wide mb-1">Documents</div>
    <h1 class="font-display text-2xl font-semibold text-[var(--color-foreground)]">{{ documents|length }} document{{ documents|length > 1 ? 's' : '' }}</h1>
    <p class="text-[var(--color-secondary)] text-sm mt-1">tous les documents déposés par les clients</p>
</div>

{{ include('order/_documents_table.html.twig', {documents: documents, order_route: 'app_admin_order_detail'}) }}
{% endblock %}
```

- [ ] **Step 5 : Lancer → succès attendu**

Run: `php bin/phpunit --filter "testAdminDocumentsPageListsAll|testClientCannotAccessAdminDocuments"`
Expected: PASS (2 tests).
Run: `php bin/console lint:twig templates/admin/documents/index.html.twig`
Expected: OK.

- [ ] **Step 6 : Commit**

```bash
git add src/Controller/Admin/DocumentController.php templates/admin/documents/index.html.twig tests/Functional/OrderDocumentTest.php
git commit -m "feat: page documents admin (toutes les commandes)"
```

---

## Task 4 : Navigation — entrées + icône `file`

**Files:**
- Modify: `templates/dashboard/_shell.html.twig`

- [ ] **Step 1 : Ajouter l'entrée nav client**

Dans `templates/dashboard/_shell.html.twig`, dans le tableau `nav_items` client (branche `: [`),
juste après la ligne `{ route: 'app_orders', label: 'Mes commandes', icon: 'list' },` ajouter :

```twig
    { route: 'app_documents', label: 'Documents', icon: 'file' },
```

- [ ] **Step 2 : Ajouter l'entrée nav admin**

Dans le même fichier, dans le tableau `nav_items` admin (branche `is_admin ? [`),
juste après la ligne `{ route: 'app_admin_orders', label: 'Commandes', icon: 'list' },` ajouter :

```twig
    { route: 'app_admin_documents', label: 'Documents', icon: 'file' },
```

- [ ] **Step 3 : Ajouter le cas `file` dans la macro `nav_icon`**

Dans la macro `{% macro nav_icon(name) %}`, avant le `{% endif %}` final (après le dernier `{% elseif name == 'building' %}` … bloc), ajouter une branche cohérente avec les autres icônes (mêmes classes `h-5 w-5` que les SVG voisins de la macro — vérifier la taille utilisée par les cas existants et la reprendre) :

```twig
    {% elseif name == 'file' %}
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
```

(Si les SVG voisins utilisent une autre taille de classe, p.ex. `w-[18px] h-[18px]`, reprendre exactement la même pour rester homogène.)

- [ ] **Step 4 : Vérifier le rendu des pages**

Run: `php bin/console lint:twig templates/dashboard/_shell.html.twig`
Expected: OK.
Run: `php bin/phpunit --filter "testClientDocumentsPageShowsOwnCompanyOnly|testAdminDocumentsPageListsAll"`
Expected: PASS (les pages rendent le shell avec la nouvelle nav sans erreur).

- [ ] **Step 5 : Commit**

```bash
git add templates/dashboard/_shell.html.twig
git commit -m "feat: entrée Documents dans la navigation (client + admin)"
```

---

## Task 5 : Déplacer le bloc documents sous la conversation

**Files:**
- Modify: `templates/order/detail.html.twig`

- [ ] **Step 1 : Retirer le bloc de la colonne principale**

Dans `templates/order/detail.html.twig`, repérer l'include actuel dans la colonne principale
(`lg:col-span-2`), vers la ligne 245 :

```twig
        {{ include('order/_documents.html.twig', {
            order: order,
            can_delete: order.status.value in ['draft', 'placed'],
            is_admin: false,
        }) }}
```

Le **supprimer** de cet emplacement (couper le bloc complet).

- [ ] **Step 2 : Ré-insérer sous la conversation dans l'aside**

Toujours dans `templates/order/detail.html.twig`, repérer dans l'`aside` la ligne :

```twig
        {{ include('order/_conversation.html.twig', {order: order, messages: messages|default([]), is_admin: false}) }}
```

Insérer **immédiatement après** cette ligne le bloc documents coupé à l'étape 1 :

```twig
        {{ include('order/_documents.html.twig', {
            order: order,
            can_delete: order.status.value in ['draft', 'placed'],
            is_admin: false,
        }) }}
```

- [ ] **Step 3 : Vérifier**

Run: `php bin/console lint:twig templates/order/detail.html.twig`
Expected: OK.
Run: `php bin/phpunit --filter "testClientUploadsPdfDocument|testAuthorDeletesDocumentOnDraftOrder"`
Expected: PASS (le flux upload/suppression depuis la page détail reste fonctionnel).

- [ ] **Step 4 : Commit**

```bash
git add templates/order/detail.html.twig
git commit -m "feat: bloc documents déplacé sous la conversation (page commande)"
```

---

## Task 6 : UX upload — dropzone (icône fichier) + spinner

**Files:**
- Modify: `templates/order/_documents.html.twig`

- [ ] **Step 1 : Remplacer le bloc d'upload**

Dans `templates/order/_documents.html.twig`, repérer le bloc d'upload actuel (entre `{% if not is_admin %}`
et le `{% endif %}` de fin), qui contient le `<form method="post" enctype="multipart/form-data" ...>`
avec l'`<input type="file" name="document" ...>` et le bouton « Déposer », ainsi que le `<p>` de hint.
Remplacer **tout ce bloc form + hint** (en conservant le `{% if not is_admin %} ... {% endif %}` autour) par :

```twig
        <form method="post" enctype="multipart/form-data"
              action="{{ path('app_order_doc_upload', {reference: order.reference}) }}"
              class="space-y-3"
              data-controller="bat-upload submit-loading"
              data-action="submit->submit-loading#start">
            <input type="file" name="document" required
                   accept="application/pdf,image/jpeg,image/png,image/webp"
                   data-bat-upload-target="input"
                   data-action="change->bat-upload#select"
                   class="sr-only">

            <button type="button"
                    data-bat-upload-target="dropzone"
                    data-action="click->bat-upload#pick"
                    class="w-full flex flex-col items-center justify-center gap-2 p-5 rounded-lg border-2 border-dashed border-[var(--color-border)] bg-white hover:border-[var(--color-primary)] hover:bg-[var(--color-primary)]/5 transition-colors cursor-pointer">
                <svg class="w-7 h-7 text-[var(--color-secondary)]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z"/></svg>
                <span class="text-sm font-medium text-[var(--color-foreground)]">Choisir un fichier</span>
                <span class="text-[10px] text-[var(--color-secondary)]">PDF · JPG · PNG · WebP · max 10 Mo</span>
            </button>

            <div data-bat-upload-target="preview" class="hidden rounded-lg border border-[var(--color-border)] bg-white p-3">
                <div class="flex items-start gap-3">
                    <div data-bat-upload-target="thumb" class="w-20 h-20 shrink-0 rounded-md overflow-hidden border border-[var(--color-border-soft)] bg-[var(--color-muted)] flex items-center justify-center"></div>
                    <div class="flex-1 min-w-0">
                        <div class="text-xs uppercase tracking-wide text-[var(--color-primary)] font-semibold mb-0.5">Aperçu · pas encore envoyé</div>
                        <div data-bat-upload-target="filename" class="text-sm font-medium text-[var(--color-foreground)] truncate"></div>
                        <div data-bat-upload-target="filesize" class="text-xs text-[var(--color-secondary)]"></div>
                    </div>
                    <button type="button" data-action="click->bat-upload#clear"
                            class="shrink-0 w-8 h-8 rounded-md text-[var(--color-secondary)] hover:bg-[var(--color-muted)] hover:text-[var(--color-destructive)] flex items-center justify-center"
                            aria-label="Retirer">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit"
                        data-bat-upload-target="submit"
                        data-loading-text="Envoi…"
                        class="btn btn-primary text-xs !py-1.5 !px-4 disabled:opacity-40 disabled:cursor-not-allowed">
                    Déposer
                </button>
            </div>
        </form>
```

- [ ] **Step 2 : Vérifier**

Run: `php bin/console lint:twig templates/order/_documents.html.twig`
Expected: OK.
Run: `php bin/phpunit --filter testClientUploadsPdfDocument`
Expected: PASS (le POST de test garde `name="document"` ; les controllers Stimulus n'affectent pas le test serveur).

- [ ] **Step 3 : Commit**

```bash
git add templates/order/_documents.html.twig
git commit -m "feat: upload document avec aperçu fichier + spinner (bat-upload + submit-loading)"
```

---

## Task 7 : Documentation

**Files:**
- Modify: `AFDAL_DESIGN.md`

- [ ] **Step 1 : Ajouter une itération**

Dans `AFDAL_DESIGN.md`, dans la section « Itérations post-livraison », après le bloc
« Documents commande (client → admin) » ajouté précédemment, ajouter :

```markdown
**Page Documents + UX upload** (2026-05-31) :
- Page liste tous les documents liés aux commandes : `/documents` (client, scopé à sa company via `OrderDocumentRepository::findForCompany`) et `/admin/documents` (admin, `findAllRecent`). Tableau à plat trié récent→ancien (nom, commande + statut, déposé par, date, taille, télécharger). Partial partagé `templates/order/_documents_table.html.twig` (param `order_route`).
- Téléchargement réutilise `app_order_doc_download` (garde admin OU même company). Pas de suppression depuis cette page.
- Nav : entrée « Documents » (icône `file`) ajoutée aux deux menus du shell.
- Page commande : bloc documents déplacé dans l'aside, directement sous la conversation.
- UX upload : aperçu (icône/vignette) du fichier choisi + spinner au dépôt, via réutilisation des controllers Stimulus `bat-upload` et `submit-loading` (aucun nouveau JS).
```

- [ ] **Step 2 : Commit**

```bash
git add AFDAL_DESIGN.md
git commit -m "docs: itération page documents + UX upload"
```

---

## Vérification finale

- [ ] **Tests de la feature verts**

Run: `php bin/phpunit --filter OrderDocumentTest`
Expected: OK (tous les tests documents passent, anciens + nouveaux).

- [ ] **Suite complète (pas de régression vs baseline)**

Run: `php bin/phpunit`
Expected: même nombre d'échecs qu'avant la feature (6 échecs pré-existants liés au test DB PostgreSQL — `DashboardController` DATEDIFF + redirects — NON liés à cette feature). Aucun nouvel échec.

- [ ] **Lint Twig global des fichiers touchés**

Run: `php bin/console lint:twig templates/documents templates/admin/documents templates/order/_documents_table.html.twig templates/order/_documents.html.twig templates/order/detail.html.twig templates/dashboard/_shell.html.twig`
Expected: OK.

- [ ] **Rappel prod** : `php bin/console asset-map:compile` (les controllers Stimulus `bat-upload`/`submit-loading` existent déjà ; aucun nouvel asset, mais recompiler par sécurité au déploiement).
