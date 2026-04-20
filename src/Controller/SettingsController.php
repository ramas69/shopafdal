<?php

namespace App\Controller;

use App\Entity\Invitation;
use App\Entity\User;
use App\Enum\CompanyRole;
use App\Repository\InvitationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/parametres')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class SettingsController extends AbstractController
{
    #[Route('', name: 'app_settings')]
    public function index(UserRepository $users, InvitationRepository $invitations): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $company = $user->getCompany();

        $members = [];
        $pendingInvites = [];
        if ($company) {
            $members = $users->findBy(['company' => $company], ['fullName' => 'ASC']);
            $pendingInvites = array_values(array_filter(
                $invitations->findBy(['company' => $company], ['createdAt' => 'DESC']),
                fn(Invitation $i) => $i->isPending(),
            ));
        }

        return $this->render('settings/index.html.twig', [
            'members' => $members,
            'pending_invites' => $pendingInvites,
            'is_owner' => $user->isCompanyOwner(),
        ]);
    }

    #[Route('/equipe/inviter', name: 'app_settings_team_invite', methods: ['POST'])]
    public function inviteMember(
        Request $request,
        UserRepository $users,
        InvitationRepository $invitations,
        EntityManagerInterface $em,
    ): RedirectResponse {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user->isCompanyOwner() || !$user->getCompany()) {
            throw $this->createAccessDeniedException();
        }

        $email = strtolower(trim((string) $request->request->get('email', '')));
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', 'Email invalide.');
            return $this->redirectToRoute('app_settings');
        }
        if ($users->findByEmail($email)) {
            $this->addFlash('error', 'Un compte avec cet email existe déjà.');
            return $this->redirectToRoute('app_settings');
        }

        $existing = $invitations->findOneBy(['email' => $email, 'company' => $user->getCompany()]);
        if ($existing && $existing->isPending()) {
            $this->addFlash('info', 'Invitation déjà en cours pour cet email.');
            return $this->redirectToRoute('app_settings');
        }

        $invite = (new Invitation())
            ->setEmail($email)
            ->setCompany($user->getCompany())
            ->setCompanyRole(CompanyRole::MEMBER);
        $em->persist($invite);
        $em->flush();

        $this->addFlash('success', sprintf('Invitation créée pour %s. Partagez le lien.', $email));
        return $this->redirectToRoute('app_settings');
    }

    #[Route('/equipe/invitation/{id}/revoquer', name: 'app_settings_team_revoke', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function revokeInvitation(
        Invitation $invitation,
        EntityManagerInterface $em,
    ): RedirectResponse {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user->isCompanyOwner() || $invitation->getCompany()->getId() !== $user->getCompany()?->getId()) {
            throw $this->createAccessDeniedException();
        }
        $invitation->setRevokedAt(new \DateTimeImmutable());
        $em->flush();
        $this->addFlash('success', 'Invitation révoquée.');
        return $this->redirectToRoute('app_settings');
    }

    #[Route('/equipe/membre/{id}', name: 'app_settings_team_remove', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function removeMember(
        User $member,
        EntityManagerInterface $em,
    ): RedirectResponse {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user->isCompanyOwner()) {
            throw $this->createAccessDeniedException();
        }
        if ($member->getCompany()?->getId() !== $user->getCompany()?->getId()) {
            throw $this->createAccessDeniedException();
        }
        if ($member->getId() === $user->getId()) {
            $this->addFlash('error', 'Vous ne pouvez pas vous retirer vous-même.');
            return $this->redirectToRoute('app_settings');
        }
        $member->setActive(false);
        $em->flush();
        $this->addFlash('success', sprintf('%s retiré de l\'équipe.', $member->getFullName()));
        return $this->redirectToRoute('app_settings');
    }

    #[Route('/profil', name: 'app_settings_profile', methods: ['POST'])]
    public function updateProfile(Request $request, EntityManagerInterface $em): RedirectResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $fullName = trim((string) $request->request->get('full_name', ''));

        if ($fullName === '') {
            $this->addFlash('error', 'Nom complet requis.');
        } else {
            $user->setFullName($fullName);
            $em->flush();
            $this->addFlash('success', 'Profil mis à jour.');
        }
        return $this->redirectToRoute('app_settings');
    }

    #[Route('/mot-de-passe', name: 'app_settings_password', methods: ['POST'])]
    public function updatePassword(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
    ): RedirectResponse {
        /** @var User $user */
        $user = $this->getUser();

        $current = (string) $request->request->get('current_password', '');
        $new = (string) $request->request->get('new_password', '');
        $confirm = (string) $request->request->get('confirm_password', '');

        if (!$hasher->isPasswordValid($user, $current)) {
            $this->addFlash('error', 'Mot de passe actuel incorrect.');
            return $this->redirectToRoute('app_settings');
        }
        if (strlen($new) < 8) {
            $this->addFlash('error', 'Le nouveau mot de passe doit contenir au moins 8 caractères.');
            return $this->redirectToRoute('app_settings');
        }
        if ($new !== $confirm) {
            $this->addFlash('error', 'La confirmation ne correspond pas.');
            return $this->redirectToRoute('app_settings');
        }

        $user->setPassword($hasher->hashPassword($user, $new));
        $em->flush();
        $this->addFlash('success', 'Mot de passe mis à jour.');
        return $this->redirectToRoute('app_settings');
    }
}
