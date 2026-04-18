<?php

namespace App\Controller;

use App\Entity\User;
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
    public function index(): Response
    {
        return $this->render('settings/index.html.twig');
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
