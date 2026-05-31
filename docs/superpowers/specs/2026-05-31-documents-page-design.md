# Spec — Page Documents + déplacement du bloc commande

Date : 2026-05-31
Statut : approuvé (design), en attente plan d'implémentation
Dépend de : feature « Dépôt de documents sur commande » (entité `OrderDocument`, routes upload/download/delete déjà livrées).

## Objectif

1. **Page Documents globale** : une page listant tous les documents liés aux commandes,
   accessible côté client (scopé à sa company) et côté admin (toutes les commandes).
2. **Déplacement UI** : sur la page détail commande, déplacer le bloc « Documents partagés »
   dans la colonne de droite (`aside`), directement sous la conversation.

## Périmètre

- Page Documents pour **client ET admin**, chacun scopé :
  - Client : documents des commandes de **sa** company.
  - Admin : documents de **toutes** les commandes.
- Présentation : **tableau à plat, trié par date décroissante** (récent → ancien).
- Actions sur les lignes : **télécharger uniquement** (+ lien vers la commande). Pas de suppression ici.
- Download : **réutilise la route existante** `app_order_doc_download` (garde déjà admin OU même company).

### Hors périmètre (YAGNI)

Filtres / recherche, pagination, tri configurable, suppression depuis cette page, upload depuis cette page.

## Architecture

### 1. Repository — `OrderDocumentRepository`

Ajouter deux méthodes (le repo est actuellement vide hormis le constructeur) :

```php
use App\Entity\Company;
use App\Entity\OrderDocument;

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

(Le `use App\Entity\Company;` doit être ajouté en tête du repository.)

### 2. Controllers

Deux controllers fins, suivant le split `Admin/` du projet.

**Client** — `src/Controller/DocumentController.php` :
- `#[IsGranted('IS_AUTHENTICATED_FULLY')]` au niveau classe.
- `#[Route('/documents', name: 'app_documents', methods: ['GET'])]`.
- Action `index(OrderDocumentRepository $documents)` :
  - `$user = $this->getUser()` (typé `App\Entity\User`).
  - `$docs = $documents->findForCompany($user->getCompany())`.
  - `return $this->render('documents/index.html.twig', ['documents' => $docs])`.
- Garde implicite : le firewall `^/` exige déjà `ROLE_CLIENT_MANAGER`. Le scope company vient du repo.

**Admin** — `src/Controller/Admin/DocumentController.php` :
- `#[IsGranted('ROLE_ADMIN')]` au niveau classe (cohérent avec les autres controllers `Admin/`).
- `#[Route('/admin/documents', name: 'app_admin_documents', methods: ['GET'])]`.
- Action `index(OrderDocumentRepository $documents)` :
  - `$docs = $documents->findAllRecent()`.
  - `return $this->render('admin/documents/index.html.twig', ['documents' => $docs])`.

> Note : un admin n'a pas de `company` (nullable). Il n'utilise jamais `findForCompany` — d'où les deux routes/controllers distincts plutôt qu'une action branchée sur le rôle.

### 3. Vue — partial table partagé

