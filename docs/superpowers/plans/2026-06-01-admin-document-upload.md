# Dépôt de documents par l'admin — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permettre à l'admin de déposer des documents sur une commande client (notif cloche + email au client), distinguer les docs Afdal par un badge, et autoriser l'admin à supprimer n'importe quel document quel que soit le statut.

**Architecture:** L'upload réutilise la route existante `app_order_doc_upload` (la garde `assertClientOwns` autorise déjà l'admin). La notification branche sur le rôle du déposant. Un helper `OrderDocument::isFromAdmin()` pilote le badge. La garde `delete()` reçoit une branche admin sans restriction.

**Tech Stack:** Symfony 7.4, Doctrine ORM 3, Twig, PHPUnit (WebTestCase + dama/doctrine-test-bundle).

**État courant (vérifié) :** dans `src/Controller/OrderDocumentController.php`, la notif admin + l'email sont **inline** dans `upload()` (pas extraits). `delete()` exclut l'admin et redirige toujours vers `app_order_detail`. Le form d'upload de `_documents.html.twig` est sous `{% if not is_admin %}`.

**Convention :** commits ciblés (`git add <fichiers>`), ne jamais stager de fichier non lié.

---

## Fichiers

- Modifier : `src/Entity/OrderDocument.php` (helper `isFromAdmin()`)
- Modifier : `src/Controller/OrderDocumentController.php` (notif selon déposant + garde/redirect `delete()`)
- Créer : `templates/emails/order/document_uploaded_client.html.twig` (email client)
- Modifier : `templates/order/_documents.html.twig` (badge Afdal + form ouvert admin + bouton suppr admin)
- Modifier : `tests/Functional/OrderDocumentTest.php`
- Modifier : `AFDAL_DESIGN.md`

> Note tests : DB de test = PostgreSQL (`.env.test.local`, quirk connu). Helpers existants : `createOrder`, `persistDocument(Order,User,name)`, `createCompanyWithAntenna`, `createUser('admin'|'client',...)`, `tmpFile`.

---

## Task 1 : Entité — helper `isFromAdmin()` (TDD)

**Files:**
- Modify: `tests/Functional/OrderDocumentTest.php`
- Modify: `src/Entity/OrderDocument.php`

- [ ] **Step 1 : Écrire le test (échoue)**

Ajouter dans `OrderDocumentTest` :

```php
    public function testIsFromAdminReflectsUploader(): void
    {
        self::bootKernel();
        [$company, $antenna] = $this->createCompanyWithAntenna();
        $client = $this->createUser('client', $company, CompanyRole::OWNER);
        $admin = $this->createUser('admin');
        $order = $this->createOrder($company, $antenna, $client);

        $clientDoc = $this->persistDocument($order, $client, 'client.pdf');
        $adminDoc = $this->persistDocument($order, $admin, 'afdal.pdf');

        self::assertFalse($clientDoc->isFromAdmin());
        self::assertTrue($adminDoc->isFromAdmin());
    }
```

- [ ] **Step 2 : Lancer → échec attendu**

Run: `php bin/phpunit --filter testIsFromAdminReflectsUploader`
Expected: FAIL — `Error: Call to undefined method App\Entity\OrderDocument::isFromAdmin()`.

- [ ] **Step 3 : Ajouter le helper**

Dans `src/Entity/OrderDocument.php`, après la méthode `isImage()` (les helpers `isPdf()`/`isImage()` sont en fin de classe) :

```php
    public function isFromAdmin(): bool
    {
        return $this->uploadedBy->isAdmin();
    }
```

- [ ] **Step 4 : Lancer → succès attendu**

Run: `php bin/phpunit --filter testIsFromAdminReflectsUploader`
Expected: PASS.

- [ ] **Step 5 : Commit**

```bash
git add src/Entity/OrderDocument.php tests/Functional/OrderDocumentTest.php
git commit -m "feat: OrderDocument::isFromAdmin() pour distinguer les dépôts Afdal"
```

---

## Task 2 : Upload admin → notification du client (TDD)

**Files:**
- Modify: `tests/Functional/OrderDocumentTest.php`
- Modify: `src/Controller/OrderDocumentController.php`
- Create: `templates/emails/order/document_uploaded_client.html.twig`

- [ ] **Step 1 : Écrire le test d'upload admin (échoue d'abord côté assertion `isFromAdmin`)**

Ajouter dans `OrderDocumentTest` :

