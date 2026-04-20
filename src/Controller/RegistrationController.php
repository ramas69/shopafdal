<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\InvitationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class RegistrationController extends AbstractController
{
    #[Route('/register/{token}', name: 'app_register')]
    public function register(
        string $token,
        Request $request,
        InvitationRepository $invitations,
        UserRepository $users,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
        Security $security,
    ): Response {
        $invitation = $invitations->findValidByToken($token);
        if (!$invitation) {
            $response = $this->render('security/invitation_invalid.html.twig');
            $response->setStatusCode(Response::HTTP_GONE);
            return $response;
        }

        $errors = [];
        $fullName = (string) $request->request->get('full_name', '');
        $password = (string) $request->request->get('password', '');
        $passwordConfirm = (string) $request->request->get('password_confirm', '');

        if ($request->isMethod('POST')) {
            if (trim($fullName) === '') {
                $errors['full_name'] = 'Nom complet requis.';
            }
            if (strlen($password) < 8) {
                $errors['password'] = 'Le mot de passe doit contenir au moins 8 caractères.';
            }
            if ($password !== $passwordConfirm) {
                $errors['password_confirm'] = 'Les mots de passe ne correspondent pas.';
            }
            if ($users->findByEmail($invitation->getEmail())) {
                $errors['_global'] = 'Un compte avec cet email existe déjà. Connectez-vous.';
            }

            if (empty($errors)) {
                $user = (new User())
                    ->setEmail($invitation->getEmail())
                    ->setFullName(trim($fullName))
                    ->setRole($invitation->getTargetRole());

                if ($invitation->isAdminInvitation()) {
                    $user->setCompany(null)->setCompanyRole(null);
                } else {
                    $user->setCompany($invitation->getCompany())
                        ->setCompanyRole($invitation->getCompanyRole());
                }

                $user->setPassword($hasher->hashPassword($user, $password));
                $em->persist($user);

                $invitation->setAcceptedAt(new \DateTimeImmutable());
                $em->flush();

                $security->login($user);

                return $this->redirectToRoute('app_dashboard');
            }
        }

        return $this->render('security/register.html.twig', [
            'invitation' => $invitation,
            'errors' => $errors,
            'full_name' => $fullName,
        ]);
    }
}
