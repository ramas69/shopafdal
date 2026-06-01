# Spec — Frais de port gratuits par entreprise

Date : 2026-06-01
Statut : approuvé (design), en attente plan d'implémentation

## Objectif

Permettre à l'admin d'activer/désactiver, entreprise par entreprise, une option « frais de port gratuits ».
Sur les commandes d'une entreprise, afficher « Frais de port gratuits » si l'option est active, sinon
« Frais de port non compris ». La valeur est lue **en direct** depuis l'entreprise (pas de snapshot par commande).

## Périmètre

- **Toggle admin** : interrupteur dans la fiche entreprise admin (`/admin/entreprises/{id}`), action directe
  (POST immédiat au changement, comme l'archivage).
- **Affichage** : texte sur la page **détail de la commande**, côté client ET admin.
- **Valeur live** : la commande lit `order.company.isFreeShipping()` au rendu. Changer le toggle affecte
  toutes les commandes de l'entreprise (anciennes incluses).

### Hors périmètre (YAGNI)

Calcul d'un montant de port, seuil de gratuité, snapshot par commande, affichage sur checkout ou emails.

## Architecture

### 1. Entité `Company` — flag `freeShipping`

Ajouter la propriété (près de `archivedAt`) :

```php
    #[ORM\Column(options: ['default' => false])]
    private bool $freeShipping = false;
```

Accesseurs (style du projet, près des autres getters/setters) :

```php
    public function isFreeShipping(): bool { return $this->freeShipping; }
    public function setFreeShipping(bool $v): self { $this->freeShipping = $v; return $this; }
```

Migration Doctrine dédiée (MySQL/MariaDB) : `ALTER TABLE companies ADD free_shipping TINYINT(1) DEFAULT 0 NOT NULL`
(le générateur produira la forme exacte). Vérifier que la migration ne contient QUE cet ALTER sur `companies`
(retirer tout statement parasite éventuel lié au quirk de schéma).

### 2. Toggle admin — `CompanyController`

Nouvelle action, calquée sur `archive()`/`unarchive()` :

```php
    #[Route('/{id}/frais-port', name: 'app_admin_company_shipping_toggle', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleShipping(Company $company, EntityManagerInterface $em): RedirectResponse
    {
        $company->setFreeShipping(!$company->isFreeShipping());
        $em->flush();
        $this->addFlash('success', $company->isFreeShipping()
            ? sprintf('Frais de port gratuits activés pour « %s ».', $company->getName())
            : sprintf('Frais de port désactivés pour « %s ».', $company->getName()));
        return $this->redirectToRoute('app_admin_company_detail', ['id' => $company->getId()]);
    }
```

Le controller est déjà `#[Route('/admin/entreprises')]` + (vérifier) protégé `ROLE_ADMIN` au niveau classe
ou via le firewall `^/admin`. Aucune garde supplémentaire nécessaire (le firewall `^/admin` impose `ROLE_ADMIN`).

### 3. UI toggle — fiche entreprise admin

Dans `templates/admin/company/detail.html.twig`, ajouter une section `card p-6` (ou une ligne dans une section
existante de réglages) avec un interrupteur qui POST au changement via le controller Stimulus `autosubmit`
(déjà utilisé ailleurs : `data-controller="autosubmit"` sur le `<form>`, `data-action="change->autosubmit#submitNow"`
sur l'input) :

```twig
<section class="card p-6">
    <h2 class="text-sm font-semibold text-[var(--color-primary)] mb-3">Livraison</h2>
    <form method="post" action="{{ path('app_admin_company_shipping_toggle', {id: company.id}) }}"
          data-controller="autosubmit">
        <label class="flex items-center gap-3 cursor-pointer">
            <input type="checkbox" name="free_shipping"
                   {{ company.freeShipping ? 'checked' }}
                   data-action="change->autosubmit#submitNow"
                   class="h-4 w-4 rounded border-[var(--color-border)] text-[var(--color-primary)]">
            <span class="text-sm text-[var(--color-foreground)]">Frais de port gratuits pour cette entreprise</span>
        </label>
    </form>
    <p class="mt-2 text-xs text-[var(--color-secondary)]">
        Affiché sur les commandes : « Frais de port gratuits » si activé, « Frais de port non compris » sinon.
    </p>
</section>
```

> Note : le controller `toggleShipping` **inverse** le flag à chaque POST (il ne lit pas la valeur de la
> checkbox). C'est cohérent avec le pattern archive (action = bascule). La case `checked` reflète l'état
> courant ; au `change`, le form se soumet et le serveur bascule. Pas de lecture de `free_shipping` côté
> serveur — le nom du champ est cosmétique. (Si on préférait lire la checkbox, il faudrait gérer le cas
> décoché qui n'envoie pas le champ ; la bascule évite ce piège.)

### 4. Affichage commande — partial partagé

Créer `templates/order/_shipping_note.html.twig` (reçoit `order`) :

```twig
{# @param order #}
{% if order.company.freeShipping %}
    <div class="flex items-center gap-2 text-sm">
        <span class="inline-flex items-center rounded-full bg-[var(--color-success)] px-2 py-0.5 text-[10px] font-semibold text-white">Port offert</span>
        <span class="font-medium text-[var(--color-success)]">Frais de port gratuits</span>
    </div>
{% else %}
    <div class="text-sm text-[var(--color-secondary)]">Frais de port non compris</div>
{% endif %}
```

Inclusion :
- `templates/order/detail.html.twig` (client) : sous le bloc Total HT (vers la ligne 240-242),
  `{{ include('order/_shipping_note.html.twig', {order: order}, with_context = false) }}`.
- `templates/admin/order/detail.html.twig` (admin) : à l'emplacement équivalent (zone total/livraison).
  Même include.

### 5. Tests (functional, nouveau `tests/Functional/CompanyShippingTest.php` ou ajout à un test admin existant)

- `isFreeShipping()` est `false` par défaut sur une `Company` neuve.
- `POST /admin/entreprises/{id}/frais-port` en tant qu'admin → bascule le flag (false→true), redirige ;
  un second POST rebascule (true→false).
- Un client (`ROLE_CLIENT_MANAGER`) sur cette route → `403` (firewall `^/admin`).
- Page détail commande (client) : contient « Frais de port gratuits » quand le flag est on ;
  « Frais de port non compris » quand off.

Helpers : `createCompanyWithAntenna`, `createUser`, `createOrder` (présents dans `TestDataTrait` /
`OrderDocumentTest` — répliquer le helper `createOrder` si le nouveau fichier de test en a besoin, ou
ajouter les tests à un fichier qui l'a déjà).

## Documentation

- Mettre à jour `AFDAL_DESIGN.md` (itération « Frais de port par entreprise »).

## Risques / points d'attention

- **Migration DB** : déploiement prod = `git pull` + `doctrine:migrations:migrate` (pas seulement cache:clear).
  La DB de test locale (PostgreSQL) devra être resynchronisée (`doctrine:schema:update --force --env=test`)
  avant de lancer les tests, comme pour la feature archivage.
- Valeur live : si un accord commercial change, toutes les commandes passées reflètent le nouvel état —
  comportement voulu (l'option = accord permanent, pas une condition figée par commande).
- Le texte est purement indicatif : aucun impact sur `totalCents` ni sur le calcul de prix.