```php
    public function testAdminUploadsDocument(): void
    {
        $client = static::createClient();
        [$company, $antenna] = $this->createCompanyWithAntenna();
        $owner = $this->createUser('client', $company, CompanyRole::OWNER);
        $admin = $this->createUser('admin');
        $order = $this->createOrder($company, $antenna, $owner);

        $client->loginUser($admin);
        $pdf = $this->tmpFile('facture.pdf', "%PDF-1.4\n%%EOF", 'application/pdf');
        $client->request('POST', '/commandes/' . $order->getReference() . '/documents', [], ['document' => $pdf]);

        self::assertResponseRedirects();
        $docs = $this->em()->getRepository(OrderDocument::class)->findBy(['order' => $order]);
        self::assertCount(1, $docs);
        self::assertSame($admin->getId(), $docs[0]->getUploadedBy()->getId());
        self::assertTrue($docs[0]->isFromAdmin());
    }
```

- [ ] **Step 2 : Lancer → vérifier l'état**

Run: `php bin/phpunit --filter testAdminUploadsDocument`
Expected : PASS possible (la garde `assertClientOwns` autorise déjà l'admin et l'upload persiste avec `uploadedBy=admin`). Si PASS, l'upload admin fonctionne déjà au niveau données — on continue pour brancher la notification client (Step 3) et l'UI (Task 3). Si FAIL, lire l'erreur et corriger avant de continuer.

- [ ] **Step 3 : Créer le template email client**

`templates/emails/order/document_uploaded_client.html.twig` :

```twig
{% set clientUrl = url('app_order_detail', {reference: order.reference}) %}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Document ajouté — Commande {{ order.reference }}</title>
    <style>
        @media only screen and (max-width: 520px) {
            .e-wrap { padding: 16px 8px !important; }
            .e-card { border-radius: 8px !important; }
            .e-header, .e-body, .e-footer { padding-left: 20px !important; padding-right: 20px !important; }
            .e-h1 { font-size: 20px !important; }
            .e-subtitle { font-size: 13px !important; }
            .e-cta a { padding: 12px 20px !important; font-size: 13px !important; }
            .e-doc td { padding: 14px !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; color: #111;">
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" class="e-wrap" style="background: #f3f4f6; padding: 32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" class="e-card" style="max-width: 600px; background: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04); overflow: hidden;">
                    <tr>
                        <td class="e-header" style="padding: 28px 32px 20px; background: linear-gradient(135deg, #175CD3 0%, #1570EF 100%);">
                            <div style="font-size: 12px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: rgba(255,255,255,0.85);">Afdal</div>
                            <div class="e-subtitle" style="margin-top: 6px; font-size: 13px; color: rgba(255,255,255,0.95);">Un document a été ajouté à votre commande</div>
                        </td>
                    </tr>
                    <tr>
                        <td class="e-body" style="padding: 28px 32px;">
                            <div style="display: inline-block; padding: 4px 10px; background: #E0EAFC; color: #175CD3; border-radius: 999px; font-size: 11px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase;">Document ajouté</div>
                            <h1 class="e-h1" style="margin: 14px 0 6px; font-size: 24px; font-weight: 700; color: #111; letter-spacing: -0.01em;">Commande {{ order.reference }}</h1>
                            <p style="margin: 0 0 18px; font-size: 14px; color: #6b7280; line-height: 1.5;">
                                L'équipe Afdal a ajouté un document à votre commande le {{ document.createdAt|date('d/m/Y à H:i') }}.
                            </p>

                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" class="e-doc" style="border-collapse: collapse; background: #f9fafb; border-radius: 8px; margin: 16px 0;">
                                <tr>
                                    <td style="padding: 16px 18px;">
                                        <div style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; margin-bottom: 6px;">Fichier</div>
                                        <div style="font-size: 15px; font-weight: 700; color: #111; word-break: break-all;">{{ document.originalName }}</div>
                                        <div style="font-size: 13px; color: #6b7280; margin-top: 2px;">
                                            {{ document.isPdf ? 'PDF' : 'Image' }} · {{ (document.sizeBytes / 1024)|round }} Ko
                                        </div>
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" class="e-cta" style="margin: 24px 0 8px;">
                                <tr>
                                    <td style="border-radius: 8px; background: #111;">
                                        <a href="{{ clientUrl }}" style="display: inline-block; padding: 13px 26px; font-size: 14px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 8px;">Voir ma commande →</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td class="e-footer" style="padding: 16px 32px 20px; background: #f9fafb; border-top: 1px solid #e5e7eb;">
                            <div style="font-size: 11px; color: #9ca3af; text-align: center; line-height: 1.5;">
                                Afdal — Marquage textile professionnel<br>
                                Notification automatique.
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
```

