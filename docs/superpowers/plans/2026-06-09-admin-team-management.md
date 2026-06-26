# Gestion de l'équipe Afdal — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Page admin « Équipe » listant les employés Afdal (users sans entreprise) avec bascule de rôle admin ↔ client_manager, sauf soi-même.

**Architecture:** Méthode `UserRepository::findAfdalStaff()` (company IS NULL), controller `Admin\TeamController` (liste + toggle de rôle avec garde-fous), page Twig + entrée nav. Aucune migration (réutilise `User::setRole`).

**Tech Stack:** Symfony 7.4, Doctrine ORM 3, Twig, PHPUnit (WebTestCase + dama). PHP 8.5 local, MAMP MySQL.

**État vérifié :** `User` a `setRole(UserRole)`, `isAdmin(): bool`, `getCompany(): ?Company`, `getLastLoginAt()`, `getFullName()`, `getEmail()`. `UserRepository` a `findByEmail`/`upgradePassword`. Nav admin dans `templates/dashboard/_shell.html.twig` (`nav_items` is_admin, après « Invitations »). Macro `nav_icon` : icônes duotone `w-[22px] h-[22px]`, PAS de cas `users`. `UserRole` cases : ADMIN='ROLE_ADMIN', CLIENT_MANAGER='ROLE_CLIENT_MANAGER'.

**Convention :** commits ciblés (`git add <fichiers>`).

---

## Fichiers

- Modifier : `src/Repository/UserRepository.php` (méthode `findAfdalStaff`)
- Créer : `src/Controller/Admin/TeamController.php`
- Créer : `templates/admin/team/index.html.twig`
- Modifier : `templates/dashboard/_shell.html.twig` (entrée nav + icône `users`)
- Créer : `tests/Functional/AdminTeamTest.php`
- Modifier : `AFDAL_DESIGN.md`

> Note tests : DB locale = MAMP MySQL (8889). Helpers `TestDataTrait` : `createUser(string $role, ?Company $company = null, ?CompanyRole $companyRole = null)`, `createCompanyWithAntenna()`, `em()`.

---

## Task 1 : Repository — `findAfdalStaff()` (TDD)

**Files:**
- Create: `tests/Functional/AdminTeamTest.php`
- Modify: `src/Repository/UserRepository.php`

- [ ] **Step 1 : Écrire le test (échoue)**

`tests/Functional/AdminTeamTest.php` :

```php
<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Enum\CompanyRole;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminTeamTest extends WebTestCase
{
    use TestDataTrait;

    public function testFindAfdalStaffReturnsOnlyUsersWithoutCompany(): void
    {
        self::bootKernel();
        [$company] = $this->createCompanyWithAntenna();
        $staff = $this->createUser('client');                       // sans company → employé Afdal
        $client = $this->createUser('client', $company, CompanyRole::OWNER); // rattaché → exclu

        $repo = static::getContainer()->get(UserRepository::class);
        $result = $repo->findAfdalStaff();

        $ids = array_map(fn (User $u) => $u->getId(), $result);
        self::assertContains($staff->getId(), $ids);
        self::assertNotContains($client->getId(), $ids);
    }
}
```

- [ ] **Step 2 : Lancer → échec attendu**

Run: `php bin/phpunit --filter testFindAfdalStaffReturnsOnlyUsersWithoutCompany`
Expected: FAIL — `Error: Call to undefined method App\Repository\UserRepository::findAfdalStaff()`.

- [ ] **Step 3 : Implémenter la méthode**

