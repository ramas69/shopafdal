# Afdal — Mémoire projet

Plateforme B2B commandes textile · Symfony 7 + PostgreSQL (o2switch)

---

## Design System

### Style
**Trust & Authority** — badges, crédibilité, WCAG AAA
- Light ✓ Full · Dark ✓ Full
- Performance : excellent

### Couleurs

| Rôle | Hex | Variable CSS |
|------|-----|--------------|
| Primary | `#0F172A` | `--color-primary` |
| On Primary | `#FFFFFF` | `--color-on-primary` |
| Secondary | `#334155` | `--color-secondary` |
| Accent / CTA | `#0369A1` | `--color-accent` |
| Background | `#F8FAFC` | `--color-background` |
| Foreground | `#020617` | `--color-foreground` |
| Muted | `#E8ECF1` | `--color-muted` |
| Border | `#E2E8F0` | `--color-border` |
| Destructive | `#DC2626` | `--color-destructive` |
| Ring | `#0F172A` | `--color-ring` |

### Typographie
- **Titres** : Lexend (300–700)
- **Corps** : Source Sans 3 (300–700)
- Mood : corporate, trustworthy, accessible, readable

```css
@import url('https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700&family=Source+Sans+3:wght@300;400;500;600;700&display=swap');
```

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