- [ ] **Step 4 : Brancher la notification selon le déposant**

Dans `src/Controller/OrderDocumentController.php` :

(a) Ajouter l'import `Address` en tête (après les autres `use Symfony\...`) :

```php
use Symfony\Component\Mime\Address;
```

(b) Dans `upload()`, **remplacer** le bloc inline actuel (la notif admin `try { notifyAdmins ... }` + le bloc `$adminRecipient = ...`) — lignes ~110 à ~131, c'est-à-dire ce bloc exact :

```php
        try {
            $notifications->notifyAdmins(
                sprintf('Document reçu · Commande %s', $order->getReference()),
                $originalName,
                $this->generateUrl('app_admin_order_detail', ['reference' => $order->getReference()]),
                Notification::TYPE_INFO,
            );
        } catch (\Throwable) {
            // notification best-effort, ne bloque pas le dépôt
        }

        $adminRecipient = $mailer->notificationRecipientAdmin();
        if ($adminRecipient !== null) {
            $mailer->sendSilently(
                (new TemplatedEmail())
                    ->to($adminRecipient)
                    ->subject(sprintf('Document reçu · Commande %s — %s', $order->getReference(), $order->getCompany()->getName()))
                    ->htmlTemplate('emails/order/document_uploaded_admin.html.twig')
                    ->context(['order' => $order, 'document' => $document]),
                'order_doc_uploaded:' . $order->getReference(),
            );
        }
```

par un appel à une méthode privée :

```php
        $this->notifyOfUpload($order, $document, $user, $notifications, $mailer);
```

(`$user` est déjà défini plus haut dans `upload()` via `$user = $this->getUser();`.)

(c) Ajouter les méthodes privées avant `assertClientOwns()` :

```php
    /**
     * Notifie la contrepartie du dépôt (best-effort).
     * Client → admins ; Admin → membres de la company + email au créateur.
     */
    private function notifyOfUpload(
        Order $order,
        OrderDocument $document,
        User $uploader,
        NotificationService $notifications,
        AppMailer $mailer,
    ): void {
        if ($uploader->isAdmin()) {
            $this->notifyClientOfAdminUpload($order, $document, $notifications, $mailer);
        } else {
            $this->notifyAdminsOfClientUpload($order, $document, $notifications, $mailer);
        }
    }

    private function notifyAdminsOfClientUpload(
        Order $order,
        OrderDocument $document,
        NotificationService $notifications,
        AppMailer $mailer,
    ): void {
        try {
            $notifications->notifyAdmins(
                sprintf('Document reçu · Commande %s', $order->getReference()),
                $document->getOriginalName(),
                $this->generateUrl('app_admin_order_detail', ['reference' => $order->getReference()]),
                Notification::TYPE_INFO,
            );
        } catch (\Throwable) {
            // best-effort
        }

        $adminRecipient = $mailer->notificationRecipientAdmin();
        if ($adminRecipient !== null) {
            $mailer->sendSilently(
                (new TemplatedEmail())
                    ->to($adminRecipient)
                    ->subject(sprintf('Document reçu · Commande %s — %s', $order->getReference(), $order->getCompany()->getName()))
                    ->htmlTemplate('emails/order/document_uploaded_admin.html.twig')
                    ->context(['order' => $order, 'document' => $document]),
                'order_doc_uploaded:' . $order->getReference(),
            );
        }
    }

    private function notifyClientOfAdminUpload(
        Order $order,
        OrderDocument $document,
        NotificationService $notifications,
        AppMailer $mailer,
    ): void {
        try {
            $notifications->notifyCompany(
                $order->getCompany(),
                sprintf('Document ajouté · Commande %s', $order->getReference()),
                $document->getOriginalName(),
                $this->generateUrl('app_order_detail', ['reference' => $order->getReference()]),
                Notification::TYPE_INFO,
            );
        } catch (\Throwable) {
            // best-effort
        }

        $client = $order->getCreatedBy();
        $mailer->sendSilently(
            (new TemplatedEmail())
                ->to(new Address($client->getEmail(), $client->getFullName()))
                ->subject(sprintf('Document ajouté à votre commande %s', $order->getReference()))
                ->htmlTemplate('emails/order/document_uploaded_client.html.twig')
                ->context(['order' => $order, 'document' => $document]),
            'order_doc_uploaded_client:' . $order->getReference(),
        );
    }
```

- [ ] **Step 5 : Vérifier**

