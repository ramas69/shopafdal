# Spec — Dépôt de documents sur commande (client → admin)

Date : 2026-05-31
Statut : approuvé (design), en attente plan d'implémentation

## Objectif

Permettre au client (CLIENT_MANAGER) de déposer des documents (PDF, images) sur
une commande, consultables et téléchargeables par l'admin. Sens unique
client → admin. Aucun système de documents/BL existant — on part propre.

## Périmètre

- Document **rattaché à une commande** (`Order`), pas à un `OrderItem` ni à la `Company`.
- Sens **client → admin** uniquement (pas de dépôt admin → client dans ce périmètre).
- Formats : **PDF + images** (`application/pdf`, `image/jpeg`, `image/png`, `image/webp`).
- Suppression par le client : autorisée tant que la commande est **non traitée**
  (statut `DRAFT` ou `PLACED`), et seulement par l'auteur du dépôt.
- Visibilité côté client : tout membre de la Company qui peut voir la commande voit ses documents
  (réutilise les droits commande existants).

### Hors périmètre (YAGNI)

Versioning, workflow de validation/review, documents hors-commande (Company/global),
dépôt admin → client, formats bureautiques (DOCX/XLSX/CSV) ou exécutables.

## Architecture

Approche retenue : **nouvelle entité `OrderDocument`** (miroir allégé de `MarkingAsset`,
sans version ni workflow review). Suit les patterns du projet :
upload type `BatController`, notifications via `NotificationService`, journal via `OrderEventLogger`.

### 1. Entité `OrderDocument`

Fichier : `src/Entity/OrderDocument.php` — table `order_documents`.

| Champ | Type | Contraintes |
|---|---|---|
| `id` | int | PK, auto |
| `order` | ManyToOne → `Order` | JoinColumn `nullable: false`, `onDelete: 'CASCADE'` |
| `path` | string(255) | chemin public `/uploads/order-docs/<hex>.<ext>` |
| `originalName` | string(255) | nom de fichier original (affichage + download) |
| `mimeType` | string(100) | MIME détecté serveur |
| `sizeBytes` | int | taille en octets |
| `uploadedBy` | ManyToOne → `User` | `nullable: false` |
| `createdAt` | DateTimeImmutable | défini au constructeur |

- Index : `idx_orderdoc_order` sur `order_id`.
- Constructeur : `__construct(Order $order, User $uploader, string $path, string $originalName, string $mimeType, int $sizeBytes)`.
- Getters uniquement (entité immuable hors suppression).
- Relation inverse sur `Order` :
  `#[OneToMany(targetEntity: OrderDocument::class, mappedBy: 'order', cascade: ['remove'], orphanRemoval: true)]`
  avec accesseur `getDocuments(): Collection`.
- Repository `OrderDocumentRepository` (méthode `findForOrder(Order): array`, tri `createdAt DESC`).
- Migration Doctrine dédiée (`migrations/Version<timestamp>.php`), compatible MySQL/MariaDB.

### 2. Stockage + validation upload

- Répertoire : `public/uploads/order-docs/`, injecté via
  `#[Autowire('%kernel.project_dir%/public/uploads/order-docs')]`.
- Préfixe public : `/uploads/order-docs/`.
- Nom de fichier stocké : `bin2hex(random_bytes(12)) . '.' . ($file->guessExtension() ?: 'bin')`.
- MIME autorisés (constante `ALLOWED_MIME`) :
  `application/pdf`, `image/jpeg`, `image/png`, `image/webp`.
- Garde-fou taille applicatif : **max 10 Mo** (`MAX_BYTES = 10 * 1024 * 1024`),
  vérifié en plus de `upload_max_filesize` PHP.
- Réutilise la logique de validation et les messages d'erreur localisés (FR) de `BatController` :
  fichier absent, `!isValid()` (switch sur `UPLOAD_ERR_*`), MIME non supporté, échec `move()`.

### 3. Controller + routes + sécurité

Fichier : `src/Controller/OrderDocumentController.php`.

