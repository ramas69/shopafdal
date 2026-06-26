# Spec — Gestion de l'équipe Afdal (promotion/rétrogradation admin)

Date : 2026-06-09
Statut : approuvé (design), en attente plan d'implémentation

## Objectif

Permettre à un admin de promouvoir un employé Afdal (utilisateur sans entreprise, actuellement
`ROLE_CLIENT_MANAGER`) en `ROLE_ADMIN`, et de rétrograder un admin en `ROLE_CLIENT_MANAGER` — depuis une
nouvelle page « Équipe ». Un admin ne peut pas changer son propre rôle (anti-verrouillage).

## Périmètre

- Page admin « Équipe » listant les **employés Afdal** = utilisateurs dont `company` est `null`.
- Action de **bascule de rôle** (admin ↔ client_manager) sur ces utilisateurs, sauf soi-même.
- L'invitation directe en admin (`Invitation::targetRole = ADMIN`) existe déjà et reste **inchangée**.

### Hors périmètre (YAGNI)

Suppression d'utilisateur, gestion fine de permissions, promotion de clients rattachés à une entreprise,
édition d'autres champs du user.

## Architecture

### 1. Repository — `UserRepository::findAfdalStaff()`

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

(`company IS NULL` ⇒ employé Afdal. Inclut les admins existants + tout user sans company.)

### 2. Controller — `Admin\TeamController`

`src/Controller/Admin/TeamController.php` :

```php
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
    public function toggleRole(User $user, EntityManagerInterface $em): RedirectResponse
    {
        /** @var User $current */
        $current = $this->getUser();

        // Garde-fou 1 : pas son propre rôle (anti-verrouillage).
        if ($user->getId() === $current->getId()) {
            $this->addFlash('error', 'Vous ne pouvez pas modifier votre propre rôle.');
            return $this->redirectToRoute('app_admin_team');
        }

        // Garde-fou 2 : uniquement les employés Afdal (sans entreprise).
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

- Le firewall `^/admin` + `#[IsGranted('ROLE_ADMIN')]` couvrent l'accès. Pas de garde supplémentaire.
- `User::setRole(UserRole)`, `User::isAdmin()`, `User::getCompany()` existent déjà.

### 3. UI — `templates/admin/team/index.html.twig`

`{% extends 'dashboard/_shell.html.twig' %}`, blocs `title` + `content`. Structure :

- En-tête : titre « Équipe Afdal » + sous-titre (« Gérez les administrateurs et membres de l'équipe Afdal. »).
- Flash success/error (réutiliser le pattern de `admin/invitation/list.html.twig`).
- Tableau (`card overflow-hidden`) colonnes :
  - **Membre** : `member.fullName` + email en sous-ligne.
  - **Rôle** : badge — `member.isAdmin` → « Admin » (fond primary, texte blanc) ; sinon « Membre » (fond muted, texte secondary).
  - **Dernière connexion** : `member.lastLoginAt ? member.lastLoginAt|date('d/m/Y') : '—'`.
  - **Action** :
    - Si `member.id == app.user.id` → texte « Vous » (pas de bouton).
    - Sinon → form POST vers `app_admin_team_toggle_role` :
      - admin → bouton « Rétrograder » (style discret/destructive léger).
      - non-admin → bouton « Promouvoir admin » (style primary).
- État vide : « Aucun membre d'équipe. » si `members` vide.

### 4. Navigation

Dans `templates/dashboard/_shell.html.twig` :
- Ajouter au `nav_items` admin, après « Invitations » :
  `{ route: 'app_admin_team', label: 'Équipe', icon: 'users' }`.
- Ajouter un cas `{% elseif name == 'users' %}` dans la macro `nav_icon` (l'icône `users` n'existe pas encore),
  SVG « groupe de personnes » cohérent avec les autres (mêmes attributs de taille `w-[22px] h-[22px]`
  duotone, comme les icônes voisines).

### 5. Tests (functional, `tests/Functional/AdminTeamTest.php`)

- `findAfdalStaff()` ne renvoie que les utilisateurs sans entreprise (créer 1 user sans company + 1 user
  rattaché à une company, asserter que seul le premier remonte).
- `GET /admin/equipe` (admin) → 200, contient le nom d'un employé Afdal.
- Admin promeut un employé (client→admin) via POST → rôle passé à `ADMIN`, redirect.
- Re-POST sur le même → rebascule à `CLIENT_MANAGER` (bascule).
- Admin tente de basculer **son propre** rôle → rôle inchangé, redirect (flash erreur).
- Admin tente de basculer un user **avec** company → `403`, rôle inchangé.
- Client (`ROLE_CLIENT_MANAGER`) sur `/admin/equipe` → `403` (firewall `^/admin`).

Helpers : `createUser('admin')`, `createUser('client', $company, CompanyRole::OWNER)`,
`createCompanyWithAntenna()` (présents dans `TestDataTrait`). Pour un employé Afdal non-admin,
`createUser('client')` sans company convient (role CLIENT_MANAGER, company null).

## Documentation

- Mettre à jour `AFDAL_DESIGN.md` (itération « Gestion équipe Afdal »).

## Risques / points d'attention

- **Anti-verrouillage** : le garde-fou « pas soi-même » évite qu'un admin se retire le rôle. Il reste
  théoriquement possible que le **dernier** admin rétrograde un **autre** admin et finisse seul, mais il ne
  peut pas se rétrograder lui-même → il restera toujours au moins un admin tant qu'on ne supprime pas de
  compte. Acceptable (pas de gestion « dernier admin » dans ce périmètre).
- `findAfdalStaff()` liste TOUS les users sans company, y compris d'éventuels comptes de service. Acceptable :
  ce sont par définition des membres Afdal.
- La bascule lit `isAdmin()` **après** `setRole()` pour le message — vérifié : `setRole` met à jour l'état,
  donc le flash reflète le nouveau rôle (cohérent avec le pattern `toggleShipping`).