Run: `php -l src/Controller/OrderDocumentController.php`
Expected: No syntax errors.
Run: `php bin/console lint:twig templates/emails/order/document_uploaded_client.html.twig`
Expected: OK.
Run: `php bin/phpunit --filter "testAdminUploadsDocument|testClientUploadsPdfDocument"`
Expected: PASS (l'upload client comme admin fonctionne ; la notif est best-effort et n'altère pas la réponse).

- [ ] **Step 6 : Commit**

```bash
git add src/Controller/OrderDocumentController.php templates/emails/order/document_uploaded_client.html.twig tests/Functional/OrderDocumentTest.php
git commit -m "feat: notification client (cloche + email) quand l'admin dépose un document"
```

---

## Task 3 : UI — badge Afdal + upload admin + bouton supprimer admin

**Files:**
- Modify: `templates/order/_documents.html.twig`

- [ ] **Step 1 : Ajouter le badge « Afdal »**

Dans `templates/order/_documents.html.twig`, le lien du nom de document est :

```twig
                        <a href="{{ path('app_order_doc_download', {document: doc.id}) }}"
                           class="text-sm font-medium text-[var(--color-primary)] hover:underline truncate block">
                            {{ doc.originalName }}
                        </a>
```

Le remplacer par (nom + badge conditionnel) :

```twig
                        <a href="{{ path('app_order_doc_download', {document: doc.id}) }}"
                           class="text-sm font-medium text-[var(--color-primary)] hover:underline truncate">
                            {{ doc.originalName }}
                        </a>
                        {% if doc.isFromAdmin %}
                            <span class="ml-1.5 inline-flex items-center rounded-full bg-[var(--color-primary-light)] px-2 py-0.5 text-[10px] font-semibold text-[var(--color-primary)]">Afdal</span>
                        {% endif %}
```

(Le `block` est retiré du `<a>` pour que le badge reste sur la même ligne.)

- [ ] **Step 2 : Ouvrir le bouton supprimer à l'admin**

Toujours dans `_documents.html.twig`, remplacer :

```twig
                    {% if not is_admin and can_delete %}
```

par :

```twig
                    {% if is_admin or can_delete %}
```

- [ ] **Step 3 : Ouvrir le formulaire d'upload à l'admin**

Le form d'upload est entouré de `{% if not is_admin %}` (ligne ~37) … `{% endif %}` (ligne ~55).
Retirer ces deux balises `{% if not is_admin %}` et son `{% endif %}` correspondant pour que le
`<form ... data-controller="auto-upload">` soit toujours rendu (client ET admin). Ne pas toucher au
contenu du form.

- [ ] **Step 4 : Vérifier**

Run: `php bin/console lint:twig templates/order/_documents.html.twig`
Expected: OK.
Run: `php bin/phpunit --filter "testClientUploadsPdfDocument|testAdminDocumentsPageListsAll"`
Expected: PASS (les pages rendent toujours).

- [ ] **Step 5 : Commit**

```bash
git add templates/order/_documents.html.twig
git commit -m "feat: UI admin documents (badge Afdal, upload, bouton supprimer)"
```

---

## Task 4 : Suppression admin sans restriction (TDD)

**Files:**
- Modify: `tests/Functional/OrderDocumentTest.php`
- Modify: `src/Controller/OrderDocumentController.php`

- [ ] **Step 1 : Remplacer le test obsolète + ajouter le test admin**

Dans `tests/Functional/OrderDocumentTest.php`, **supprimer entièrement** la méthode
`testAdminCannotDeleteDocument` (elle devient fausse) et la **remplacer** par :

```php
    public function testAdminCanDeleteAnyDocumentAnyStatus(): void
    {
        $client = static::createClient();
        [$company, $antenna] = $this->createCompanyWithAntenna();
        $owner = $this->createUser('client', $company, CompanyRole::OWNER);
        $admin = $this->createUser('admin');
        // Commande CONFIRMED + document déposé par le client : l'admin doit pouvoir le supprimer.
        $order = $this->createOrder($company, $antenna, $owner, OrderStatus::CONFIRMED);
        $doc = $this->persistDocument($order, $owner);
        $docId = $doc->getId();

        $client->loginUser($admin);
        $client->request('POST', '/commandes/documents/' . $docId . '/delete');

        self::assertResponseRedirects();
        $this->em()->clear();
        self::assertNull($this->em()->getRepository(OrderDocument::class)->find($docId));
    }
```

- [ ] **Step 2 : Lancer → échec attendu**

Run: `php bin/phpunit --filter testAdminCanDeleteAnyDocumentAnyStatus`
Expected: FAIL — avec la garde actuelle, l'admin reçoit 403 (`$sameCompany` est `false` pour un admin), donc la row reste et `assertNull` échoue.