| Route | Nom | Méthode | Accès |
|---|---|---|---|
| `/commande/{reference}/documents` | `app_order_doc_upload` | POST | client (membre Company de la commande) |
| `/commande/document/{document}/download` | `app_order_doc_download` | GET | client (même Company) + admin |
| `/commande/document/{document}/delete` | `app_order_doc_delete` | POST | client auteur, commande non traitée |

Sécurité :

- **Appartenance commande** : la commande doit appartenir à la Company de l'utilisateur courant.
  Réutiliser la garde existante (méthode privée type `assertOrderBelongsToUser` /
  équivalent dans `OrderController`/`BatController`) ; factoriser si besoin.
- **Download** (route hors `/admin`) : autorisé si `isGranted('ROLE_ADMIN')`
  **OU** la commande appartient à la Company de l'utilisateur. Sinon `403`.
- **Delete** autorisé si et seulement si :
  `document.uploadedBy === user` **ET** `order.status ∈ {DRAFT, PLACED}`.
  Sinon `403`.
- Suppression = supprime le fichier sur disque (`unlink`/`Filesystem::remove`, tolérant si absent)
  **puis** la row DB. Action directe : l'élément disparaît au clic (pas d'overlay mark-then-save).
- **CSRF** : token sur upload et delete (cohérent avec les forms POST du projet).
- Download : réponse `BinaryFileResponse` avec `Content-Disposition: attachment`
  forçant `originalName`.

### 4. Interface

- **Page commande client** (`templates/order/` — page détail) :
  nouvelle section « Documents partagés ».
  - Liste : icône selon type (PDF / image), `originalName`, taille lisible, date, déposant.
  - Bouton supprimer (form POST) affiché uniquement si l'utilisateur courant est l'auteur
    et la commande est non traitée.
  - Zone d'upload (`input[type=file]`, form POST multipart, CSRF).
- **Page admin commande** (`templates/admin/` — détail commande) :
  même liste en lecture seule + download, badge « déposé par {nom client} ».
- Respecter le design system Afdal (tokens couleurs, Inter/Lexend, états WCAG).

### 5. Notifications + journal

- Au dépôt réussi :
  `notifications->notifyAdmins('Document reçu · Commande ' . ref, originalName, urlAdminOrder)`.
- Journal commande : ajouter `OrderEventLogger::logDocumentUploaded(Order, string $name)`
  sur le modèle de `logBatUploaded`, et afficher l'événement dans la timeline de la commande.
- (Optionnel, cohérence) journaliser la suppression : `logDocumentDeleted` — à confirmer au plan.

### 6. Tests

Functional (`tests/Functional/`) :

- Upload PDF valide → 1 `OrderDocument` créé, fichier présent, notif admin émise.
- Upload MIME interdit (ex. `text/plain`) → rejet, flash erreur, 0 row.
- Upload > 10 Mo → rejet taille.
- Delete par l'auteur sur commande `DRAFT`/`PLACED` → supprimé (row + fichier).
- Delete par l'auteur sur commande `CONFIRMED`+ → `403`.
- Delete par un non-auteur → `403`.
- Accès cross-company (commande d'une autre Company) → `403`.
- Download admin force `originalName`.

### 7. Documentation

- Mettre à jour `AFDAL_DESIGN.md` (mémoire vivante) : nouvelle phase
  « Documents commande (client → admin) » avec décisions clés.

## Risques / points d'attention

- `guessExtension()` peut renvoyer `null` → fallback `bin` ; le `originalName` reste la
  source de vérité pour l'affichage et le download.
- Limite `upload_max_filesize`/`post_max_size` o2switch potentiellement < 10 Mo :
  le message d'erreur doit afficher la limite serveur réelle (déjà fait dans le pattern BAT).
- `onDelete: CASCADE` DB : la suppression d'une commande supprime les rows, mais **pas**
  les fichiers disque → acceptable ici (suppression de commande non exposée au client),
  à documenter.
