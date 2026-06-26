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