Dans `src/Repository/UserRepository.php`, ajouter (l'import `App\Entity\User` est déjà présent) :

```php
    /** @return User[] Employés Afdal (sans entreprise), triés par rôle puis nom */
    public function findAfdalStaff(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.company IS NULL')
            ->orderBy('u.role', 'ASC')
            ->addOrderBy('u.fullName', 'ASC')
            ->getQuery()
            ->getResult();
    }
```

- [ ] **Step 4 : Lancer → succès attendu**

Run: `php bin/phpunit --filter testFindAfdalStaffReturnsOnlyUsersWithoutCompany`
Expected: PASS.

- [ ] **Step 5 : Commit**

```bash
git add src/Repository/UserRepository.php tests/Functional/AdminTeamTest.php
git commit -m "feat: UserRepository::findAfdalStaff (employés sans entreprise)"
```

---

## Task 2 : Controller — liste + toggle de rôle (TDD)

**Files:**
- Modify: `tests/Functional/AdminTeamTest.php`
- Create: `src/Controller/Admin/TeamController.php`

- [ ] **Step 1 : Écrire les tests (échouent)**

Ajouter dans `AdminTeamTest` :

```php
    public function testAdminPromotesAndDemotesStaff(): void
    {
        $client = static::createClient();
        $admin = $this->createUser('admin');
        $staff = $this->createUser('client'); // employé Afdal, CLIENT_MANAGER, sans company

        $client->loginUser($admin);

        // Promotion : client → admin
        $client->request('POST', '/admin/equipe/' . $staff->getId() . '/role');
        self::assertResponseRedirects();
        $this->em()->clear();
        self::assertSame(UserRole::ADMIN, $this->em()->getRepository(User::class)->find($staff->getId())->getRole());

        // Rétrogradation : re-bascule → client
        $client->request('POST', '/admin/equipe/' . $staff->getId() . '/role');
        $this->em()->clear();
        self::assertSame(UserRole::CLIENT_MANAGER, $this->em()->getRepository(User::class)->find($staff->getId())->getRole());
    }

    public function testAdminCannotChangeOwnRole(): void
    {
        $client = static::createClient();
        $admin = $this->createUser('admin');

        $client->loginUser($admin);
        $client->request('POST', '/admin/equipe/' . $admin->getId() . '/role');

        self::assertResponseRedirects();
        $this->em()->clear();
        self::assertSame(UserRole::ADMIN, $this->em()->getRepository(User::class)->find($admin->getId())->getRole());
    }

    public function testCannotToggleUserWithCompany(): void
    {
        $client = static::createClient();
        [$company] = $this->createCompanyWithAntenna();
        $admin = $this->createUser('admin');
        $member = $this->createUser('client', $company, CompanyRole::OWNER);

        $client->loginUser($admin);
        $client->request('POST', '/admin/equipe/' . $member->getId() . '/role');

        self::assertResponseStatusCodeSame(403);
        $this->em()->clear();
        self::assertSame(UserRole::CLIENT_MANAGER, $this->em()->getRepository(User::class)->find($member->getId())->getRole());
    }

    public function testClientCannotAccessTeam(): void
    {
        $client = static::createClient();
        [$company] = $this->createCompanyWithAntenna();
        $user = $this->createUser('client', $company, CompanyRole::OWNER);

        $client->loginUser($user);
        $client->request('GET', '/admin/equipe');

        self::assertResponseStatusCodeSame(403);
    }
```

- [ ] **Step 2 : Lancer → échec attendu**

Run: `php bin/phpunit --filter "testAdminPromotesAndDemotesStaff|testAdminCannotChangeOwnRole|testCannotToggleUserWithCompany|testClientCannotAccessTeam"`
Expected: les tests admin/toggle FAIL (route absente → 404). `testClientCannotAccessTeam` peut déjà passer (firewall `^/admin` → 403 avant routing).

- [ ] **Step 3 : Créer le controller**

`src/Controller/Admin/TeamController.php` :

```php
<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/equipe')]
#[IsGranted('ROLE_ADMIN')]
final class TeamController extends AbstractController
{
    #[Route('', name: 'app_admin_team', methods: ['GET'])]
    public function index(UserRepository $users): Response
    {
        return $this->render('admin/team/index.html.twig', [
            'members' => $users->findAfdalStaff(),
        ]);
    }

    #[Route('/{id}/role', name: 'app_admin_team_toggle_role', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleRole(
        #[MapEntity(mapping: ['id' => 'id'])] User $user,
        EntityManagerInterface $em,
    ): RedirectResponse {
        /** @var User $current */
        $current = $this->getUser();

        if ($user->getId() === $current->getId()) {
            $this->addFlash('error', 'Vous ne pouvez pas modifier votre propre rôle.');
            return $this->redirectToRoute('app_admin_team');
        }

        if ($user->getCompany() !== null) {
            throw $this->createAccessDeniedException();
        }

        $user->setRole($user->isAdmin() ? UserRole::CLIENT_MANAGER : UserRole::ADMIN);
        $em->flush();

        $this->addFlash('success', $user->isAdmin()
            ? sprintf('%s est désormais administrateur.', $user->getFullName())
            : sprintf('%s est désormais membre (non admin).', $user->getFullName()));

        return $this->redirectToRoute('app_admin_team');
    }
}
```

- [ ] **Step 4 : Lancer → succès attendu**

Run: `php bin/phpunit --filter "testAdminPromotesAndDemotesStaff|testAdminCannotChangeOwnRole|testCannotToggleUserWithCompany|testClientCannotAccessTeam"`
Expected: PASS (4 tests).

- [ ] **Step 5 : Commit**

```bash
git add src/Controller/Admin/TeamController.php tests/Functional/AdminTeamTest.php
git commit -m "feat: TeamController — bascule de rôle des employés Afdal (garde-fous)"
```

---

## Task 3 : UI — page Équipe + navigation

**Files:**
- Create: `templates/admin/team/index.html.twig`
- Modify: `templates/dashboard/_shell.html.twig`

- [ ] **Step 1 : Créer la page**

`templates/admin/team/index.html.twig` :

```twig
{% extends 'dashboard/_shell.html.twig' %}

{% block title %}Équipe — Admin{% endblock %}

{% block content %}
<div class="mb-8">
    <h1 class="text-3xl font-semibold text-[var(--color-primary)] mb-2">Équipe Afdal</h1>
    <p class="text-[var(--color-secondary)]">Gérez les administrateurs et membres de l'équipe Afdal.</p>
</div>

{% for message in app.flashes('success') %}
    <div class="mb-4 p-3 rounded-md bg-emerald-50 border border-emerald-200 text-sm text-emerald-800" role="status">{{ message }}</div>
{% endfor %}
{% for message in app.flashes('error') %}
    <div class="mb-4 p-3 rounded-md bg-[var(--color-accent-light)] border border-[var(--color-accent)]/30 text-sm text-[var(--color-accent-focus)]" role="alert">{{ message }}</div>
{% endfor %}

{% if members is empty %}
    <div class="card p-10 text-center">
        <p class="text-sm text-[var(--color-secondary)]">Aucun membre d'équipe.</p>
    </div>
{% else %}
    <div class="card overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-[var(--color-border)] text-left text-xs uppercase tracking-wide text-[var(--color-secondary)]">
                    <th class="px-4 py-3 font-medium">Membre</th>
                    <th class="px-4 py-3 font-medium">Rôle</th>
                    <th class="px-4 py-3 font-medium">Dernière connexion</th>
                    <th class="px-4 py-3 font-medium text-right">Action</th>
                </tr>
            </thead>
            <tbody>
                {% for member in members %}
                    <tr class="border-b border-[var(--color-border)] last:border-0 hover:bg-[var(--color-muted)]/40">
                        <td class="px-4 py-3">
                            <div class="font-medium text-[var(--color-foreground)]">{{ member.fullName }}</div>
                            <div class="text-xs text-[var(--color-secondary)]">{{ member.email }}</div>
                        </td>
                        <td class="px-4 py-3">
                            {% if member.isAdmin %}
                                <span class="inline-flex items-center rounded-full bg-[var(--color-primary)] px-2 py-0.5 text-[10px] font-semibold text-white">Admin</span>
                            {% else %}
                                <span class="inline-flex items-center rounded-full bg-[var(--color-muted)] px-2 py-0.5 text-[10px] font-semibold text-[var(--color-secondary)]">Membre</span>
                            {% endif %}
                        </td>
                        <td class="px-4 py-3 text-[var(--color-secondary)]">{{ member.lastLoginAt ? member.lastLoginAt|date('d/m/Y') : '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            {% if member.id == app.user.id %}
                                <span class="text-xs text-[var(--color-secondary)]">Vous</span>
                            {% else %}
                                <form method="post" action="{{ path('app_admin_team_toggle_role', {id: member.id}) }}">
                                    {% if member.isAdmin %}
                                        <button type="submit" class="text-xs font-medium text-[var(--color-destructive)] hover:underline">Rétrograder</button>
                                    {% else %}
                                        <button type="submit" class="text-xs font-medium text-[var(--color-primary)] hover:underline">Promouvoir admin</button>
                                    {% endif %}
                                </form>
                            {% endif %}
                        </td>
                    </tr>
                {% endfor %}
            </tbody>
        </table>
    </div>
{% endif %}
{% endblock %}
```

- [ ] **Step 2 : Ajouter l'entrée nav**

Dans `templates/dashboard/_shell.html.twig`, dans le `nav_items` admin (branche `is_admin ? [`), après la
ligne `{ route: 'app_admin_invitations', label: 'Invitations', icon: 'mail' },` ajouter :

```twig
    { route: 'app_admin_team', label: 'Équipe', icon: 'users' },
```

- [ ] **Step 3 : Ajouter l'icône `users` dans la macro `nav_icon`**

Dans la macro `{% macro nav_icon(name) %}`, ajouter une branche (avant le `{% endif %}` final, après le cas
`'file'`), cohérente avec les icônes voisines (duotone `w-[22px] h-[22px]`) :

```twig
    {% elseif name == 'users' %}
        {# Équipe — groupe de personnes duotone #}
        <svg class="w-[22px] h-[22px]" viewBox="0 0 24 24" fill="none">
            <path fill="currentColor" fill-opacity=".3" d="M17 20c0-2.761-2.239-5-5-5s-5 2.239-5 5h10Z"/>
            <circle cx="12" cy="8" r="3.25" fill="currentColor"/>
            <circle cx="5" cy="9.5" r="2.25" fill="currentColor" fill-opacity=".6"/>
            <circle cx="19" cy="9.5" r="2.25" fill="currentColor" fill-opacity=".6"/>
            <path fill="currentColor" fill-opacity=".3" d="M5 13c-2 0-3.5 1.5-3.5 3.5h4.2A6.97 6.97 0 0 1 7 13.4 4.5 4.5 0 0 0 5 13Zm14 0c-.74 0-1.43.18-2 .4.8.9 1.3 2 1.3 3.1h4.2C22.5 14.5 21 13 19 13Z"/>
        </svg>
```

- [ ] **Step 4 : Vérifier**

Run: `php bin/console lint:twig templates/admin/team/index.html.twig templates/dashboard/_shell.html.twig`
Expected: OK.
Run: `php bin/phpunit --filter "testAdminPromotesAndDemotesStaff|testClientCannotAccessTeam"`
Expected: PASS (la page rend ; le toggle marche).

- [ ] **Step 5 : Commit**

```bash
git add templates/admin/team/index.html.twig templates/dashboard/_shell.html.twig
git commit -m "feat: page Équipe admin + entrée nav (promotion/rétrogradation)"
```

---

## Task 4 : Documentation

**Files:**
- Modify: `AFDAL_DESIGN.md`

- [ ] **Step 1 : Ajouter une itération**

Dans `AFDAL_DESIGN.md`, section « Itérations post-livraison », après le dernier bloc, ajouter :

```markdown
**Gestion équipe Afdal** (2026-06-09) :
- Page `/admin/equipe` (`app_admin_team`, `TeamController`) listant les employés Afdal (users sans entreprise, via `UserRepository::findAfdalStaff` = `company IS NULL`).
- Bascule de rôle admin ↔ client_manager : `POST /admin/equipe/{id}/role`. Garde-fous : un admin ne peut pas changer son propre rôle (anti-verrouillage), et seuls les users sans entreprise sont basculables (un user avec company → 403).
- Entrée nav « Équipe » (icône `users`). L'invitation directe en admin (`Invitation::targetRole=ADMIN`) reste inchangée ; cette feature ajoute la promotion après inscription.
```

- [ ] **Step 2 : Commit**

```bash
git add AFDAL_DESIGN.md
git commit -m "docs: itération gestion équipe Afdal"
```

---

## Vérification finale

- [ ] **Tests feature verts**

Run: `php bin/phpunit --filter AdminTeamTest`
Expected: OK (tous : findAfdalStaff, promote/demote, own-role, with-company 403, client 403).

- [ ] **Suite complète (pas de régression)**

Run: `php bin/phpunit`
Expected: les tests de la feature passent ; aucun nouvel échec introduit. (La DB locale MAMP MySQL doit être en sync — pas de migration ici, donc rien à resync.)

- [ ] **Lint Twig**

Run: `php bin/console lint:twig templates/admin/team templates/dashboard/_shell.html.twig`
Expected: OK.

- [ ] **Rappel prod** : pas de migration (réutilise `User::setRole`). Déploiement = `git pull` + `php bin/console asset-map:compile --env=prod` (nouvelle icône SVG dans le shell → recompiler) + `cache:clear --env=prod`. Sur o2switch, penser à `export TMPDIR=$HOME/tmp` avant `tailwind:build`.
