# Spec — L'admin peut déposer des documents sur une commande

Date : 2026-06-01
Statut : approuvé (design), en attente plan d'implémentation
Dépend de : features « Dépôt de documents » + « Page Documents » + « Email admin au dépôt » déjà livrées.

## Objectif

Permettre à l'admin (Afdal) de déposer des documents sur une commande passée par un client
(ex. facture, bon de livraison), en miroir du dépôt client → admin déjà existant. Le client est
notifié (cloche + email). Les documents déposés par l'admin sont visuellement distingués (badge « Afdal »).
L'admin peut supprimer n'importe quel document, sans limite de statut.

## Périmètre

- **Upload admin** : l'admin dépose un document sur la fiche commande admin (même route, mêmes
  validations MIME/taille que le client).
- **Notification client** : quand l'admin dépose, les membres de la company sont notifiés (cloche)
  et le créateur de la commande reçoit un email.
- **Badge « Afdal »** : tout document dont le déposant est admin porte une pastille « Afdal » dans
  la liste (page commande client + admin, page Documents).
- **Suppression admin** : l'admin supprime n'importe quel document (client ou Afdal), quel que soit
  le statut de la commande. La règle client reste inchangée (membre de la company + statut DRAFT/PLACED).

### Hors périmètre (YAGNI)

Catégories de documents (facture/BL/devis…), soft-delete, restriction de statut pour l'admin,
sections séparées « vos documents » / « documents Afdal ».

## Architecture

### 1. Entité `OrderDocument` — helper `isFromAdmin()`

Ajouter une méthode au modèle de `isPdf()`/`isImage()` :

```php
public function isFromAdmin(): bool
{
    return $this->uploadedBy->isAdmin();
}
```

(`User::isAdmin(): bool` existe déjà.)

### 2. Controller `OrderDocumentController::upload()` — notification selon le déposant

La garde `assertClientOwns($order)` autorise **déjà** l'admin (`!$user->isAdmin() && ...`) : aucun
changement de garde nécessaire pour l'upload. La validation, le `move()`, la persistance et le
`logDocumentUploaded` restent identiques.

Seule la **notification** change. La méthode privée actuelle `notifyAdminsOfUpload(...)` est
remplacée par une logique qui branche sur le rôle du déposant :

```php
$this->notifyOfUpload($order, $document, $user, $notifications, $mailer);
```

Nouvelle méthode privée :

```php
/**
 * Notifie la contrepartie du dépôt (cloche + email, best-effort).
 * - Dépôt par un client → notifie les admins.
 * - Dépôt par un admin   → notifie les membres de la company + email au créateur de la commande.
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
```

- `notifyAdminsOfClientUpload(...)` = le corps actuel de `notifyAdminsOfUpload()` (cloche `notifyAdmins`
  + email vers `notificationRecipientAdmin()` via `document_uploaded_admin.html.twig`). Inchangé.
- `notifyClientOfAdminUpload(...)` :
  - **Cloche** : `notifications->notifyCompany($order->getCompany(), 'Document ajouté · Commande {ref}',
    $document->getOriginalName(), url client commande, Notification::TYPE_INFO)`.
    (Signature réelle : `notifyCompany(Company, string $title, ?string $message, ?string $linkUrl, string $type)`.)
    Le lien pointe vers `app_order_detail` (vue client).
  - **Email** best-effort vers `$order->getCreatedBy()->getEmail()` :
    ```php
    $mailer->sendSilently(
        (new TemplatedEmail())
            ->to(new Address($order->getCreatedBy()->getEmail(), $order->getCreatedBy()->getFullName()))
            ->subject(sprintf('Document ajouté à votre commande %s', $order->getReference()))
            ->htmlTemplate('emails/order/document_uploaded_client.html.twig')
            ->context(['order' => $order, 'document' => $document]),
        'order_doc_uploaded_client:' . $order->getReference(),
    );
    ```
  - Les deux dans des `try/catch (\Throwable)` (best-effort, ne bloque pas le dépôt) — même pattern
    que l'existant. `Address` est déjà importable (`Symfony\Component\Mime\Address`).

### 3. Template email client `templates/emails/order/document_uploaded_client.html.twig`

Calqué sur `document_uploaded_admin.html.twig`, adapté au client :
- Header bleu Afdal, sous-titre « Un document a été ajouté à votre commande ».
- Titre : commande {ref}.
- Texte : « L'équipe Afdal a ajouté un document à votre commande. »
- Encart fichier : nom, type (PDF/Image), taille.
- CTA « Voir ma commande → » vers `url('app_order_detail', {reference: order.reference})`.
- Footer standard.
- Context : `{order, document}`.

### 4. UI — bloc `templates/order/_documents.html.twig`

Le partial reçoit déjà `{order, can_delete, is_admin}`. Modifications :

