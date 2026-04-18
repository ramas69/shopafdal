<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/notifications')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class NotificationController extends AbstractController
{
    #[Route('/{id}/read', name: 'app_notification_read', methods: ['GET'])]
    public function read(Notification $notification, EntityManagerInterface $em): RedirectResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($notification->getRecipient()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }
        if (!$notification->isRead()) {
            $notification->setReadAt(new \DateTimeImmutable());
            $em->flush();
        }
        return $this->redirect($notification->getLinkUrl() ?? $this->generateUrl('app_dashboard'));
    }

    #[Route('/read-all', name: 'app_notifications_read_all', methods: ['POST'])]
    public function readAll(NotificationRepository $notifications): RedirectResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $notifications->markAllReadFor($user);
        $this->addFlash('success', 'Notifications marquées comme lues.');
        return $this->redirect($this->generateUrl('app_dashboard'));
    }
}
