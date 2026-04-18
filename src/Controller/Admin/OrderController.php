<?php

namespace App\Controller\Admin;

use App\Entity\Notification;
use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/commandes')]
#[IsGranted('ROLE_ADMIN')]
final class OrderController extends AbstractController
{
    private const REF_PATTERN = 'CMD-[0-9]{4}-[0-9]+';

    /** Allowed transitions: current status => [next statuses] */
    private const TRANSITIONS = [
        'placed' => [OrderStatus::CONFIRMED, OrderStatus::CANCELLED],
        'confirmed' => [OrderStatus::IN_PRODUCTION, OrderStatus::CANCELLED],
        'in_production' => [OrderStatus::SHIPPED, OrderStatus::CANCELLED],
        'shipped' => [OrderStatus::DELIVERED],
    ];

    #[Route('', name: 'app_admin_orders')]
    public function list(Request $request, OrderRepository $orders): Response
    {
        $status = (string) $request->query->get('status', '');

        $qb = $orders->createQueryBuilder('o')
            ->orderBy('o.createdAt', 'DESC');

        if ($status !== '') {
            $qb->andWhere('o.status = :status')->setParameter('status', OrderStatus::from($status));
        }

        return $this->render('admin/order/list.html.twig', [
            'orders' => $qb->getQuery()->getResult(),
            'statuses' => OrderStatus::cases(),
            'current_status' => $status,
        ]);
    }

    #[Route('/{reference}', name: 'app_admin_order_detail', requirements: ['reference' => self::REF_PATTERN])]
    public function detail(#[MapEntity(mapping: ['reference' => 'reference'])] Order $order): Response
    {
        return $this->render('admin/order/detail.html.twig', [
            'order' => $order,
            'allowed_transitions' => self::TRANSITIONS[$order->getStatus()->value] ?? [],
        ]);
    }

    #[Route('/{reference}/transition', name: 'app_admin_order_transition', methods: ['POST'], requirements: ['reference' => self::REF_PATTERN])]
    public function transition(
        #[MapEntity(mapping: ['reference' => 'reference'])] Order $order,
        Request $request,
        EntityManagerInterface $em,
        NotificationService $notifications,
    ): RedirectResponse {
        $target = OrderStatus::tryFrom((string) $request->request->get('status', ''));
        $allowed = self::TRANSITIONS[$order->getStatus()->value] ?? [];

        if (!$target || !in_array($target, $allowed, true)) {
            $this->addFlash('error', 'Transition non autorisée.');
            return $this->redirectToRoute('app_admin_order_detail', ['reference' => $order->getReference()]);
        }

        $order->setStatus($target);

        if ($request->request->has('admin_notes')) {
            $order->setAdminNotes(trim((string) $request->request->get('admin_notes')) ?: null);
        }

        $em->flush();

        // Notify the client manager who created the order
        $type = $target === OrderStatus::CANCELLED ? Notification::TYPE_DESTRUCTIVE
              : ($target === OrderStatus::DELIVERED ? Notification::TYPE_SUCCESS : Notification::TYPE_INFO);

        $notifications->notify(
            $order->getCreatedBy(),
            sprintf('Commande %s : %s', $order->getReference(), $target->label()),
            match ($target) {
                OrderStatus::CONFIRMED => 'Afdal a confirmé votre commande. La production va démarrer.',
                OrderStatus::IN_PRODUCTION => 'Votre commande est maintenant en production.',
                OrderStatus::SHIPPED => 'Votre commande a été expédiée.',
                OrderStatus::DELIVERED => 'Votre commande est livrée. Merci !',
                OrderStatus::CANCELLED => 'Votre commande a été annulée par Afdal.',
                default => null,
            },
            $this->generateUrl('app_order_detail', ['reference' => $order->getReference()]),
            $type,
        );

        $this->addFlash('success', sprintf('Statut mis à jour : %s', $target->label()));
        return $this->redirectToRoute('app_admin_order_detail', ['reference' => $order->getReference()]);
    }
}
