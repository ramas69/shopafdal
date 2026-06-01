# Frais de port gratuits par entreprise — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Flag `freeShipping` par entreprise, basculable par l'admin (toggle direct dans la fiche entreprise), affiché en direct sur la page détail de la commande (client + admin) : « Frais de port gratuits » ou « Frais de port non compris ».

**Architecture:** Un champ booléen sur `Company` (lu en direct, pas de snapshot), une action POST de bascule dans `Admin\CompanyController` (calquée sur `archive()`), un partial Twig partagé inclus dans les deux pages détail commande.

**Tech Stack:** Symfony 7.4, Doctrine ORM 3, MySQL/MariaDB, Twig, Stimulus (`autosubmit`), PHPUnit (WebTestCase + dama).

**État vérifié :** `Company` a `archivedAt` (pattern de flag) ; `Admin\CompanyController` (`#[Route('/admin/entreprises')]` + `#[IsGranted('ROLE_ADMIN')]`) a `archive()`/`unarchive()`. Total HT : `templates/order/detail.html.twig` l.239-242 (client), `templates/admin/order/detail.html.twig` l.205-206 (admin). DB de test = PostgreSQL (quirk connu) → resync schéma avant tests.

**Convention :** commits ciblés (`git add <fichiers>`), ne jamais stager de fichier non lié (le working tree contient du drift « archivage entreprise » non lié à cette feature).

---

## Fichiers

- Modifier : `src/Entity/Company.php` (champ + accesseurs)
- Créer : `migrations/Version<timestamp>.php` (généré)
- Modifier : `src/Controller/Admin/CompanyController.php` (action toggle)
- Modifier : `templates/admin/company/detail.html.twig` (interrupteur)
- Créer : `templates/order/_shipping_note.html.twig` (partial affichage)
- Modifier : `templates/order/detail.html.twig` + `templates/admin/order/detail.html.twig` (include)
- Créer : `tests/Functional/CompanyShippingTest.php`
- Modifier : `AFDAL_DESIGN.md`

---

## Task 1 : Entité `Company` — flag `freeShipping` (TDD)

**Files:**
- Create: `tests/Functional/CompanyShippingTest.php`
- Modify: `src/Entity/Company.php`

- [ ] **Step 1 : Écrire le test (échoue)**

`tests/Functional/CompanyShippingTest.php` :

```php
<?php

namespace App\Tests\Functional;

use App\Entity\Company;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CompanyShippingTest extends WebTestCase
{
    use TestDataTrait;

    public function testFreeShippingDefaultsToFalse(): void
    {
        self::bootKernel();
        $company = (new Company())->setName('Acme')->setSlug('acme-test');
        self::assertFalse($company->isFreeShipping());

        $company->setFreeShipping(true);
        self::assertTrue($company->isFreeShipping());
    }
}
```

- [ ] **Step 2 : Lancer → échec attendu**

Run: `php bin/phpunit --filter testFreeShippingDefaultsToFalse`
Expected: FAIL — `Error: Call to undefined method App\Entity\Company::isFreeShipping()`.

- [ ] **Step 3 : Ajouter le champ + accesseurs**

Dans `src/Entity/Company.php`, après la propriété `$archivedAt` (vers ligne 33) :

```php
    #[ORM\Column(options: ['default' => false])]
    private bool $freeShipping = false;
```

Et près des autres accesseurs (après `setSiret(...)`, vers ligne 61) :

```php
    public function isFreeShipping(): bool { return $this->freeShipping; }
    public function setFreeShipping(bool $v): self { $this->freeShipping = $v; return $this; }
```

- [ ] **Step 4 : Lancer → succès attendu**

Run: `php bin/phpunit --filter testFreeShippingDefaultsToFalse`
Expected: PASS.

- [ ] **Step 5 : Commit**

```bash
git add src/Entity/Company.php tests/Functional/CompanyShippingTest.php
git commit -m "feat: flag Company::freeShipping (défaut false)"
```

