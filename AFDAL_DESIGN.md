# Afdal — Mémoire projet

Plateforme B2B commandes textile · Symfony 7 + PostgreSQL (o2switch)

---

## Design System

### Style
**Trust & Authority** — badges, crédibilité, WCAG AAA
- Light ✓ Full · Dark ✓ Full
- Performance : excellent

### Couleurs — Palette Afdal réelle (2026-04-18)

**Source** : extraites du HTML de [atelier-afdal.fr](https://www.atelier-afdal.fr/) (95 occurrences du bleu primary, 31 du rouge accent).

| Rôle | Hex | Variable CSS | Usage |
|------|-----|--------------|-------|
| **Primary** | `#175CD3` | `--color-primary` | Bleu Afdal · CTA principal, liens, nav active, logo |
| Primary focus | `#194185` | `--color-primary-focus` | Hover/active |
| Primary light | `#E0EAFC` | `--color-primary-light` | Backgrounds légers |
| On primary | `#FFFFFF` | `--color-on-primary` | Texte sur primary |
| **Accent** | `#E82538` | `--color-accent` | Rouge Afdal · Highlights, badges "Nouveau", CTA critique |
| Accent focus | `#C0172B` | `--color-accent-focus` | Hover accent |
| Accent light | `#FEE4E6` | `--color-accent-light` | Backgrounds légers destructive/accent |
| **Foreground** | `#181D27` | `--color-foreground` | Titres h1-h6 |
| Body | `#414651` | `--color-body` | Texte courant |
| **Secondary** | `#535862` | `--color-secondary` | Texte muet (labels, captions) — gardé comme muted-text |
| Background | `#FAFAFA` | `--color-background` | Fond page |
| Surface | `#FFFFFF` | `--color-surface` | Cards, panneaux, inputs |
| Muted | `#E9EAEB` | `--color-muted` | Backgrounds secondaires (badges, hover) |
| Border | `#D5D7DA` | `--color-border` | Bordures interactives |
| Success | `#12B76A` | `--color-success` | Statut livré, confirmations |
| Warning | `#DC6803` | `--color-warning` | Statut en production |
| Destructive | `#E82538` | `--color-destructive` | = accent (cohérent avec la marque) |

**Règle primary vs accent** :
- **Primary (bleu)** = omniprésent : tous les CTA "normaux", liens, éléments de nav, badges "Placée", icônes principales
- **Accent (rouge)** = sparse : uniquement là où on veut de la friction visuelle positive/négative (badge "Nouveau", erreurs, CTA destructifs, éléments "attention")

### Navy scale (pour granularité du shell)
`--color-navy-50` à `--color-navy-900` — dérivée de la palette Afdal pour avoir des teintes neutres cohérentes (sidebar, borders subtiles, text micro).

### Typographie
- **Titres** : Lexend (400–700) — inchangé, tracking serré (`-0.015em` → `-0.025em` sur h1)
- **Corps** : **Inter** (400–700) — changé depuis Source Sans 3, c'est le standard UI moderne
- Features OpenType Inter activés (`cv02 cv03 cv04 cv11`) : chiffres tabulaires + alternates contextuels

```css
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Lexend:wght@400;500;600;700&display=swap');
```

### Shadows (nouveau)
Tokens CSS `--shadow-xs/sm/md/lg` avec offsets doux et opacité faible (0.03–0.08) — plus diffus, plus moderne.

### Radii
- `--radius-sm`: 0.375rem
- `--radius-md`: 0.5rem (défaut)
- `--radius-lg`: 0.75rem (cards)
- `--radius-xl`: 1rem (sections)

### À éviter
- Design playful / gradients purple-pink (AI vibes)
- Credentials / badges cachés
- Emojis en icônes (utiliser SVG : Heroicons, Lucide)

---

## Architecture UI

### Shell d'application (layout authentifié)

```
┌──────────────────────────────────────────────────────────┐
│  Topbar   [Logo Afdal]      [Search]   [Notif] [Avatar]  │  h-16, border-b
├──────────────┬───────────────────────────────────────────┤
│              │                                            │
│  Sidebar     │  Main content                              │
│  w-64        │  max-w-7xl, p-6/p-8                        │
│  (desktop)   │                                            │
│              │  [Breadcrumb]                              │
│  - Catalogue │  [Page title + actions]                    │
│  - Commandes │  [Content sections]                        │
│  - Antennes  │                                            │
│  ───────     │                                            │
│  - Paramètres│                                            │
│              │                                            │
└──────────────┴───────────────────────────────────────────┘
```

- **Mobile** (<768px) : sidebar devient drawer (Stimulus toggle), topbar garde logo + burger + avatar
- **Tablet** (768–1023px) : sidebar collapsée en icônes (w-16)
- **Desktop** (≥1024px) : sidebar complète (w-64)
- Active nav item : `bg-[var(--color-muted)] border-l-2 border-[var(--color-accent)]`

### Sidebar différenciée par rôle

| Rôle | Items |
|------|-------|
| `CLIENT_MANAGER` | Catalogue · Mes commandes · Mes antennes · Paramètres |
| `ADMIN` | Commandes à traiter · Produits · Clients (entreprises) · Invitations · Paramètres |

---

## Pages — spécifications

### 1. Landing + Login (`/`, `/login`)
- **Public** (pas de shell auth). Landing + login sur la même page ou séparés.
- **Hero** : `max-w-2xl`, titre Lexend 5xl, sous-titre `text-secondary`, 2 CTA (Se connecter / Demander un accès → `mailto:`)
- **Section "Proof"** (Trust & Authority) : 3 blocs (Sécurisé · Sur invitation · Sans paiement en ligne) avec icônes Heroicons SVG
- **Formulaire login** : card `bg-white rounded-xl border shadow-sm p-8`, email + password + submit. Erreurs inline sous chaque champ. `aria-live` sur zone erreur.
- **Pas de "créer un compte"** : c'est sur invitation uniquement → lien `mailto:contact@afdal.fr`
- [x] Tokens `--color-primary` / `--color-accent` utilisés
- [ ] À construire

### 2. Catalogue produits (`/catalogue`)
- **Header page** : titre "Catalogue" + compteur résultats
- **Filtres** (sidebar secondaire gauche ou pills au-dessus) : Catégorie, Matière, Couleur, Recherche texte
- **Grille produits** : `grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6`
- **Product card** : image 4:3, nom, catégorie en chip, hover = subtle lift (`hover:shadow-md transition-shadow duration-200`)
- **Turbo Frame** sur la grille → filtres = URL params, pas de reload
- **Empty state** : illustration SVG + "Aucun produit ne correspond" + bouton reset filtres
- [ ] À construire

### 3. Fiche produit + config commande (`/catalogue/{slug}`)
- **Layout 2 colonnes** (desktop) : gauche = galerie images (lightbox Stimulus), droite = configurateur
- **Configurateur** (formulaire) :
  - Variantes (taille × couleur) en grid → sélectionne SKU
  - Quantités par taille : inputs numériques groupés (`XS [0] S [0] M [0] L [0] XL [0]`)
  - Zone marquage (optionnel) : select zone (poitrine, dos, manche) + upload logo PNG/SVG + taille
  - Prix HT indicatif calculé live via Stimulus controller
- **Sticky bar bas de page** (mobile) : total + CTA "Ajouter au panier"
- **Desktop** : CTA en fin de colonne droite
- [ ] À construire

### 4. Panier + checkout B2B (`/panier`, `/commander`)
- **Panier** : tableau produits (image · nom · variantes · qté · prix HT · action supprimer)
- **Mobile** : cartes empilées au lieu du tableau
- **Checkout** (1 seule étape, pas de paiement) :
  - Étape 1 : Choix antenne destinataire (radio cards avec nom + adresse)
  - Étape 2 : Notes libres (textarea)
  - Étape 3 : Récap → CTA "Passer commande"
- **Après soumission** : page confirmation avec numéro commande + lien dashboard
- **Auto-save** du panier en session Symfony (pas de perte au refresh)
- [ ] À construire

### 5. Dashboard commandes client (`/commandes`, `/commandes/{id}`)
- **Liste** : tableau (N° · Date · Antenne · Articles · Montant · Statut · Action)
  - Badge statut : couleur par statut (DRAFT=slate · PLACED=sky · CONFIRMED=emerald · IN_PRODUCTION=amber · SHIPPED=indigo · DELIVERED=emerald-dark · CANCELLED=red)
  - Filtres haut de page : statut, antenne, plage dates
  - Pagination Turbo
- **Détail** :
  - Header : N° commande · statut · date
  - Timeline verticale des statuts (cercles connectés, statut actuel en highlight)
  - Liste articles (réutilise composant du panier en read-only)
  - Infos livraison (antenne + notes)
  - Actions selon statut (annuler si DRAFT/PLACED, télécharger BL si SHIPPED)
- [ ] À construire

### 6. Admin Afdal (`/admin/*`)
- **Dashboard** (`/admin`) : 4 stat cards (Commandes à traiter · Clients actifs · Produits · CA estimé mois) + liste commandes à traiter (filtrée PLACED + CONFIRMED)
- **Commandes** (`/admin/commandes`) : même vue que client mais toutes les commandes + filtre par entreprise
- **Détail commande admin** : même layout + panneau "Actions admin" avec boutons transitions statut (Workflow component Symfony)
- **Produits CRUD** (`/admin/produits`) : tableau + boutons Créer/Éditer/Désactiver, form multi-step avec upload images (VichUploader)
- **Clients** (`/admin/clients`) : liste entreprises → détail = antennes + utilisateurs + historique commandes
- **Invitations** (`/admin/invitations`) : form simple (email + company), liste invitations pending/acceptées avec actions renvoyer/révoquer
- [ ] À construire

---

## Composants réutilisables

Composants Twig à créer dans `templates/components/` (Twig Components ou simple `include`).

| Composant | Fichier | Usage |
|-----------|---------|-------|
| `button` | `components/button.html.twig` | primary / secondary / ghost / destructive, size sm/md/lg, loading state |
| `input` | `components/input.html.twig` | label + input + helper + error, types text/email/password/number |
| `textarea` | `components/textarea.html.twig` | avec compteur caractères optionnel |
| `select` | `components/select.html.twig` | native + styling Tailwind |
| `checkbox` / `radio` | `components/choice.html.twig` | accessible (label for=) |
| `card` | `components/card.html.twig` | container `bg-white rounded-xl border shadow-sm` |
| `badge` | `components/badge.html.twig` | variants par statut commande |
| `table` | `components/table.html.twig` | responsive (overflow-x-auto desktop, card stack mobile) |
| `pagination` | `components/pagination.html.twig` | Turbo-friendly |
| `modal` | `components/modal.html.twig` | Stimulus controller, focus trap, ESC, backdrop click |
| `toast` | `components/toast.html.twig` | success/error/info, auto-dismiss 4s, aria-live=polite |
| `breadcrumb` | `components/breadcrumb.html.twig` | uniquement pour profondeur ≥3 |
| `empty-state` | `components/empty_state.html.twig` | icône SVG + titre + description + CTA |
| `timeline` | `components/timeline.html.twig` | statuts commande (verticale) |
| `product-card` | `components/product_card.html.twig` | grille catalogue |
| `stat-card` | `components/stat_card.html.twig` | dashboard admin (label · nombre · variation) |
| `avatar` | `components/avatar.html.twig` | initiales sur fond coloré (pas de photo nécessaire B2B) |
| `sidebar-nav` | `components/sidebar_nav.html.twig` | nav principale, items conditionnels par rôle |

---

## Flows UX

### Onboarding client VIP
1. Admin crée invitation (`/admin/invitations` · email + company)
2. Email envoyé avec lien `/register/{token}` (expire 7j)
3. Client ouvre lien → formulaire (nom, mot de passe) + affiche nom de son entreprise
4. Submit → compte créé + login auto → redirect `/catalogue`
5. Empty state si aucune antenne → CTA "Ajouter une antenne" (obligatoire avant 1ère commande)

### Passage de commande
1. `/catalogue` → filtre/recherche → clique produit
2. `/catalogue/{slug}` → configure variantes + quantités + marquage → "Ajouter au panier"
3. Toast confirmation + compteur panier incrémenté
4. `/panier` → vérifie → "Commander"
5. `/commander` → choisit antenne + notes → "Passer commande"
6. Page confirmation → email auto à l'admin Afdal + au client

### Traitement commande admin
1. Notification dashboard (badge sur "Commandes à traiter")
2. Ouvre détail commande → bouton "Confirmer" (PLACED → CONFIRMED)
3. Quand lance la prod → bouton "Mettre en production"
4. Chaque transition envoie email auto au client
5. Admin peut ajouter notes internes (visibles que côté admin)

---

---

## Décisions prises

### Phase 0 — Setup (2026-04-18)
- **Stack** : Symfony 7.4 LTS (support jusqu'à fin 2028) + PHP ≥8.2
- **Pourquoi 7.4 et non 8.0** : 7.4 a les mêmes features que 8.0 (perf container DI, cache réécrit, FrankenPHP natif) mais en LTS. Symfony 8.0 n'est pas LTS → expire juillet 2026, forcerait 4 upgrades en 2 ans. PHP 8.2 plus portable sur mutualisé qu'exiger PHP 8.4.
- **Webapp pack** complet : Doctrine ORM, Twig, Security, Mailer, Validator, Form, Serializer, Messenger (transport Doctrine), AssetMapper + Turbo + Stimulus
- **Tailwind v4** via `symfonycasts/tailwind-bundle` (config CSS native, plus de `tailwind.config.js`)
- **Tokens design** dans `assets/styles/app.css` via directive `@theme` (couleurs + fonts Lexend/Source Sans 3)
- **Fonts** : Google Fonts chargées dans `base.html.twig` avec `preconnect`
- **PostgreSQL 16** via Docker Compose (fourni par le webapp pack) + Mailpit pour emails de dev
- **Favicon** : SVG custom (lettre A blanche sur fond `--color-primary`) — pas d'emoji

### Portabilité o2switch
- Pas de Node requis (AssetMapper + Tailwind bundle buildent en PHP pur)
- PHP 8.2 dispo partout sur o2switch
- PostgreSQL illimité via phpPgAdmin (standard PG, `pg_dump`/`pg_restore` fonctionnent)

### Phase 1 — Modèle de données (2026-04-18)
**Entités Doctrine créées** (8 entités + 2 enums) :
- `User` — email, fullName, role (enum ADMIN/CLIENT_MANAGER), password hashé, company (nullable pour admin), active, lastLoginAt
- `Company` — name, slug unique, siret, antennas/users/orders
- `Antenna` — company, name, addressLine, postalCode, city, country, phone
- `Product` — name, slug, description, category, material, basePriceCents (entier), images (json), active
- `ProductVariant` — product, size, color, colorHex, SKU unique, stock (nullable)
- `Order` — reference unique, company, antenna, createdBy, status (enum 7 états), notes, adminNotes, timestamps
- `OrderItem` — order, variant, quantity, unitPriceCents, marking (json : zone/taille/fichier)
- `Invitation` — email, company, token 48 chars (bin2hex 24 bytes), expiresAt (+7j auto), acceptedAt, revokedAt

**Décisions d'archi** :
- **IDs** : int auto-increment (simple, standard, pas d'UUID inutile pour B2B fermé)
- **Prix** : stockés en centimes (`int`) — pas de float pour éviter erreurs d'arrondi
- **Marking** : JSON flexible sur `OrderItem` (zone, dimensions, fichier) — pas de table dédiée (évite surdesign)
- **Pas de soft delete** : produits désactivés via `active = false`, commandes ont statut CANCELLED
- **Timestamps** : `createdAt` immutable en constructor, `updatedAt` sur Order seulement (setStatus le met à jour)
- **Table `orders`** (et pas `order`) — mot réservé SQL

**Fixtures de dev** (`bin/console doctrine:fixtures:load`) :
- 1 admin : `admin@afdal.fr` / `admin123`
- 2 clients VIP + 3 antennes + 2 managers : `marie@groupe-alpha.fr` / `jean@beta-sas.fr` (password: `client123`)
- 5 produits textile (T-shirt, Polo, Sweat, Casquette, Tote) × 51 variantes
- 4 commandes d'exemple (différents statuts)

**Base de données** : PostgreSQL 16 Homebrew local (DB `afdal_dev`), config via `.env.local` (gitignoré). Pas de Docker nécessaire en dev.

### Phase 2 — Auth & invitations (2026-04-18)

**Security config** (`config/packages/security.yaml`) :
- Provider Doctrine sur `App\Entity\User` (email comme identifier)
- Firewall `main` : `form_login` + `logout` + `remember_me` (30 jours, samesite=lax)
- Role hierarchy : `ROLE_ADMIN` inclut `ROLE_CLIENT_MANAGER`
- `access_control` : `/admin/*` = ROLE_ADMIN · `/*` = ROLE_CLIENT_MANAGER (sauf `/`, `/login`, `/register` publics)
- CSRF **stateless** (via Stimulus) — standard Symfony 7.4, pas de config manuelle

**Routes auth** :
- `GET /login` · `POST /login` : formulaire connexion (redirect vers /dashboard si déjà logué)
- `GET /logout` : déconnexion + redirect vers /
- `GET /register/{token}` · `POST` : inscription via invitation (token 48 chars, valide 7j)
- `GET /dashboard` : redirige vers `/admin` (admin) ou `/catalogue` (client)

**Routes admin invitations** :
- `GET /admin/invitations` : liste avec statuts (En attente / Acceptée / Révoquée / Expirée)
- `GET|POST /admin/invitations/new` : création (email + company)
- `POST /admin/invitations/{id}/revoke` : révoquer

**Flow invitation (actuel)** :
1. Admin crée invitation → token généré (bin2hex 24 bytes) + expiration +7j
2. L'URL complète `/{host}/register/{token}` est affichée en flash message (copy-paste) — **pas d'email envoyé en Phase 2** (mailer = null://null, emails en Phase 5)
3. Client ouvre le lien → si token valide+pending, affiche form (nom + password min 8 chars)
4. Submit → crée User avec role CLIENT_MANAGER + company de l'invitation, marque `acceptedAt`, login auto via `Security::login()`, redirect vers /dashboard

**Décisions techniques** :
- `Security::login()` (Symfony 7) pour login programmatique — évite de wire manuellement `FormLoginAuthenticator`
- Validation simple en contrôleur (NotBlank + Email + longueur password) — pas de FormType pour l'instant (moins de boilerplate pour 2 formulaires)
- Réponse HTTP 410 Gone pour token invalide/expiré (plutôt que 404 — le ressource a existé mais n'est plus dispo)
- Logout bouton dans shell `_shell.html.twig` — `<a>` simple (pas de POST form), acceptable ici car le risque CSRF sur logout est faible et l'UX est meilleure

**Shell authentifié** (`templates/dashboard/_shell.html.twig`) :
- Topbar minimaliste (logo + nom user + entreprise/rôle + déconnexion)
- Pas de sidebar pour l'instant (stubs simples) — sidebar complète en Phase 3/4
- Max-width 7xl, responsive

**Stubs dashboard** :
- `/catalogue` : 3 stat cards (antennes · commandes · statut) pour le client
- `/admin` : 4 stat cards (clients · produits actifs · commandes · à traiter) + lien invitations

### Phase 3 — Catalogue + Panier + Checkout (2026-04-18)

**Shell v2** (`templates/dashboard/_shell.html.twig`) :
- Topbar sticky + sidebar desktop (lg+) avec nav conditionnelle par rôle
- Active state : `bg-muted` + `border-l-2 border-accent`
- Badge quantité panier dans nav client (via service Cart injecté en twig global)
- Flash messages `success`/`error` affichés en tête de `content`

**Service `App\Service\Cart`** :
- Panier en session (clé `afdal_cart`), pas de DB (le panier est volatil, pas besoin de persistance cross-device)
- Chaque ajout crée une "ligne" avec `line_id` unique (bin2hex 8) + `variant_id` + `quantity` + `marking` optionnel
- API : `add`, `updateQuantity`, `remove`, `clear`, `lines`, `count`, `totalCents`, `isEmpty`
- Injecté en global Twig (`{{ cart.count }}`)

**Twig extension `App\Twig\AppExtension`** (PHP attributes, pas de `AbstractExtension`) :
- Filtre `|price` : centimes → `1 234,56 €`
- Fonctions `status_badge_class(status)` + `status_dot_class(status)` : classes Tailwind par statut

**Routes** :
- `GET /catalogue` : liste + filtres (`?q=` recherche, `?category=` filtre catégorie)
- `GET /catalogue/{slug}` : fiche produit (variantes groupées par couleur, tailles × quantités, marquage optionnel)
- `POST /catalogue/{slug}/add` : ajoute au panier (1 ligne par taille avec qty>0, marquage commun)
- `GET /panier` · `POST /panier/update/{lineId}` · `POST /panier/remove/{lineId}` · `POST /panier/clear`
- `GET|POST /commander` : choix antenne + notes → crée `Order` en `PLACED`, vide le panier, redirect vers confirmation
- `GET /commander/{reference}/confirmation` : page succès
- `GET /commandes` · `GET /commandes/{reference}` : liste + détail commandes du client

**Stimulus** `assets/controllers/quantities_controller.js` :
- Sur fiche produit : changement de couleur cache/montre panneaux de tailles, recalcule total HT live
- Reset des quantités des couleurs masquées (évite de submit des variantes d'une couleur non sélectionnée)

**Décisions UX** :
- 1 ajout panier = N lignes (1 par taille remplie) — le marquage est appliqué aux N lignes d'un même ajout
- Pas de paiement en ligne (comme spécifié) — checkout = simple "Passer commande" → `PLACED`
- Référence commande auto : `CMD-{YYYY}-{NNNN}` (compteur annuel via COUNT SQL)
- Clic sur ligne de table `/commandes` → redirige vers détail (comportement JS `onclick`)
- Utilisation de `MapEntity(mapping: ['reference' => 'reference'])` pour routes par référence métier
- Fiche produit : placeholder visuel basé sur première lettre du nom (uploads images = Phase 4)

**Templates créés** (9) : `catalogue/list`, `catalogue/detail`, `cart/index`, `checkout/form`, `checkout/confirmation`, `order/list`, `order/detail` + shell updaté.

### Phase 3b — Finition client (2026-04-18)

**Shell v3** :
- **Mobile drawer** : sidebar cachée par défaut < 1024px, bouton burger dans topbar, drawer slide-in + backdrop, fermeture via ESC ou click backdrop (Stimulus `drawer_controller.js`)
- **Icône panier dans topbar** (plus dans sidebar) avec badge compteur — accessible depuis toutes les pages sans scroll
- **Bouton logout** avec icône SVG + libellé masqué en mobile
- Nav client : `Catalogue · Mes commandes · Mes antennes · Paramètres`

**Gestion antennes** (`/antennes`) :
- Liste en cartes (2 colonnes desktop) + CTA "Ajouter"
- CRUD complet : `/antennes/new`, `/antennes/{id}/edit`, `POST /antennes/{id}/delete`
- Suppression avec `confirm()` natif (pas de modal custom pour l'instant)
- Voter-style check : le client ne peut éditer/supprimer que les antennes de sa propre `Company` (méthode `assertOwns`)

**Timeline commande** (`templates/order/detail.html.twig`) :
- Parcours linéaire : PLACED → CONFIRMED → IN_PRODUCTION → SHIPPED → DELIVERED
- États visuels : `done` (cercle vert + checkmark), `current` (cercle accent + ring), `upcoming` (cercle muted)
- Ligne verticale verte entre étapes terminées, grise sinon
- Statut `CANCELLED` affiché en bloc séparé rouge (remplace la timeline)
- Logique dans `OrderController::buildTimeline()` (fonction privée, pas besoin de service)

**Annulation commande** :
- `POST /commandes/{reference}/cancel` — bouton "Annuler la commande" visible uniquement si statut `DRAFT` ou `PLACED`
- Transition côté client uniquement (admin peut aussi annuler, en Phase 4)
- `confirm()` natif avant submit

**Filtres liste commandes** :
- `?status=` (enum values) · `?antenna=` (id) — build via QueryBuilder
- Select antennes visible uniquement si >1 antenne pour l'entreprise
- Bouton "Réinitialiser" affiché si filtre actif

**Paramètres** (`/parametres`) :
- Profil : nom complet éditable, email + entreprise read-only (pas modifiable par le client)
- Mot de passe : current + new + confirm, validation min 8 chars, check current via `UserPasswordHasherInterface::isPasswordValid()`
- 2 forms séparés avec endpoints dédiés (`app_settings_profile`, `app_settings_password`) — pas de gestion de token 2FA pour l'instant

**Fix accessibilité** : liste commandes utilise des `<a>` sur la référence (pas `onclick` sur `<tr>` — navigable clavier, right-click, copy link).

**Composants ajoutés** : Stimulus `drawer_controller.js`.

**Reorder** (`POST /commandes/{reference}/reorder`) :
- Bouton "Commander à nouveau" sur détail commande (disponible quel que soit le statut, même sur DELIVERED/CANCELLED)
- Copie toutes les lignes de la commande dans le panier (variante + quantité + marquage préservés)
- Redirige vers `/panier` avec flash succès → l'utilisateur peut ajuster avant de valider
- N'écrase pas le panier existant : ajoute en plus

**Export CSV** :
- Service `App\Service\OrderExporter` : génère StreamedResponse CSV (UTF-8 BOM + `;` séparateur = Excel FR natif)
- Colonnes : Référence, Date, Statut, Antenne, Ville, SKU, Produit, Catégorie, Couleur, Taille, Quantité, Prix HT unitaire, Total ligne, Marquage zone/taille, Notes
- 1 ligne CSV = 1 `OrderItem` (pas 1 `Order`) → exploitable directement en Excel pour calculs/tri
- Routes : `GET /commandes/export.csv` (multi : `?ids[]=` ou fallback filtres liste), `GET /commandes/{reference}/export.csv` (single)
- UI liste : checkbox par ligne + "Tout cocher" dans header + barre sticky "X sélectionnées / Exporter la sélection" (Stimulus `selection_controller.js`)
- UI détail : bouton "Exporter CSV" à côté de "Commander à nouveau"
- Sécurité : `assertOwns()` côté single + filtre `company = user.company` côté multi (impossible d'exporter des commandes d'une autre entreprise même en manipulant les IDs)
- **Route conflict fix** : `{reference}` contraint par regex `CMD-[0-9]{4}-[0-9]+` (extrait en constante `REF_PATTERN`) pour ne pas intercepter `/commandes/export.csv`

### Refresh Lineone (2026-04-18) — Option A

Intégration ciblée du template admin **Lineone** (référence locale dans `/lineone-html/`, gitignoré).

**Tokens adoptés** :
- **Font** : Poppins (body + display) — remplace Inter+Lexend
- **Navy scale** : palette Lineone 50→900 (`--color-navy-50` à `--color-navy-900`)
- **Primary** : `#4F46E5` (indigo-600) — remplace slate-900 comme action principale
- **Accent** : `#5F5AF6` (violet Lineone)
- **Success** : `#10B981` · **Warning** : `#FF9800` · **Error** : `#FF5724`
- **Slate-150** : `#E9EEF5` (muted spécifique Lineone, entre muted et border)
- **Shadow-soft** : `0 3px 10px 0 rgb(48 46 56 / 6%)` (signature Lineone)

**Classes composants** dans `@layer components` (`app.css`) :
- `.card` : surface + shadow-soft + rounded-lg
- `.btn` + variants `.btn-primary` / `.btn-outline` / `.btn-ghost` / `.btn-error`
- `.form-input` : border navy-300, hover + focus primary
- `.form-label` : label text-sm 500
- `.form-checkbox` : custom checkbox indigo
- `.tag` : chip filtre background slate-150
- `.link` : link primary avec hover underline
- `.badge` : pill badge uniform

**Pages reskinnées** :
- **Login** : layout Lineone (logo 2xl centré, card + inputs avec icônes peer, checkbox remember-me, séparateur "OR" skipped)
- **Admin dashboard** : metric cards avec icône colorée (primary/info/success/warning), barre de couleur sous le chiffre, hover -translate-y-0.5
- **Catalogue stub (espace client)** : même pattern metric cards + badges d'état
- **Catalogue liste** : filtres en card blanche, product cards avec tag catégorie en backdrop blur, initiale 6xl avec outline
- **Shell** : sidebar claire (fond blanc, active state indigo), header avec backdrop-blur

**Non adopté** (volontairement) :
- Alpine.js — on garde Stimulus
- Dark mode — prévu en Option B si demandé
- FontAwesome — on continue avec Heroicons inline
- Les 132 pages du template — on adopte le **visual language** pas la base de pages

Le dossier `lineone-html/` reste dans le projet comme référence visuelle (gitignoré).

**Direction** : Linear/Vercel — près-noir, typographie tendue, bordures très douces, whitespace généreux, shadows diffuses.

**Changements** :
- Primary passé de `#0F172A` (slate-900) à `#0A0A0A` (true near-black) pour un rendu plus moderne
- Font body : **Inter** (standard UI actuel) remplace Source Sans 3 — titres Lexend conservés, avec tracking serré
- Ajout tokens `--color-surface` (card backgrounds distincts du bg principal), `--color-border-soft` (bordures quasi invisibles par défaut), `--color-accent-soft` (fonds accent subtils)
- Shadows système CSS vars `--shadow-xs/sm/md/lg` avec offsets doux
- Radii centralisés `--radius-sm/md/lg/xl`
- Classes utilitaires `.card` / `.btn-primary` / `.btn-secondary` dans `@layer components`

**Landing v2** (`home/index.html.twig`) :
- Hero centré 5xl→7xl avec Lexend tracking tight
- Radial gradient accent en background (wash subtil)
- Pulse dot animé dans le badge "sur invitation"
- Proof section 3 cards avec icônes Heroicons (Sécurisé / Multi-antennes / Sans paiement)
- Footer minimaliste
- Header léger avec lien login en "→"

**Shell v4** :
- Topbar h-14 (avant h-16), backdrop-blur-xl + bg semi-transparent
- Bordures passées à `--color-border-soft` (plus douces)
- Sidebar : active state en fond `--color-primary` + texte on-primary (plus assertive, plus moderne) — remplace l'ancien bg-muted + border-l
- Avatar + déconnexion séparés par un divider vertical
- Logout en bouton icône carré (pas de label en large)
- Flash messages avec icônes check/warning inline

**Cards catalogue** :
- Ratio 4:3 avec gradient neutral + initiale 7xl (au lieu de 4xl) pour plus d'impact
- Chip catégorie en overlay backdrop-blur
- Hover : border passe en primary noir + shadow-md (au lieu de translate)
- Layout 3 colonnes max (au lieu de 4) pour plus de respiration
- Footer card avec "À partir de" + prix séparés par une bordure soft

**Stat cards (dashboards)** :
- Metric Value en Lexend 4xl tracking tight (plus bold, plus moderne)
- Labels uppercase tracking-wide (style Vercel)
- "À traiter" en couleur accent pour attirer l'œil admin
- Client : badge "Actif" avec pulse dot animé

---

## Live sync & audit (Phase live)

**Polling Stimulus** (`assets/controllers/poll_controller.js`) :
- `data-controller="poll"` + `data-poll-url-value` + `data-poll-interval-value="5000"` sur un élément avec `id` unique
- Refetch la page entière, extrait l'élément par `id`, swap DOM si HTML différent
- Skip si `document.hidden` ou si un dropdown interne est ouvert (évite fermeture involontaire)
- Utilisé pour : notifications topbar, badge statut commande (client + admin), articles commande (admin), timeline historique (client + admin)

**OrderEvent (audit trail)** :
- Entité `OrderEvent` : `actor`, `type`, `summary`, `data` (JSON), `createdAt`
- Types : `CREATED`, `STATUS_CHANGED`, `ITEMS_EDITED`, `ANTENNA_CHANGED`, `NOTES_UPDATED`, `CANCELLED`, `ADMIN_NOTE`
- Service `OrderEventLogger` enregistre sans flush (caller flush avec le changement métier)
- Visible côté admin (tous les types) et côté client (filtré, `ADMIN_NOTE` masqué)
- Actor label : `Vous` (client courant) vs `Afdal` (admin) côté client ; nom complet côté admin
- `<details>` expandable pour items_edited (diff added/removed/changed)

**Sidebar "Historique" commande** (client + admin) :
- Section dans `aside` entre "Livraison" et "Informations"
- Badge "Live" avec ping animation (dot accent + pulse)
- Timeline verticale avec dot coloré par type d'événement
- Format date FR (jours/mois custom car ICU `fr` non dispo en Twig)

**Modal "Ajouter un article"** (client, édition commande) :
- Bouton en header de la page édition (pas dans le flow form)
- Backdrop `bg-black/50 backdrop-blur-sm` + dialog `max-w-4xl max-h-[85vh]`
- Deux panneaux : liste (grid 3 cols) → détail (matrice couleur × taille)
- Recherche live dans le panneau liste
- Matrice : une ligne par couleur (avec pastille hex), colonnes = tailles triées `TU/XXS→XXXL`, input qty par variante
- Sous-total live calculé sur `input` event
- Submit : `quantities[variant_id]` array POST vers `app_order_add_item`
- Contrôleur `add_article_controller.js` (Stimulus), données produits via `<script type="application/json">`

**UX direct action** :
- Bouton "Retirer" article en édition commande : clic → ligne disparaît immédiatement (display:none + input désactivé)
- Pas de pattern mark-then-save avec overlay barré

---

## Phase A — Quick wins (Stock / Filtres / Favoris)

**Stock UI** :
- `ProductVariant` : helpers `isStockTracked()` / `isOutOfStock()` / `isLowStock($threshold=5)`
- `Product` : `isAllOutOfStock()` (tous les variants tracked en rupture) + `hasLowStockVariant()`
- Badges catalogue list (overlay image bottom-right) : `Rupture` (destructive) ou `Stock limité` (amber)
- Card en rupture : `opacity-75` + `grayscale` sur l'image
- Detail produit : stock affiché par ligne (rupture / X restants / X dispo), input `max={stock}`, row `opacity-50` + `disabled` si rupture
- Modale "Ajouter un article" : idem en cell matrice (label stock sous input)
- `quantities_controller` : `_clampInput(input)` plafonne côté client au max
- Panier : bandeau rouge si qty > stock actuel, bouton "Passer commande" désactivé (`Stock insuffisant`)
- Checkout server-side : ligne > stock → flash erreur + redirect panier (refuse création commande)
- `app_order_add_item` server-side : bloque qty > stock avec flash détaillé
- `app_cart_update` : clamp silencieux au stock disponible + flash warning

**Filtres catalogue** (`list.html.twig` + `catalogue_filters_controller.js`) :
- Facets dérivés : `color_facets` (DISTINCT color+hex) + `size_facets` (DISTINCT size, triés TU/XXS→XXXL)
- Pills couleur (dot + nom) et taille (chips `XS`/`M`/`L`…) — actif = `bg-primary text-on-primary`
- Query string : `?colors=Noir,Blanc&sizes=M,L` (comma-separated pour URL propre)
- Logique : AND entre axes (color × size dans une même variante via `innerJoin`), OR intra-axe (`IN`)
- Controller Stimulus : `toggleColor/toggleSize` → mutate hidden input + `requestSubmit()` → autosubmit

**Favoris** :
- Entité `Favorite(user, product, createdAt)` — unique index `(user_id, product_id)`
- `FavoriteRepository::findProductIdsForUser()` pour hydrater l'état dans la liste catalogue en une requête
- Controller `app_favorites_toggle` : accepte JSON (Accept/XHR) → `{favorited: true|false}` sans reload
- `favorite_button_controller.js` : toggle optimiste, revert si erreur, `aria-pressed`, cible icône (fill) + label optionnel
- Bouton cœur : sur card catalogue (top-right, overlay image, 36px rond `bg-white/90 backdrop-blur`) + sur page detail (bouton bordé avec label "Favori" / "Ajouter aux favoris")
- Page `/favoris` : grid identique au catalogue, cœur pré-rempli rouge ; empty state avec CTA vers le catalogue
- Sidebar client : entrée "Favoris" avec icône cœur duotone

---

## Phase B — Images & galerie

**Shape images (rupture de compat storage)** :
- `Product.images` (JSON) stocke désormais `array<{path: string, color: ?string}>` au lieu de `string[]`
- Migration `Version20260418212334` : normalise les entrées existantes (strings → `{path, color: null}`)
- Helpers entité : `getImages()` (shape normalisée), `getImagePaths()` (compat legacy), `getPrimaryImage()`, `getImageForColor(?string)` (fallback au primary si pas de match)
- Templates : `product.imagePaths|length` / `product.primaryImage` remplacent `product.images|first`

**Admin — drag-sort + tag couleur** :
- SortableJS ajouté via `importmap:require sortablejs` (asset-map native, pas de node)
- Controller `image_sort_controller.js` : `Sortable.create({handle: '[data-image-sort-target="handle"]'})`, `onEnd` → réindexe les `name="existing_images[N][path|color]"` pour préserver l'ordre au POST
- Chaque vignette (existante) : handle drag (icône burger coin haut-gauche), bouton retirer (coin haut-droit), badge "Principal" sur la 1ère après réindex, select couleur en pied (dropdown alimenté par les variantes du produit)
- Nouveau shape POST `existing_images[N][path]` + `existing_images[N][color]` + `remove_images[]` + `images[]` (nouveaux fichiers, color=null par défaut)
- `syncImages` : lit `existing_images` (filtré par `remove_images`), merge avec nouveaux uploads à la fin

**Variante "Dupliquer"** (admin edit produit) :
- Bouton icône copie dans chaque ligne variante → clone size/color/hex, SKU + `-COPY`, id vidé (création)

**Client — image contextuelle** :
- Gallery controller étendu : `peek(event)` (hover) + `restore()` (leave) + `defaultSrcValue` (source "stable" actualisée par `select`)
- Page detail : `data-controller="quantities gallery"` sur le `<form>` (scope commun), matrice de lignes couleurs avec `data-color-image="/uploads/x.jpg"` + actions `mouseenter->gallery#peek mouseleave->gallery#restore` (seulement si une image couleur-spécifique existe)
- Catalogue list : si un seul filtre couleur actif, l'image de la card bascule sur `imageForColor(color)` → fallback primary
- Vignettes galerie (admin + client detail) : petite pastille couleur coin bas-droit si l'image est taguée

---

## Phase C — BAT / Marquage

**Entités** :
- `MarkingAsset(orderItem, logoPath, status: pending|approved|rejected, feedback, version, uploadedBy, reviewedBy, createdAt, reviewedAt)`
- Liée par `#[ORM\OneToMany]` côté `OrderItem` (cascade persist, order by version ASC) → `item.markingAssets` / `item.latestMarkingAsset` dispo en Twig
- Enum `MarkingStatus { PENDING, APPROVED, REJECTED }` avec labels FR

**OrderEvent** : nouveaux types `TYPE_BAT_UPLOADED / APPROVED / REJECTED` (timeline filtrée côté client inchangée : `admin_note` seul est masqué)

**Upload & formats** :
- Uploads dans `public/uploads/markings/`, MIME autorisés : jpeg/png/webp/svg + **pdf** (certains clients envoient en vecto)
- Nom fichier random 24-char hex (sécurité)

**Workflow** :
1. **Upload anticipé (recommandé)** : côté catalogue/detail, la section "Ajouter un marquage" inclut un champ logo (dropzone + preview via `bat-upload` controller sans target submit). Le fichier est uploadé en `addToCart`, le path stocké dans `cart.line.marking.logo_path`. Au checkout : `logo_path` est extrait, un `MarkingAsset v1 pending` est créé automatiquement pour chaque item, puis `logo_path` est retiré du JSON `OrderItem.marking` (le logo vit désormais sur l'asset). La notif admin indique `· N BAT à valider` directement.
2. **Upload post-commande (fallback)** : si le client n'a pas joint de logo, le bloc BAT s'affiche sur `/commandes/{ref}` avec le formulaire dropzone → même flow pending.
3. Panier affiche miniature logo (ou pastille PDF) + badge "Logo joint" en vert, ou alerte ambre "Logo à fournir après commande" si aucun
3. Admin reçoit notification + widget "BAT à valider" sur dashboard + bouton Valider / Refuser + motif sur order detail
4. Approve → notif client success ; Reject avec motif obligatoire → notif client warning
5. Client re-upload si refusé → v2, v3… (chaque version conservée pour audit)
6. Bouton upload visible côté client tant que `order.status ∈ {placed, confirmed}` — désactivé une fois en production

**Controller** (`BatController` + `Admin\BatBulkController`) :
- `POST /commandes/{ref}/bat/upload/{item}` — client (ou admin sur commande propriétaire)
- `POST /commandes/{ref}/bat/approve/{asset}` — admin, un BAT
- `POST /commandes/{ref}/bat/reject/{asset}` — admin, `feedback` obligatoire
- `POST /commandes/{ref}/bat/approve-all` — admin, **valide tous les BAT pending d'une commande en un clic** (1 notif groupée client : "N BAT validés · Commande X")
- `POST /admin/bat/bulk-approve` — admin, **validation cross-orders** (payload `assets[]=id&assets[]=id…`), regroupe les notifs par commande
- `MapEntity` pour reference↔Order, id↔OrderItem/MarkingAsset

**UI validation groupée** :
- Admin order detail : bouton "Valider tous les BAT" dans l'en-tête section Articles (visible seulement si `has_pending_bats`), avec confirmation
- Dashboard widget : chaque carte BAT devient une `<label>` avec checkbox (rangée en `has-[:checked]:bg-emerald-50/40` pour feedback visuel), header avec "Tout sélectionner" (indeterminate supporté) et bouton "Valider la sélection (N)" désactivé si aucune coche
- Controller Stimulus `bat-bulk` : toggle/sélection, compteur live, gestion état indeterminate

**UI** :
- Bloc BAT client : icône check + titre "BAT marquage", upload form (si pas encore ou rejeté), aperçu miniature 16×16 (PDF → icône doc), badge statut, feedback affiché en rouge si rejeté
- Bloc BAT admin : même structure, boutons Valider (vert) / Refuser avec textarea `bat_review_controller.js` (toggle show/hide, focus auto), historique `<details>` des versions
- Widget dashboard admin : bordure gauche ambre, grid 2 colonnes de miniatures cliquables → order detail, compteur badge
- `NotificationService::notifyCompany(Company)` ajouté (notifie tous membres actifs d'une entreprise)

---

## Phase D — Livraison & suivi

**Champs `Order`** : `carrier` (80), `trackingNumber` (80), `estimatedDeliveryAt` (datetime), `setShippedAt()` exposé public · `getTrackingUrl()` génère l'URL transporteur selon pattern (Chronopost, Colissimo, DPD, UPS).

**Admin** — section "Expédition" sur `admin/order/detail` (visible si status ∈ confirmed/in_production/shipped/delivered) : select transporteur + input n° suivi + date picker ETA · route `POST /admin/commandes/{ref}/shipping` · flash info si inchangé, notif client si diff.

**Client** — bloc livraison enrichi : transporteur, n° de suivi mono-font, lien "Suivre le colis" (si transporteur connu), ETA avec countdown "dans Nj".

**OrderEvent** : `TYPE_SHIPPING_UPDATED` avec data `{carrier, tracking, eta}`.

---

## Phase E — Multi-utilisateurs par entreprise

**Enum** `CompanyRole { OWNER, MEMBER }` · champ `User.companyRole` (nullable, null pour admins Afdal).

**Migration** : backfill des `CLIENT_MANAGER` existants avec company → `owner`.

**Invitations** : nouveau champ `Invitation.companyRole` (default MEMBER). Admin Afdal invite → OWNER. Owner invite via `/parametres` → MEMBER.

**Sécurité** : `User::getRoles()` ajoute `ROLE_COMPANY_OWNER` si `companyRole === OWNER`. Les routes team dans `SettingsController` sont gardées par `isCompanyOwner()`.

**UI `/parametres` section Équipe** (OWNER uniquement) :
- Liste membres avec badges rôle + statut actif
- Bouton "Retirer" (hors soi-même, hors autres OWNERs)
- Invitations en cours avec lien token copiable + révocation
- Form "Inviter un membre" (email → MEMBER)

**`RegistrationController`** : applique `invitation.companyRole` sur le nouveau User.

---

## Phase F — Pricing B2B

**Entités** :
- `PriceTier(product, minQty, unitPriceCents)` — index unique (product, minQty)
- `CompanyPrice(company, product, unitPriceCents)` — index unique (company, product) = tarif négocié

**Service `PricingService::resolveUnitPrice(product, company, qty)`** — ordre :
1. CompanyPrice négocié → override absolu
2. Palier volume le plus élevé dont `minQty ≤ qty`
3. Fallback `product.basePriceCents`

**Wiring** :
- `Cart::lines()` : agrège qty par produit, applique pricing résolu
- `OrderController::addItem()` (re-commande) : utilise pricing
- `CheckoutController` : l'OrderItem freeze le prix résolu dans `unitPriceCents` (déjà le cas via Cart)

**Admin produit** : section "Barème volume" avec table éditable (add/remove paliers) · Controller `price-tiers` Stimulus pour UI dynamique.

**Catalogue detail** :
- Si CompanyPrice : badge vert "Tarif négocié" + prix HT / pièce + prix standard barré
- Sinon : tarif de base + pills dégressifs "dès N → X€" ligne horizontale

**CGV** : page `/cgv` + `/mentions-legales` (controller `LegalController`, contenu template twig inline) · checkbox obligatoire au checkout côté form + validation server-side dans `CheckoutController`.

---

## Phase G — Messagerie commande

**Entité `OrderMessage(order, author, body, createdAt, readByClientAt, readByAdminAt)`** · auto-mark read côté auteur à la création.

**Repository** : `findForOrder`, `countUnreadForClientInCompany`, `countUnreadForAdmin`, `markAllReadForClient/Admin`.

**Controller `POST /commandes/{ref}/messages/new`** — author = current user, destinataire opposé notifié. Admin écrit → notif groupée entreprise. Client écrit → notif admins.

**UI `order/_conversation.html.twig`** (include factorisé client + admin) :
- Bulles alternées : self à droite (primary), admin à gauche (sky), client à gauche (muted)
- Label auteur + timestamp compact
- Scroll max-h-96
- Textarea + bouton Envoyer avec `data-poll-skip` (empêche le poll d'écraser la saisie en cours)
- Polling 5s du conteneur pour feed live

**Read markers** : au chargement de detail (client ou admin), les messages non lus sont marqués lus via `markAllReadForClient/Admin`.

---

## Phase H — Export CSV + Analytics

**Chart.js** via `importmap:require chart.js` + controller Stimulus `chart` générique (type/payload/horizontal en values).

**Exports** (`OrderExporter` étendu, UTF-8 BOM + séparateur `;` compat Excel/Sheets) :
- Commandes détaillées : filtres statut/entreprise/période · 1 ligne par article
- CA par entreprise : période avec total cumulé
- Annuaire entreprises : toutes companies avec métriques (membres, antennes, commandes, CA)

**Page `/admin/exports`** : 3 cards avec filtres dédiés.

**Page `/admin/analytics`** :
- Stat cards : CA total, commandes, ticket moyen, livrées
- Line chart : CA 12 mois glissants
- Doughnut : distribution statuts actifs (couleurs Afdal)
- Bar horizontal : top 10 produits (qty)
- Liste : top 10 entreprises avec rang, nb commandes, CA

**Entrées sidebar admin** : "Analytics" (icône chart) + "Exports" (icône download).

---

## Phase I — Reset mot de passe

**Stack** : `symfony/mailer` + `symfonycasts/reset-password-bundle` (installés via composer, entité + controller scaffold générés).

**Config** :
- `MAILER_DSN=null://null` par défaut (dev)
- SMTP o2switch (prod) : `MAILER_DSN=smtp://user:pass@mail.o2switch.net:587?encryption=tls` (à configurer en `.env.local.prod`)
- Expéditeur hardcodé `no-reply@afdal.fr` — à remplacer par `MAILER_FROM` env plus tard

**Dev fallback** : quand `MAILER_DSN=null://...`, le controller catche l'erreur mailer et flash `reset_password_dev` avec l'URL de reset. Le template `check_email.html.twig` affiche un bloc ambre "Dev · mailer désactivé" avec l'URL copiable → permet de tester le flow sans SMTP.

**Templates thémés** :
- `request.html.twig` : formulaire email, card centrée, flash errors
- `check_email.html.twig` : confirmation + fallback dev + retour login
- `reset.html.twig` : nouveau mot de passe (formulaire reset-password-bundle)

**Login** : lien "Mot de passe oublié ?" sous le bouton "Se connecter".

**Flow prod** : `/reset-password` → form email → email avec lien → `/reset-password/reset/{token}` → nouveau MDP → redirect login.

---

## Itérations post-livraison

*Corrections et ajouts appliqués après la validation des phases D→I.*

**Admin — UI tarifs négociés** (manquait, complétée) :
- `/admin/entreprises/{id}` : nouvelle section "Tarifs négociés" (après les statistiques)
- Liste les `CompanyPrice` existants avec bouton "Retirer" (confirmation)
- Form d'ajout : select produit (Tom Select recherchable, dropdown porté sur `<body>`) + prix unitaire HT → `app_admin_company_price_save`
- Le `CompanyPrice` override tout (palier, base) pour cette entreprise sur ce produit

**Catalogue detail — recalcul live PricingService** :
- Le controller Stimulus `quantities` reçoit désormais :
  - `unit-price` = `negotiated_price_cents` si dispo, sinon `basePriceCents` (écrasé selon qty si tiers)
  - `base-price` = `product.basePriceCents` (toujours, pour calcul savings)
  - `negotiated` (bool) — si true, ignore les paliers
  - `tiers` (array `{min_qty, unit_cents}`)
- Résolution live côté client : `negotiatedValue ? unitPrice : tier-best-matching-totalQty`
- Total recalculé à chaque input

**Catalogue detail — feedback savings** :
- Prix unitaire effectif affiché sous le total en gros
- Badge vert "Vous économisez X € (N%) vs tarif standard" → visible dès que l'utilisateur gagne par rapport au `basePrice` (négocié OU palier déclenché)
- Hint ambre "Ajoutez N pièce(s) pour passer à X€/pc" → uniquement sans tarif négocié ET qu'un prochain palier existe

**Invitation admin Afdal** (extension Phase E) :
- `Invitation.company` passé en nullable + nouveau champ `Invitation.targetRole` (UserRole, default `CLIENT_MANAGER`) + migration
- Form `/admin/invitations/new` : sélecteur à 3 modes (client entreprise existante / client nouvelle entreprise / **employé Afdal**) — le 3e est stylé primary pour trahir le niveau de privilège
- Mode Afdal : pas de company, `targetRole=ADMIN`, encart explicatif sur les droits accordés
- `RegistrationController` branche sur `invitation.isAdminInvitation()` → user sans company + `role=ADMIN`
- Liste `/admin/invitations` : badge primary "Employé Afdal" à la place du nom d'entreprise pour les invitations admin
- Controller `mode-switch` accepte désormais 3 targets (existing / new / afdal)

**Bugfixes visuels collectés** :
- Tom Select dropdown : `dropdownParent: 'body'` (échappe les stacking contexts), `z-index: 1000` sur `.ts-dropdown`
- Input prix HT : remplacé `position: absolute` du `€` par un layout flex avec 2 cellules (input + suffix) — plus de collision possible même avec des valeurs longues
- Twig strict_variables : `marking.logo_path` → `marking.logo_path|default(null)` pour tolérer les markings legacy sans logo

**Bugfixes métier** :
- BAT upload condition élargie : `can_upload` = `status not in ['shipped', 'delivered', 'cancelled']` (était trop restreint à placed/confirmed uniquement)
- Erreur upload 2Mo diagnostique : le `BatController::upload` traduit `UPLOAD_ERR_INI_SIZE` en message clair + `public/.user.ini` relève les limites PHP à 20Mo (pris en compte par PHP-FPM o2switch + symfony serve)
- Polling 5s : skip si `[data-poll-skip]` descendant visible OU si focus sur un input/textarea dans la zone polled → n'écrase plus les saisies en cours (BAT rejet, messagerie, etc.)

---

## Tests

**Smoke test console** (`php bin/console app:smoke-test`) :
- Exercice en transaction rollback de tous les flows critiques : création entreprise/antenne/users (admin/owner/member), produit avec paliers + tarif négocié, vérification `PricingService`, favori, commande PLACED avec BAT pending, validation admin, transition CONFIRMED→IN_PRODUCTION→SHIPPED avec carrier+tracking, messagerie owner↔admin, invitation membre
- Flag `--keep` pour conserver les données (debug)
- Exit code 0 si tout OK, 1 si moindre échec
- Temps moyen ~1s — à lancer après chaque modif structurelle

**Tests fonctionnels PHPUnit** (`./vendor/bin/phpunit`) :
- Stack : `symfony/test-pack` (WebTestCase + BrowserKit) + `dama/doctrine-test-bundle` (wrap chaque test en transaction, rollback auto)
- DB dédiée : `afdal_dev_test` en Postgres (config dans `.env.test.local`)
- Bundle `DAMADoctrineTestBundle` activé `test-only` dans `config/bundles.php` + `config/packages/test/dama_doctrine_test.yaml`
- Trait `TestDataTrait` pour helpers `createCompanyWithAntenna() / createUser() / createProduct()`

**Suite actuelle (16 tests, <1s)** :
- `SmokeHttpTest` : routes publiques (home, login, forgot, cgv), redirections auth (catalogue, admin)
- `AuthTest` : login client/admin, interdit client sur /admin (403)
- `OrderFlowTest` : cycle complet passer commande → confirm admin, validation CGV obligatoire
- `PricingServiceTest` : base / palier volume / tarif négocié
- `ResetPasswordTest` : request reset (avec fallback dev URL flash) + email inconnu (silent redirect)

**Lancer les tests** :
```bash
# Smoke rapide (dev DB, transaction rollback)
php bin/console app:smoke-test

# Tests fonctionnels (test DB séparée)
createdb afdal_dev_test   # une fois
php bin/console doctrine:migrations:migrate --env=test --no-interaction
./vendor/bin/phpunit
```

**Conventions retenues** :
- Dama rollback → pas besoin de setUp/tearDown pour nettoyer la DB
- Pas de fixtures partagées → chaque test crée ses données via `TestDataTrait`
- Suffixes aléatoires `bin2hex(random_bytes())` pour éviter collisions (email/slug/sku uniques)

**À ajouter plus tard** : Panther pour tester les Stimulus controllers JS-lourds (matrice catalogue detail, drag-sort images admin, polling live messagerie, BAT upload preview).

---

## Roadmap — Phases restantes

*Toutes les phases D→I ont été livrées. Ce qui suit est la spec détaillée d'origine, conservée comme référence.*

### Phase D — Livraison & suivi (~1j)

**Entité `Order`** : ajouter 4 champs
- `carrier` (string 80) — Chronopost, Colissimo, DPD, autre…
- `trackingNumber` (string 80, nullable)
- `estimatedDeliveryAt` (datetime, nullable)
- `shippedAt` (datetime, nullable — déjà géré implicitement par transition SHIPPED)

**Admin** — dans le panneau statut de `admin/order/detail.html.twig`
- Form inline "Expédition" visible quand status ∈ {confirmed, in_production, shipped}
- Champs : transporteur (select pré-rempli), n° suivi (input), date livraison estimée (date picker)
- À la transition vers `SHIPPED` : `shippedAt = now()` auto + notif client enrichie

**Client** — `order/detail.html.twig`
- Bloc "Livraison" enrichi : transporteur + n° suivi + "Suivre le colis" (lien externe vers transporteur selon pattern URL)
- Timeline : step "Expédiée" affiche n° suivi et transporteur
- Si `estimatedDeliveryAt` : countdown "Livraison estimée dans N jour(s)"

**URLs transporteurs** (pattern statique)
- Chronopost : `https://www.chronopost.fr/tracking-no-cms/suivi-page?listeNumerosLT={tracking}`
- Colissimo : `https://www.laposte.fr/outils/suivre-vos-envois?code={tracking}`
- DPD : `https://www.dpd.fr/trace/{tracking}`

**OrderEvent** : nouveau type `TYPE_SHIPPED` enrichi avec carrier+tracking dans `data`

---

### Phase E — Multi-utilisateurs par entreprise (~1-2j)

**Refacto relations**
- Actuel : `Company.manager` (ManyToOne vers User, unique). Supprimer.
- Nouveau : `User.company` ManyToOne (existe déjà), inverse `Company.members` OneToMany
- Ajouter `User.companyRole` enum `OWNER|MEMBER` (nullable, null pour admins Afdal)

**Migration Doctrine**
- Pour chaque Company : l'ancien manager devient `companyRole=OWNER`, les autres users déjà rattachés deviennent `MEMBER`
- Drop `companies.manager_id`

**Sécurité & rôles**
- Voter `CompanyVoter` : MEMBER peut lister/voir commandes + passer commande ; OWNER ajoute gestion équipe + CGV
- `Invitation` : ajouter `companyRole` cible (owner peut inviter des members ; admin Afdal peut inviter owners)

**UI `/parametres`** (nouveau panneau)
- Section "Équipe" (visible OWNER uniquement) : liste des membres + rôle + bouton "Retirer"
- Form "Inviter un membre" : email + rôle par défaut MEMBER
- Invitation suit le flow existant (lien unique + expiration)

**UI admin**
- Page company detail : liste membres avec badges rôle (OWNER/MEMBER)
- Invitation admin : cible désormais "Entreprise" dans un dropdown (plus juste email)

**Ordres d'idée** : pas de notion d'organisation au-dessus de Company, reste en multi-company plat

---

### Phase F — Pricing B2B (~2-3j)

**Entités tarification**
- `PriceTier(product, minQty, unitCents)` — barème volume : ex. 1-49=15€, 50-99=12€, 100+=10€
- `CompanyPrice(company, product, unitCents)` — tarif négocié pour UNE entreprise, override le barème

**Logique résolution prix** (service `PricingService`)
- Pour un (product, company, qty) : retourne `unitCents` = `CompanyPrice` si existe, sinon palier `PriceTier` le plus proche au-dessus de qty, sinon `Product.basePriceCents`
- Applique sur TOUS les touchpoints : cart subtotal, order creation (freeze au moment du placement dans `OrderItem.unitPriceCents` → déjà le cas)

**Admin**
- Page produit : nouvelle section "Barème volume" avec table éditable (add/remove paliers)
- Page company : nouvelle section "Tarifs négociés" avec table (product searchable + prix unitaire + bouton add/remove)

**Catalogue client**
- Detail produit : table paliers affichée sous le total ("Prix unitaire diminue à partir de N pièces")
- Cart : `unit_price_cents` recalculé live via Stimulus en fonction de la qty totale produit — indicateur quand franchissement palier ("Ajoutez X pour passer à Y€/pc")
- Detail commande : si la commande comporte des prix négociés, petit badge "Tarif négocié" sur la ligne

**CGV**
- Entité `LegalDocument(slug, title, content, publishedAt)` ou fichier statique selon préférence → discussion
- Page `/cgv` publique (render markdown via `michelf/php-markdown` ou natif Twig)
- Checkbox "J'accepte les [CGV](/cgv)" au checkout, obligatoire, traçage dans `Order.cgvAcceptedAt` + version CGV acceptée

---

### Phase G — Messagerie commande (~1j)

**Entité** `OrderMessage(order, author, body, createdAt, readByClient, readByAdmin)`
- Index `(order_id, created_at)`
- `readByClient` / `readByAdmin` : `?\DateTimeImmutable` (null = non lu)

**Repository**
- `findForOrder(Order)` chronologique
- `countUnreadForClient(Order)` / `countUnreadForAdmin(Order)`
- Marquage en masse au chargement du detail (`markAllReadForClient` / `markAllReadForAdmin`)

**UI** — aside sur `order/detail.html.twig` (client + admin)
- Section "Conversation" avec liste messages (bulles alternées client/admin, timestamp, auteur)
- Textarea en bas + bouton "Envoyer"
- Polling 5s (réutilise `poll_controller`)
- Protège le saisir en cours avec `data-poll-skip` (même pattern que BAT review)

**Sidebar globale**
- Badge compteur "N messages non lus" sur l'entrée "Mes commandes" (client) ou "Commandes" (admin)
- Topbar : petite icône bulle avec count total, clic → dropdown listant les 5 commandes avec messages non lus

**Différence avec notes** : les notes sont 1-shot + non versionnées, la messagerie est multi-tours + historique conservé

---

### Phase H — Export + Analytics admin (~2j)

**Exports CSV** (service `CsvExporter`)
- Commandes : filtres période/statut/entreprise, colonnes réf + date + entreprise + antenne + statut + pièces + CA HT
- CA par entreprise : période + entreprise, lignes mensuelles, total annuel
- Annuaire entreprises : toutes les companies avec membres, antennes, compteurs commandes et CA total

**UI export**
- Page `/admin/exports` avec 3 cards (1 par export) contenant les filtres + bouton "Télécharger le CSV"
- `StreamedResponse` pour gros volumes

**Analytics** — nouvelle page `/admin/analytics`
- Installer **Chart.js** via `importmap:require chart.js` (pas de node)
- Line chart : CA par mois sur 12 mois glissants
- Bar chart horizontal : top 10 produits (qty vendue)
- Bar chart : top 10 entreprises (CA)
- Donut : distribution statuts commandes actives
- Stat cards : CA total année, commandes totales, ticket moyen, taux conversion (placed→delivered)

**Performance**
- Cache queries sur 5min avec `CacheInterface` (pas d'invalidation fine, simple TTL)
- Pagination ou limite sur les listes

---

### Phase I — Reset mot de passe (~1j)

**⚠️ Bloqueur** : dépend du choix Mailer

**Option A** — Mailer complet (recommandé, cohérent avec o2switch)
- `composer require symfony/mailer symfonycasts/reset-password-bundle`
- Dev : Mailpit via Docker Compose (`docker-compose.mailpit.yml`, port 1025/8025) — seule dépendance Docker du projet, optionnelle
- Prod : SMTP o2switch via `MAILER_DSN=smtp://user:pass@mail.o2switch.net:587?encryption=tls`
- Flow standard reset-password-bundle : `/mot-de-passe-oublie` → email avec token signé → `/reset/{token}` → nouveau MDP

**Option B** — Admin-driven (pas d'email)
- Route client `/mot-de-passe-oublie` : form email → crée un `ResetRequest(email, token, expiresAt, usedAt)`
- Admin reçoit notification in-app "Reset demandé par X"
- Admin copie le lien (`/reset/{token}`) via bouton "Copier le lien" sur page dédiée → l'envoie au client par son canal habituel (Slack/SMS/tel)
- Client ouvre le lien → new password

**Décision attendue** : A ou B ? (A ouvre la voie pour Phase "Emails transactionnels" plus tard : confirmation commande, BAT validé, expédition)

---

### Estimation totale restante : **~8-11 jours**

Ordre suggéré : **D → E → F → G → H → I** (dépendances : F nécessite les membres multiples de E pour tests significatifs ; G bénéficie de E ; I indépendant mais bloqué par décision Mailer).

---

## Patterns Tailwind partagés

```html
<!-- Primary CTA -->
<button class="inline-flex items-center gap-2 px-5 py-2.5 rounded-md bg-[var(--color-primary)] text-[var(--color-on-primary)] font-medium cursor-pointer transition-colors duration-200 hover:bg-[var(--color-secondary)] focus-visible:outline-2 focus-visible:outline-[var(--color-ring)] focus-visible:outline-offset-2 disabled:opacity-50 disabled:cursor-not-allowed">
  Action
</button>

<!-- Card -->
<div class="bg-white rounded-xl border border-[var(--color-border)] shadow-sm p-6">
  ...
</div>

<!-- Input -->
<label class="block">
  <span class="text-sm font-medium text-[var(--color-foreground)] mb-1.5 block">Label</span>
  <input class="w-full px-3 py-2 rounded-md border border-[var(--color-border)] bg-white focus-visible:outline-2 focus-visible:outline-[var(--color-ring)]" />
  <span class="text-xs text-[var(--color-secondary)] mt-1 block">Helper text</span>
</label>

<!-- Status badge -->
<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-sky-50 text-sky-700 border border-sky-200">
  <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>
  Placée
</span>
```

---

## Pré-livraison (checklist)

- [ ] Pas d'emojis comme icônes (SVG uniquement)
- [ ] `cursor-pointer` sur tous les éléments cliquables
- [ ] Transitions hover 150–300ms
- [ ] Contraste texte ≥ 4.5:1
- [ ] Focus visible (clavier)
- [ ] `prefers-reduced-motion` respecté
- [ ] Responsive : 375px · 768px · 1024px · 1440px


---

## Refonte fiche produit client — `templates/catalogue/detail.html.twig` (2026-04-19)

**Objectif** : moderniser la page produit côté client. Look éditorial clair (Aesop/COS-inspired), plus d'air, matrice de variantes en pleine largeur.

**Structure** :
- Breadcrumb fin `Catalogue / {category}` (remplace bouton Retour)
- **Hero** 2 colonnes (5/7 sur lg, grid-cols-12) :
  - Image principale `aspect-[4/5]` `rounded-3xl`
  - Thumbnails horizontales scrollables, ring 2px offset au lieu de border
  - Colonne droite : catégorie micro-label, titre `font-display text-5xl`, matière, description `max-w-prose`, prix XXL `text-5xl font-display`, tiers en pills rondes
  - Bouton favori : icône seule dans pastille ronde 44px (label sr-only)
- **Section 02 — Configuration** : matrice full-width dans card `rounded-2xl`, lignes fines
- **Section 03 — Personnalisation** : marquage en `<details>` stylé (icône ronde primary-light + chevron rotatif)
- **Barre d'action flottante** `fixed bottom-4` : `backdrop-blur-xl`, shadow douce, total XXL + CTA noir avec flèche

**Pourquoi `fixed` et pas `sticky`** : sticky testé (top header + wrapper interne) mais ne s'est pas déclenché en Chrome sur cette app → fallback `fixed` qui marche toujours. Padding `pb-32` sur le form pour compenser la hauteur de la barre.

**Préservation** : tous les data-controllers (`quantities`, `gallery`, `favorite-button`, `bat-upload`) et leurs targets/actions conservés.


---

## Analytics client (2026-04-19)

Nouvelle page `/analytics` côté client, scopée à `app.user.company`.

**Fichiers créés** :
- `src/Controller/ClientAnalyticsController.php` — 4 queries DBAL (monthly, top_products, status_dist, totals) filtrées `WHERE o.company_id = :company`
- `templates/dashboard/analytics.html.twig` — 4 KPI cards + 3 charts (line CA 12m, doughnut statuts actifs, bar Top 5 produits)

**Nav** : entrée "Analytics" ajoutée dans `_shell.html.twig` entre "Mes antennes" et "Paramètres" (icône chart existante).

**Différences vs admin** :
- KPI 4ème = "En cours" (placed+confirmed+in_production) au lieu de "Livrées"
- Pas de top entreprises (une seule = la sienne)
- Top 5 produits au lieu de 10
- Titre montre le nom de l'entreprise

**Chart.js + `chart_controller.js` réutilisés tels quels** (déjà dans importmap + controllers).


### MAJ Analytics — 3 blocs supplémentaires (2026-04-19)

- **Alerte Commandes en retard** en haut de page (rouge) : card avec liste des 5 premières commandes `estimated_delivery_at < NOW() AND status NOT IN (delivered, cancelled)`. N'apparaît que si count > 0.
- **KPI Économies** : 5ème card de la rangée KPI, style emerald, calcule `SUM(GREATEST(products.base_price_cents - order_items.unit_price_cents, 0) * quantity)` — valorise les tarifs négociés.
- **Commandes par antenne** (bar horizontal) : en bas à droite à côté du Top produits, groupé sur `antennas.name`. Fallback empty state si aucune commande avec antenne.


### Dashboard admin — tuiles KPI style "Lineone Travel" (2026-04-19)

Les 4 tuiles KPI du jour (`templates/dashboard/admin_stub.html.twig`, début du `dashboard-grid`) reprennent le style Lineone :
- Fond gradient plein (bleu / orange / indigo / rose)
- Texte blanc pur en inline style (`rgba(255,255,255,0.9)` pour titre, `#fff` pour lien "Voir les commandes" avec `border-bottom: 1px dotted`) — Tailwind `text-white/85` et `text-blue-100` ne rendaient pas correctement
- Forme SVG décorative (cercle/hexagone/polygone) positionnée en inline style `top:12px;right:-16px;width:80px;height:80px` avec `fill="rgba(255,255,255,0.22)"` — le modificateur `text-white/20` sur `fill="currentColor"` ne marchait pas
- z-index : texte en `relative z-10` pour rester au-dessus de la forme

Section **Pipeline** supprimée en bas du dashboard admin (redondante avec les tuiles KPI + activité récente). Méthode `computePipeline()` du controller aussi retirée.

**Top clients** (`templates/dashboard/admin_stub.html.twig`, bloc col-span-4) refondu :
- Avatar 44px avec initiales de l'entreprise (2 premières lettres, `|split(' ')|slice(0,2)|map(w => w|first)`)
- Palette cyclée sur 5 couleurs pastel (`#DBEAFE/#1D4ED8`, `#FEE2E2/#B91C1C`, `#FEF3C7/#B45309`, `#D1FAE5/#047857`, `#E0E7FF/#4338CA`) via `loop.index0 % 5`
- "Voir tout" en haut-droite (pointillé), pointe vers `app_admin_companies`
- `divide-y` entre rangées au lieu de `space-y-3`


### Cards antennes — style gradient border + avatar contact (2026-04-19)

`templates/antenna/list.html.twig` refondu inspiré design "classes" :
- **Bordure gauche gradient** (span absolu `w-1.5`) avec palette cyclée 5 couleurs (`loop.index0 % 5`) : bleu→violet, rose→rouge, ambre→rouge, émeraude→cyan, indigo→pourpre
- **Icône localisation** dans carré 80px rounded-2xl avec même gradient + shadow colorée (`0 8px 20px -6px {{ palette.from }}66`)
- **Badge téléphone** en bas : inline-flex gradient même palette, texte blanc
- **Avatar contact** (initiales contactName, 40px rond, border blanche) + **bouton flèche** (rond 40px, muted → primary au hover) en bas de la card
- Boutons edit/delete en haut-droite avec `opacity-0 group-hover:opacity-100` (révélés au survol), fond `bg-white/80 backdrop-blur`
- Card cliquable via pattern `before:absolute before:inset-0` sur le `<a>`
- `flex flex-col` + `min-height: 340px` sur l'article + `mt-auto pt-6` sur la rangée avatar/flèche : hauteur homogène et alignement bas propre

**Page détail antenne** (`templates/antenna/detail.html.twig`) alignée sur le même langage :
- Palette déterminée par `antenna.id % 5` (même couleur entre liste et détail pour une antenne donnée)
- Hero card en haut : bordure gauche gradient + carré icône 80px gradient + titre/adresse + badge téléphone (identique aux cards liste)
- Card "Contact responsable" : avatar 44px initiales (contactName) + gradient palette, sous-titre "Responsable", email/phone en liens hover primary
- **4 stats cards** : chacune avec icône 40px dans carré rounded-xl gradient dédié (commandes bleu/indigo, pièces émeraude/cyan, revenue ambre/rouge avec text-fill gradient, last order rose/violet)
- **Graph 6 mois** : barres en gradient palette antenne avec shadow colorée + tooltip au hover en gradient
- **Top produits** : avatars pastel cyclés (5 palettes bg+fg) avec numéro, `divide-y` entre rangées, toute la ligne cliquable
- **Historique commandes** : remplacé `<ol class="timeline">` par cards rounded-xl avec icône panier status-colorée + badge statut + barre de progression en bas (réutilise `status.progressColor()` / `progressPct()` comme l'activité récente du dashboard admin)


### Nav admin — lien Paramètres (2026-04-19)

Ajout d'une entrée `{ route: 'app_settings', label: 'Paramètres', icon: 'cog' }` dans `nav_items` admin de `templates/dashboard/_shell.html.twig`. Le `SettingsController` client est réutilisé tel quel — le template gère déjà le cas admin en masquant :
- Champ Entreprise (condition `if not app.user.isAdmin()`)
- Onglet Équipe (condition `if is_owner` — admin n'est pas company owner → false)

Admin voit donc : Profil (email readonly + nom) + Sécurité (changement mot de passe). Pas besoin de nouveau controller ni template.


### Timeline événements commande — refonte unifiée (2026-04-20)

Partial partagé `templates/order/_timeline.html.twig` utilisé côté client (`order/detail.html.twig`) et admin (`admin/order/detail.html.twig`) — supprime la duplication de ~80 lignes.

- **Filtre Twig `time_ago`** ajouté dans `src/Twig/AppExtension.php` : rend "à l'instant / il y a X min / il y a X h / hier / il y a X j / date" en français
- **Table `event_config`** mappe chaque `event.type` (created, status_changed, items_edited, cancelled, admin_note, bat_*, shipping_updated, antenna_changed, notes_updated) vers `{title, bg, fg}` — couleurs pastel dédiées par type d'évènement
- **Dot icône** 32px rond avec bg/fg du type, SVG choisi par type
- **Chip actor** : mini-avatar 20px initiales en gradient palette du type + nom ("Afdal · X" pour admin events, nom seul pour client)
- **Titre + description + time_ago** : titre bold (label du type), summary en secondaire, temps relatif aligné à droite
- **Ligne connecteur** : span absolu `left-4 top-9 bottom-0 w-px` (masqué sur le dernier item via `not loop.last`)


### Suivi commande (stepper) — refonte colorée (2026-04-20)

Bloc "Suivi" de `templates/order/detail.html.twig` (stepper Placée → Confirmée → En production → Expédiée → Livrée) refondu dans le même langage visuel :
- Dot 40px rond coloré avec la `status.progressColor()` (indigo/rose/ambre/sky/émeraude) au fond 22% d'opacité + icône SVG dédiée par statut (facture, check-circle, cog, truck, package)
- Étape courante : ring coloré 4px + shadow colorée pour faire pulser visuellement
- Étapes à venir : gris muted `#F1F5F9` / `#94A3B8`
- Titre bold + description (ex. "Impression et fabrication en cours") pour chaque statut + timestamp relatif via `time_ago`
- Connecteur fin avec la couleur du status à 40% d'opacité (étapes terminées) ou grey (à venir)
- Card "cancelled" : même style mais rounded-xl + description "Cette commande a été annulée et ne sera pas traitée"


### Accès non authentifié — redirection vers / (2026-04-20)

Nouvelle classe `src/Security/HomeEntryPoint.php` implémentant `AuthenticationEntryPointInterface` : redirige systématiquement vers `app_home` (`/`) au lieu de `/login` quand un visiteur non connecté atteint une URL protégée.

Wiring dans `config/packages/security.yaml` sous la firewall `main` : `entry_point: App\Security\HomeEntryPoint`. Le `form_login.login_path` reste sur `app_login` (route qui sert le formulaire), seul le comportement "unauthenticated access → redirect" change.

Les 403 réels (utilisateur connecté sans permission, ex. client qui tente `/admin`) restent gérés par Symfony normalement.


### Bloc "À traiter en priorité" — refonte Lineone (2026-04-20)

`templates/dashboard/admin_stub.html.twig` : section alertes critiques refondue avec macro partagée `alert_card(title, count, palette, icon_svg, items, footer_note)`.

- **En-tête** : icône gradient ambre→rouge 32px + titre + sous-titre "Actions urgentes à réaliser maintenant" (au lieu du gradient background amber)
- **Chaque alerte est une card** `rounded-xl bg-white` avec :
  - Bordure gauche gradient (2 couleurs par type) — même pattern que les antennes
  - Icône gradient 36px dans carré `rounded-lg` avec shadow colorée + titre + badge count à droite (gradient aussi)
  - Liste items en `divide-y` : ref mono + nom client + meta colorée à droite
  - Footer note optionnelle en italique
- **Palettes** : livraisons retard (rouge→rose), BAT refusés (rose→cramoisi), stock (ambre), bloquées (orange), messages (indigo)
- **Hover** : `-translate-y-0.5` + `shadow-md` comme les cards antennes
- Macro Twig `_self.alert_card(...)` supprime la duplication HTML des 5 types d'alertes


### Préparation déploiement o2switch (2026-04-20)

URL prod : `https://afdal.sora3439.odns.fr`. Email applicatif : `afdal@sora3439.odns.fr` (SMTP via `mail.sora3439.odns.fr:465` SSL, username doit encoder `@` en `%40`).

Fichiers ajoutés :
- `public/.htaccess` — rewrite Apache standard Symfony + compression + cache static assets + bloc HTTPS commenté (à activer après AutoSSL)
- `.env.prod` — template commité (APP_ENV=prod, DEFAULT_URI, MESSENGER_TRANSPORT_DSN=doctrine) sans secrets
- `DEPLOY.md` — checklist 10 étapes (PostgreSQL, upload, `.env.prod.local`, migrations, permissions, admin user, SSL, troubleshooting)
- `.gitignore` mis à jour : `public/uploads/markings/*` et `public/uploads/products/*` exclus avec `.gitkeep` préservés

**Non résolu mais documenté** : document root à pointer sur `~/afdal/public/` via cPanel (ou symlink si indispo). Paths uploads restent dans `public/uploads/` — acceptable sur o2switch avec permissions correctes, à surveiller.


### Bascule PostgreSQL → MySQL pour o2switch (2026-04-20)

**Contexte** : premier déploiement sur mutualisé o2switch. PostgreSQL 9.6 local (EOL depuis 2021, incompatible avec le schéma Doctrine généré pour PG 17). Neon/Supabase externes bloqués par le firewall sortant TCP 5432. Budget serré → pas d'option VPS.

**Décision** : migrer l'app sur MySQL 8 / MariaDB 10 (natif o2switch, gratuit, supporté par Doctrine).

**Changements opérés** :
- `.env`, `.env.local` (MAMP 8.0.44 port 8889), `.env.test`, `.env.prod` → DSN MySQL
- `config/packages/doctrine.yaml` → retrait de `identity_generation_preferences: PostgreSQLPlatform: identity`
- 5 requêtes SQL natives patchées :
  - `TO_CHAR(DATE_TRUNC('month', X), 'YYYY-MM')` → `DATE_FORMAT(X, '%Y-%m')` (4 occurrences dans `Admin/AnalyticsController` et `ClientAnalyticsController`)
  - `EXTRACT(DAY FROM :now::timestamp - X)` → `DATEDIFF(:now, X)` (DashboardController)
  - `ORDER BY X ASC NULLS LAST` → `ORDER BY X IS NULL, X ASC` (DashboardController)
- `Entity/Product.php` : retiré `options: ['default' => '[]']` sur la colonne JSON `images` (MySQL interdit un default sur JSON/TEXT ; le défaut PHP `= []` suffit)
- Suppression des 16 migrations PG + génération d'une migration MySQL unique via `doctrine:migrations:diff`
- `DEPLOY.md` mis à jour (pdo_mysql, DSN mysql, MariaDB 10.11)

**Validé** : smoke test 10/10 OK sur MySQL local (MAMP 8.0.44). Aucun opérateur JSON Pg / cast `::type` / type `ARRAY` dans le code → pas d'autre surprise attendue.

**À faire côté prod** : noter la version MySQL/MariaDB o2switch dans `.env.prod.local` (`serverVersion=10.x-MariaDB` ou `8.0.x`).


### Accès produit par entreprise (2026-04-21)

Visibilité stricte : chaque produit est lié à 1..N `Company` via la table de jointure `product_company_access` (ManyToMany sur `Product::$allowedCompanies`). Pas de champ "visibility" — un produit sans entreprise assignée est **invisible pour tous les clients**, seul l'admin le voit dans son back-office avec un badge ambre "Non affecté".

**Côté client**, le filtre `ProductRepository::createCatalogueQueryBuilder(Company)` applique systématiquement `INNER JOIN p.allowedCompanies WHERE ac = :company` sur :
- `/catalogue` (liste + facets couleurs/tailles/catégories)
- `/catalogue/{slug}` (404 si pas d'accès, même si publié)
- `/catalogue/{slug}/add` (POST ajout au panier)
- `/favoris/toggle/{id}`, `/favoris` (filtre aussi les favoris existants qui ont perdu l'accès)
- Recherche globale
- Checkout : double-check avant persistance de la commande (accessIssues flash)

**Côté admin**, modal de gestion des accès depuis la fiche produit (`templates/admin/product/form.html.twig`) :
- Bouton "Gérer les accès" avec compteur live
- Modal centré : search insensible casse/accents, liste cochable avec statut users par entreprise (`Company::getAccessStatusLabel()` : "3 users actifs" / "En attente d'inscription" / "Aucun user actif")
- Bouton "+ Créer une nouvelle entreprise" (details inline) → endpoint JSON `POST /admin/entreprises/quick-create` → nouvelle ligne cochée auto
- Sélections synchronisées via `company_access[]` hidden inputs au moment du submit du form produit

**Liste produits admin** : 2 filtres séparés (statut draft/published, accès unassigned/assigned) + nouvelle colonne "Accès" avec badge ambre "Non affecté" ou bleu "Affecté à N".

**Smoke test + fixtures** mis à jour pour assigner `allowedCompanies` sur les produits créés, sinon rien ne serait visible côté client.
