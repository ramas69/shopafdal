<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\Order;
use App\Entity\OrderMessage;
use App\Entity\User;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/commandes/{reference}/messages', requirements: ['reference' => 'CMD-[0-9]{4}-[0-9]+'])]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class OrderMessageController extends AbstractController
{
    #[Route('/new', name: 'app_order_message_new', methods: ['POST'])]
    public function post(
        #[MapEntity(mapping: ['reference' => 'reference'])] Order $order,
        Request $request,
        EntityManagerInterface $em,
        NotificationService $notifications,
    ): RedirectResponse {
        /** @var User $user */
        $user = $this->getUser();
        $isAdmin = $user->isAdmin();

        if (!$isAdmin && $order->getCompany()->getId() !== $user->getCompany()?->getId()) {
            throw $this->createAccessDeniedException();
        }

        $body = trim((string) $request->request->get('body', ''));
        if ($body === '') {
            $this->addFlash('error', 'Message vide.');
            return $this->redirectToRoute($isAdmin ? 'app_admin_order_detail' : 'app_order_detail', ['reference' => $order->getReference()]);
        }
        if (mb_strlen($body) > 4000) {
            $body = mb_substr($body, 0, 4000);
        }

        $em->persist(new OrderMessage($order, $user, $body));
        $em->flush();

        if ($isAdmin) {
            $notifications->notifyCompany(
                $order->getCompany(),
                sprintf('Nouveau message Afdal · Commande %s', $order->getReference()),
                mb_substr($body, 0, 120),
                $this->generateUrl('app_order_detail', ['reference' => $order->getReference()]),
                Notification::TYPE_INFO,
            );
        } else {
            $notifications->notifyAdmins(
                sprintf('Message client · Commande %s', $order->getReference()),
                mb_substr($body, 0, 120),
                $this->generateUrl('app_admin_order_detail', ['reference' => $order->getReference()]),
                Notification::TYPE_INFO,
            );
        }

        return $this->redirectToRoute($isAdmin ? 'app_admin_order_detail' : 'app_order_detail', ['reference' => $order->getReference()]);
    }
}