---

## Task 2 : Migration

**Files:**
- Create: `migrations/Version<timestamp>.php` (généré)

- [ ] **Step 1 : Générer la migration**

Run: `php bin/console make:migration`
Expected: un fichier `migrations/Version<timestamp>.php` contenant `ALTER TABLE companies ADD free_shipping TINYINT(1) DEFAULT 0 NOT NULL` (ou équivalent MariaDB).

- [ ] **Step 2 : Relire la migration**

Ouvrir le fichier généré. Vérifier que `up()` ne contient QUE l'`ALTER TABLE companies ADD free_shipping ...`.
**Retirer tout statement parasite** (ALTER sur d'autres tables, dû au quirk de schéma test/dev). `down()` :
`ALTER TABLE companies DROP free_shipping`.

- [ ] **Step 3 : Appliquer sur le dev**

Run: `php bin/console doctrine:migrations:migrate --no-interaction`
Expected: `[OK]` migration exécutée.

- [ ] **Step 4 : Resync le schéma de la DB de test**

Run: `php bin/console doctrine:schema:update --force --env=test`
Expected: `[OK] Database schema updated successfully!` (ajoute `free_shipping` à la DB de test PostgreSQL).

- [ ] **Step 5 : Vérifier la colonne (dev MySQL)**

Run: `php bin/console dbal:run-sql "SHOW COLUMNS FROM companies LIKE 'free_shipping'"`
Expected: une ligne `free_shipping`.

- [ ] **Step 6 : Commit**

```bash
git add migrations/
git commit -m "feat: migration companies.free_shipping"
```

---

## Task 3 : Toggle admin — action controller (TDD)

**Files:**
- Modify: `tests/Functional/CompanyShippingTest.php`
- Modify: `src/Controller/Admin/CompanyController.php`

- [ ] **Step 1 : Écrire les tests (échouent)**

Ajouter dans `CompanyShippingTest`. Il faut un helper de création de commande/utilisateur : `TestDataTrait`
fournit `createCompanyWithAntenna()`, `createUser()`. Ajouter les tests :

```php
    public function testAdminTogglesFreeShipping(): void
    {
        $client = static::createClient();
        [$company] = $this->createCompanyWithAntenna();
        $admin = $this->createUser('admin');

        $client->loginUser($admin);

        $client->request('POST', '/admin/entreprises/' . $company->getId() . '/frais-port');
        self::assertResponseRedirects();
        $this->em()->clear();
        self::assertTrue($this->em()->getRepository(Company::class)->find($company->getId())->isFreeShipping());

        $client->request('POST', '/admin/entreprises/' . $company->getId() . '/frais-port');
        $this->em()->clear();
        self::assertFalse($this->em()->getRepository(Company::class)->find($company->getId())->isFreeShipping());
    }

    public function testClientCannotToggleFreeShipping(): void
    {
        $client = static::createClient();
        [$company] = $this->createCompanyWithAntenna();
        $user = $this->createUser('client', $company, \App\Enum\CompanyRole::OWNER);

        $client->loginUser($user);
        $client->request('POST', '/admin/entreprises/' . $company->getId() . '/frais-port');

        self::assertResponseStatusCodeSame(403);
    }
```

(Ajouter l'import `use App\Enum\CompanyRole;` en tête si tu préfères `CompanyRole::OWNER` non-qualifié.)

- [ ] **Step 2 : Lancer → échec attendu**

Run: `php bin/phpunit --filter "testAdminTogglesFreeShipping|testClientCannotToggleFreeShipping"`
Expected: `testAdminTogglesFreeShipping` FAIL (route absente → 404). `testClientCannotToggleFreeShipping` peut déjà passer (firewall `^/admin` renvoie 403 avant routing).

- [ ] **Step 3 : Ajouter l'action**

Dans `src/Controller/Admin/CompanyController.php`, après `unarchive()` (vers ligne 182) :

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

(`Company`, `EntityManagerInterface`, `RedirectResponse`, `Route` sont déjà importés — `archive()` les utilise.)

- [ ] **Step 4 : Lancer → succès attendu**

Run: `php bin/phpunit --filter "testAdminTogglesFreeShipping|testClientCannotToggleFreeShipping"`
Expected: PASS (2 tests).

- [ ] **Step 5 : Commit**

```bash
git add src/Controller/Admin/CompanyController.php tests/Functional/CompanyShippingTest.php
git commit -m "feat: bascule frais de port gratuits (admin, action directe)"
```

---

## Task 4 : UI toggle — fiche entreprise admin

**Files:**
- Modify: `templates/admin/company/detail.html.twig`

- [ ] **Step 1 : Ajouter la section interrupteur**

Dans `templates/admin/company/detail.html.twig`, insérer une nouvelle `<section class="card p-6">` dans le flux
des sections (par ex. après la section « Tarifs négociés », avant la zone « danger »/archivage). Contenu :

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

- [ ] **Step 2 : Vérifier**

Run: `php bin/console lint:twig templates/admin/company/detail.html.twig`
Expected: OK.
Run: `php bin/phpunit --filter testAdminTogglesFreeShipping`
Expected: PASS (la route fonctionne ; la page admin rend l'interrupteur).

> Note : `toggleShipping` **inverse** le flag à chaque POST (il ne lit pas la valeur de la checkbox). Le `name="free_shipping"` est cosmétique ; au `change`, le form se soumet et le serveur bascule. Comportement voulu (pattern archive).

- [ ] **Step 3 : Commit**

```bash
git add templates/admin/company/detail.html.twig
git commit -m "feat: interrupteur frais de port dans la fiche entreprise admin"
```

---

## Task 5 : Affichage commande — partial partagé

**Files:**
- Create: `templates/order/_shipping_note.html.twig`
- Modify: `templates/order/detail.html.twig`
- Modify: `templates/admin/order/detail.html.twig`

- [ ] **Step 1 : Créer le partial**

`templates/order/_shipping_note.html.twig` :

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

- [ ] **Step 2 : Inclure côté client**

Dans `templates/order/detail.html.twig`, juste après la `<div>` du Total HT qui se ferme à la ligne ~242
(le bloc `<div class="flex justify-between items-center p-4 bg-[var(--color-muted)] ...">...Total HT...</div>`),
avant la fermeture `</section>` (ligne ~243), insérer :

```twig
            <div class="px-4 pb-4">
                {{ include('order/_shipping_note.html.twig', {order: order}, with_context = false) }}
            </div>
```

- [ ] **Step 3 : Inclure côté admin**

Dans `templates/admin/order/detail.html.twig`, repérer le bloc Total HT (lignes ~205-206) et, juste après la
`<div>` qui le contient (avant sa `</section>`), insérer le même include :

```twig
            <div class="px-4 pb-4">
                {{ include('order/_shipping_note.html.twig', {order: order}, with_context = false) }}
            </div>
```

(Adapter l'indentation au contexte ; l'important est que l'include soit dans la section du total, visible.)

- [ ] **Step 4 : Vérifier**

Run: `php bin/console lint:twig templates/order/_shipping_note.html.twig templates/order/detail.html.twig templates/admin/order/detail.html.twig`
Expected: OK.

- [ ] **Step 5 : Commit**

```bash
git add templates/order/_shipping_note.html.twig templates/order/detail.html.twig templates/admin/order/detail.html.twig
git commit -m "feat: note frais de port sur la page détail commande (client + admin)"
```

---

## Task 6 : Test d'affichage commande (TDD léger)

**Files:**
- Modify: `tests/Functional/CompanyShippingTest.php`

- [ ] **Step 1 : Ajouter le helper de commande + le test**

Dans `CompanyShippingTest`, ajouter un helper `createOrder` (réplique de celui d'`OrderDocumentTest`) et le test :

```php
    private function createOrder(\App\Entity\Company $company, \App\Entity\Antenna $antenna, \App\Entity\User $createdBy): \App\Entity\Order
    {
        $order = (new \App\Entity\Order())
            ->setReference('CMD-2026-' . random_int(100000, 999999))
            ->setCompany($company)
            ->setAntenna($antenna)
            ->setCreatedBy($createdBy)
            ->setStatus(\App\Enum\OrderStatus::PLACED);
        $this->em()->persist($order);
        $this->em()->flush();
        return $order;
    }

    public function testOrderDetailShowsShippingNote(): void
    {
        $client = static::createClient();
        [$company, $antenna] = $this->createCompanyWithAntenna();
        $owner = $this->createUser('client', $company, \App\Enum\CompanyRole::OWNER);
        $order = $this->createOrder($company, $antenna, $owner);

        $client->loginUser($owner);

        // Off → "non compris"
        $client->request('GET', '/commandes/' . $order->getReference());
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Frais de port non compris', $client->getResponse()->getContent());

        // On → "gratuits"
        $company->setFreeShipping(true);
        $this->em()->flush();
        $client->request('GET', '/commandes/' . $order->getReference());
        self::assertStringContainsString('Frais de port gratuits', $client->getResponse()->getContent());
    }
```

- [ ] **Step 2 : Lancer → succès attendu**

Run: `php bin/phpunit --filter testOrderDetailShowsShippingNote`
Expected: PASS (le partial rend le bon texte selon le flag).

> Si le test échoue parce que la route de détail client diffère (vérifier `app_order_detail` = `/commandes/{reference}`),
> ajuster l'URL. Le pattern de référence est `OrderDocumentTest` qui POST sur `/commandes/{ref}/documents`.

- [ ] **Step 3 : Commit**

```bash
git add tests/Functional/CompanyShippingTest.php
git commit -m "test: note frais de port rendue sur le détail commande"
```

---

## Task 7 : Documentation

**Files:**
- Modify: `AFDAL_DESIGN.md`

- [ ] **Step 1 : Ajouter une itération**

Dans `AFDAL_DESIGN.md`, section « Itérations post-livraison », après le bloc « Dépôt de documents par l'admin », ajouter :

```markdown
**Frais de port par entreprise** (2026-06-01) :
- Flag `Company::freeShipping` (bool, défaut false), basculé par l'admin via un interrupteur dans la fiche entreprise (`POST /admin/entreprises/{id}/frais-port`, action directe `autosubmit`, bascule comme l'archivage).
- Affichage live sur le détail commande (client + admin) via le partial `order/_shipping_note.html.twig` : « Frais de port gratuits » (vert) si activé, « Frais de port non compris » (gris) sinon. Lit `order.company.freeShipping` au rendu — pas de snapshot, changer le flag affecte toutes les commandes de l'entreprise.
- Purement indicatif : aucun impact sur `totalCents`.
```

- [ ] **Step 2 : Commit**

```bash
git add AFDAL_DESIGN.md
git commit -m "docs: itération frais de port par entreprise"
```

---

## Vérification finale

- [ ] **Tests feature verts**

Run: `php bin/phpunit --filter CompanyShippingTest`
Expected: OK (tous les tests de la feature).

- [ ] **Suite complète (pas de régression vs baseline)**

Run: `php bin/phpunit`
Expected: 6 échecs pré-existants liés au test DB PostgreSQL (DashboardController DATEDIFF + redirects), aucun nouvel échec introduit par cette feature.

- [ ] **Lint Twig**

Run: `php bin/console lint:twig templates/order templates/admin/company templates/admin/order`
Expected: OK.

- [ ] **Rappel prod** : `git pull` + `php bin/console doctrine:migrations:migrate --no-interaction --env=prod` (migration `free_shipping`) + `php bin/console cache:clear --env=prod`. Pas d'asset nouveau (le controller Stimulus `autosubmit` existe déjà).