- [ ] **Step 3 : Modifier la garde + le redirect de `delete()`**

Dans `src/Controller/OrderDocumentController.php`, méthode `delete()`, remplacer :

```php
        $deletable = in_array($order->getStatus(), [OrderStatus::DRAFT, OrderStatus::PLACED], true);
        $sameCompany = !$user->isAdmin() && $order->getCompany()->getId() === $user->getCompany()?->getId();
        if (!$sameCompany || !$deletable) {
            throw $this->createAccessDeniedException();
        }
```

par :

```php
        if (!$user->isAdmin()) {
            $deletable = in_array($order->getStatus(), [OrderStatus::DRAFT, OrderStatus::PLACED], true);
            $sameCompany = $order->getCompany()->getId() === $user->getCompany()?->getId();
            if (!$sameCompany || !$deletable) {
                throw $this->createAccessDeniedException();
            }
        }
        // admin : aucune restriction (tout document, tout statut)
```

Puis, **remplacer la dernière ligne** de `delete()` :

```php
        $this->addFlash('success', 'Document supprimé.');
        return $this->redirectToRoute('app_order_detail', ['reference' => $order->getReference()]);
```

par (redirige l'admin vers la vue admin) :

```php
        $this->addFlash('success', 'Document supprimé.');
        $route = $user->isAdmin() ? 'app_admin_order_detail' : 'app_order_detail';
        return $this->redirectToRoute($route, ['reference' => $order->getReference()]);
```

- [ ] **Step 4 : Lancer → succès attendu**

Run: `php bin/phpunit --filter "testAdminCanDeleteAnyDocumentAnyStatus|testCompanyMemberCanDeleteUntilConfirmed|testCrossCompanyMemberCannotDelete|testDeleteForbiddenOnConfirmedOrder|testAuthorDeletesDocumentOnDraftOrder"`
Expected: PASS (5 tests — l'admin supprime tout ; les règles client restent intactes).

- [ ] **Step 5 : Commit**

```bash
git add src/Controller/OrderDocumentController.php tests/Functional/OrderDocumentTest.php
git commit -m "feat: l'admin peut supprimer tout document (tout statut) + redirect admin"
```

---

## Task 5 : Documentation

**Files:**
- Modify: `AFDAL_DESIGN.md`

- [ ] **Step 1 : Ajouter une itération**

Dans `AFDAL_DESIGN.md`, section « Itérations post-livraison », après le bloc
« Documents : auto-submit, suppression équipe, recherche », ajouter :

```markdown
**Dépôt de documents par l'admin** (2026-06-01) :
- L'admin dépose des documents sur une commande client (même route `app_order_doc_upload` ; `assertClientOwns` autorise déjà l'admin). Form d'upload affiché aussi en mode admin.
- Notification selon le déposant : client → admins (cloche + email, inchangé) ; admin → membres de la company (cloche via `notifyCompany`) + email au créateur de la commande (`document_uploaded_client.html.twig`). Logique extraite dans `notifyOfUpload()` / `notifyAdminsOfClientUpload()` / `notifyClientOfAdminUpload()`.
- Badge « Afdal » sur les documents déposés par un admin (`OrderDocument::isFromAdmin()`), visible côté client et admin.
- Suppression : l'admin supprime n'importe quel document quel que soit le statut (garde `delete()` : branche admin sans restriction) ; redirige vers la vue admin. Règle client inchangée (membre company + DRAFT/PLACED).
```

- [ ] **Step 2 : Commit**

```bash
git add AFDAL_DESIGN.md
git commit -m "docs: itération dépôt de documents par l'admin"
```

---

## Vérification finale

- [ ] **Tests feature verts**

Run: `php bin/phpunit --filter OrderDocumentTest`
Expected: OK (tous, anciens + nouveaux ; `testAdminCannotDeleteDocument` n'existe plus, remplacé).

- [ ] **Suite complète (pas de régression vs baseline)**

Run: `php bin/phpunit`
Expected: 6 échecs pré-existants liés au test DB PostgreSQL (DashboardController DATEDIFF + redirects), aucun nouvel échec.

- [ ] **Lint Twig**

Run: `php bin/console lint:twig templates/order templates/emails/order`
Expected: OK.

- [ ] **Rappel prod** : `git pull` + `php bin/console cache:clear --env=prod`. Pas de migration ni d'asset (template + PHP only). L'email client part via la config SMTP prod (si configurée) — sinon seule la cloche fonctionne.