**`templates/order/_documents_table.html.twig`** — reçoit `{documents}` (liste d'`OrderDocument`).
Tableau (ou liste stylée cohérente avec le design system Afdal), colonnes :

| Colonne | Contenu |
|---|---|
| Document | pastille `PDF`/`IMG` (via `doc.isPdf`) + `doc.originalName`, lien `path('app_order_doc_download', {document: doc.id})` (téléchargement) |
| Commande | lien vers le détail commande (voir routing ci-dessous) : `doc.order.reference` + badge statut (`doc.order.status.label()`, couleur `doc.order.status.progressColor()`) |
| Déposé par | `doc.uploadedBy.fullName` |
| Date | `doc.createdAt|date('d/m/Y')` |
| Taille | `(doc.sizeBytes / 1024)|round` Ko |
| Action | bouton/lien « Télécharger » → `path('app_order_doc_download', {document: doc.id})` |

- État vide : si `documents` est vide, afficher un message « Aucun document pour le moment. ».
- **Lien commande** : dépend du contexte (client → `app_order_detail`, admin → `app_admin_order_detail`).
  Le partial reçoit un paramètre `order_route` (string) ; chaque page passe le bon nom de route :
  `{{ include('order/_documents_table.html.twig', {documents: documents, order_route: 'app_order_detail'}) }}`
  côté client, `'app_admin_order_detail'` côté admin. Le lien : `path(order_route, {reference: doc.order.reference})`.

**`templates/documents/index.html.twig`** (client) :
- `{% extends 'dashboard/_shell.html.twig' %}`, `{% block title %}Documents — Afdal{% endblock %}`.
- `{% block content %}` : en-tête (titre « Documents », sous-titre nombre + nom company), puis
  `{{ include('order/_documents_table.html.twig', {documents: documents, order_route: 'app_order_detail'}) }}`.

**`templates/admin/documents/index.html.twig`** (admin) :
- Même structure, sous-titre adapté (« tous les documents »),
  `order_route: 'app_admin_order_detail'`.

Le shell `dashboard/_shell.html.twig` dérive `is_admin` lui-même (`app.user.isAdmin()`) → aucun passage de variable nécessaire pour la nav.

### 4. Navigation

Dans `templates/dashboard/_shell.html.twig`, ajouter une entrée « Documents » aux deux tableaux `nav_items` :
- Client (après `{ route: 'app_orders', ... }`) : `{ route: 'app_documents', label: 'Documents', icon: 'file' }`.
- Admin (après `{ route: 'app_admin_orders', ... }`) : `{ route: 'app_admin_documents', label: 'Documents', icon: 'file' }`.

Le shell rend les icônes via la macro `nav_icon(name)`. L'icône `file` n'existe probablement pas encore :
ajouter un cas `{% elseif name == 'file' %}` dans la macro `nav_icon` avec un SVG document
(style cohérent avec les autres icônes : `viewBox="0 0 24 24"`, `stroke-width` aligné, `currentColor`).
SVG document suggéré (heroicons « document ») :
```twig
{% elseif name == 'file' %}
    <svg ... viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
```
Reprendre exactement les attributs de taille/classe des SVG voisins dans la macro pour rester homogène.

### 5. Déplacement du bloc sur la page détail commande

Dans `templates/order/detail.html.twig` :
- **Retirer** l'include `order/_documents.html.twig` actuellement dans la colonne principale
  (`lg:col-span-2`, vers la ligne 245, entre la section articles/total et la section notes).
- **Ré-insérer** ce même include dans l'`aside` (colonne droite), **directement après**
  l'include de la conversation `order/_conversation.html.twig` (vers la ligne 302).
- Les paramètres restent identiques :
  ```twig
  {{ include('order/_documents.html.twig', {
      order: order,
      can_delete: order.status.value in ['draft', 'placed'],
      is_admin: false,
  }) }}
  ```
- Le partial `_documents.html.twig` est déjà stylé comme une card (`bg-white rounded-xl border ... shadow-sm p-6`),
  cohérent avec les sections de l'aside. Aucune modif du partial.

> Note : ce partial (`_documents.html.twig`, bloc d'une commande) reste distinct du nouveau
> partial table (`_documents_table.html.twig`, liste multi-commandes). Deux responsabilités, deux fichiers.

## Sécurité

- `/documents` : firewall `^/` → `ROLE_CLIENT_MANAGER` requis. Le repo `findForCompany` ne renvoie
  que les documents de la company de l'utilisateur → pas de fuite cross-company.
- `/admin/documents` : firewall `^/admin` → `ROLE_ADMIN`. Un client (`ROLE_CLIENT_MANAGER` seul) reçoit 403.
- Download inchangé (`app_order_doc_download`, garde `assertClientOwns` = admin OU même company).
- Cas admin sans company : géré par l'usage de `findAllRecent` (jamais `findForCompany`).

## Tests (functional, `tests/Functional/`)

Ajouter à `OrderDocumentTest` (ou un nouveau `DocumentPageTest`) :

- `findForCompany` ne renvoie que les documents de la company ciblée (créer 2 companies + 1 doc chacune,
  asserter le scope) et le tri `createdAt DESC`.
- `GET /documents` (client connecté) → 200, la page contient le nom du document de sa company
  et **ne contient pas** le nom d'un document d'une autre company.
- `GET /admin/documents` → 200 pour un admin ; **403** pour un client.
- Le lien de téléchargement (`app_order_doc_download`) répond bien (déjà couvert par les tests existants ;
  un smoke suffit ici).

## Documentation

- Mettre à jour `AFDAL_DESIGN.md` (itération « Page Documents ») : routes, scope, réutilisation du download,
  déplacement du bloc sous la conversation.

## Risques / points d'attention

- `findForCompany` reçoit `$user->getCompany()` : pour un `ROLE_CLIENT_MANAGER` la company est non nulle
  (invariant métier — un client manager appartient toujours à une company). Si jamais elle était nulle,
  le `QueryBuilder` renverrait 0 résultat (pas d'erreur). Acceptable.
- Tri `createdAt DESC` au niveau requête (pas via `OrderBy` sur la relation) car la page agrège
  plusieurs commandes — différent du bloc par-commande qui s'appuie sur `Order::$documents` (déjà `OrderBy DESC`).
- L'icône `file` ajoutée à la macro `nav_icon` doit matcher la signature des autres cas (un seul argument `name`).