**(a) Badge « Afdal »** sur chaque doc déposé par un admin (visible quel que soit `is_admin`).
Dans la ligne du document, à côté du nom :
```twig
{% if doc.isFromAdmin %}
    <span class="ml-2 inline-flex items-center rounded-full bg-[var(--color-primary-light)] px-2 py-0.5 text-[10px] font-semibold text-[var(--color-primary)] align-middle">Afdal</span>
{% endif %}
```

**(b) Formulaire d'upload ouvert à l'admin** : le `{% if not is_admin %}` qui entoure le `<form>`
d'upload (lignes 37 et 55) est **retiré** → le form s'affiche pour client ET admin (le controller
accepte déjà l'admin). Le form lui-même est inchangé (auto-upload + spinner).

**(c) Bouton supprimer** : la condition actuelle `{% if not is_admin and can_delete %}` devient :
```twig
{% if is_admin or can_delete %}
```
→ l'admin voit toujours le bouton supprimer (sur tous les docs, tout statut) ; le client le voit
uniquement si `can_delete` (commande DRAFT/PLACED). La garde serveur (section 5) reste la source de
vérité.

> Note : le libellé « déposé par {nom} » reste affiché uniquement côté admin (`{% if is_admin %}`),
> inchangé. Le badge « Afdal » complète cette info et est visible côté client aussi.

### 5. Controller `OrderDocumentController::delete()` — pouvoir admin total

La garde actuelle exclut l'admin (`$sameCompany = !$user->isAdmin() && ...`). La remplacer par une
branche admin explicite :

```php
$order = $document->getOrder();
/** @var User $user */
$user = $this->getUser();

if (!$user->isAdmin()) {
    $deletable = in_array($order->getStatus(), [OrderStatus::DRAFT, OrderStatus::PLACED], true);
    $sameCompany = $order->getCompany()->getId() === $user->getCompany()?->getId();
    if (!$sameCompany || !$deletable) {
        throw $this->createAccessDeniedException();
    }
}
// admin : aucune restriction (tout doc, tout statut)
```

Le reste de `delete()` (unlink + remove + flush + flash + redirect) est inchangé.

> Sécurité : l'admin peut supprimer une pièce déposée par le client, sans trace (pas de soft-delete).
> Acceptable (admin de confiance) — choix explicite du périmètre.

> Redirection : `delete()` redirige aujourd'hui vers `app_order_detail` (vue client). Pour l'admin,
> rediriger vers `app_admin_order_detail`. Adapter la fin de `delete()` :
> ```php
> $route = $user->isAdmin() ? 'app_admin_order_detail' : 'app_order_detail';
> return $this->redirectToRoute($route, ['reference' => $order->getReference()]);
> ```

## Tests (functional, `tests/Functional/OrderDocumentTest.php`)

- **`testAdminUploadsDocument`** : admin connecté POST upload sur une commande d'une company →
  1 `OrderDocument` créé, `getUploadedBy()` = l'admin, `isFromAdmin()` true. Redirige.
- **`testAdminCanDeleteAnyDocumentAnyStatus`** : remplace `testAdminCannotDeleteDocument` (obsolète) —
  admin supprime un doc déposé par le client sur une commande `CONFIRMED` → succès (row supprimée).
- **`testIsFromAdminReflectsUploader`** : un doc déposé par un client → `isFromAdmin()` false ;
  par un admin → true (test unitaire léger via `persistDocument` + un uploader admin).
- Conserver verts : `testCompanyMemberCanDeleteUntilConfirmed`, `testCrossCompanyMemberCannotDelete`,
  `testDeleteForbiddenOnConfirmedOrder`, `testAuthorDeletesDocumentOnDraftOrder`.
- Notifications/emails = best-effort via `AppMailer` → **non testés** par `assertEmailCount`
  (cf. mémoire `reference_appmailer_tests` : AppMailer non relié au collecteur de test). Couverture
  par `lint:twig` du nouveau template + envoi réel via `app:mail:test` si besoin en prod.

`persistDocument()` (helper existant) accepte un `$uploader` : pour les tests admin, passer un user admin.

## Documentation

- Mettre à jour `AFDAL_DESIGN.md` (itération « Dépôt de documents par l'admin »).

## Risques / points d'attention

- La page Documents (`_documents_table.html.twig`) affiche déjà « déposé par {nom} ». Le badge « Afdal »
  n'y est pas requis par cette spec (périmètre = bloc commande), mais peut être ajouté symétriquement
  si souhaité — **hors périmètre v1** pour rester focalisé.
- `notifyCompany` notifie tous les membres actifs de la company, y compris l'auteur si présent —
  acceptable (cohérent avec le sens client→admin qui notifie tous les admins).
- Email client : destinataire = `order.createdBy` (créateur de la commande), pas tous les membres —
  choix retenu pour éviter le spam ; la cloche couvre les autres membres.
