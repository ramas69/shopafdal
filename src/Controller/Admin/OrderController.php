<?php

namespace App\Controller\Admin;

use App\Entity\Notification;
use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Repository\CompanyRepository;
use App\Repository\OrderRepository;
use App\Repository\OrderEventRepository;
use App\Service\NotificationService;
use App\Service\OrderEventLogger;
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
    public function list(Request $request, OrderRepository $orders, CompanyRepository $companies): Response
    {
        $status = (string) $request->query->get('status', '');
        $companyId = (int) $request->query->get('company', 0);
        $from = (string) $request->query->get('from', '');
        $to = (string) $request->query->get('to', '');

        $qb = $orders->createQueryBuilder('o')
            ->orderBy('o.createdAt', 'DESC');

        if ($status !== '') {
            $qb->andWhere('o.status = :status')->setParameter('status', OrderStatus::from($status));
        }
        if ($companyId > 0) {
            $qb->andWhere('o.company = :company')->setParameter('company', $companyId);
        }
        if ($from !== '') {
            $qb->andWhere('o.createdAt >= :from')->setParameter('from', new \DateTimeImmutable($from));
        }
        if ($to !== '') {
            $qb->andWhere('o.createdAt < :to')->setParameter('to', (new \DateTimeImmutable($to))->modify('+1 day'));
        }

        return $this->render('admin/order/list.html.twig', [
            'orders' => $qb->getQuery()->getResult(),
            'statuses' => OrderStatus::cases(),
            'companies' => $companies->createQueryBuilder('c')->orderBy('c.name', 'ASC')->getQuery()->getResult(),
            'current_status' => $status,
            'current_company' => $companyId,
            'current_from' => $from,
            'current_to' => $to,
        ]);
    }

    #[Route('/{reference}', name: 'app_admin_order_detail', requirements: ['reference' => self::REF_PATTERN])]
    public function detail(
        #[MapEntity(mapping: ['reference' => 'reference'])] Order $order,
        OrderEventRepository $events,
        \App\Repository\MarkingAssetRepository $markings,
        \App\Repository\OrderMessageRepository $messagesRepo,
    ): Response {
        $messages = $messagesRepo->findForOrder($order);
        $messagesRepo->markAllReadForAdmin($order);
        return $this->render('admin/order/detail.html.twig', [
            'order' => $order,
            'allowed_transitions' => self::TRANSITIONS[$order->getStatus()->value] ?? [],
            'events' => $events->findForOrder($order),
            'has_pending_bats' => $markings->hasAnyPendingForOrder($order),
            'messages' => $messages,
        ]);
    }

    #[Route('/{reference}/shipping', name: 'app_admin_order_shipping', methods: ['POST'], requirements: ['reference' => self::REF_PATTERN])]
    public function updateShipping(
        #[MapEntity(mapping: ['reference' => 'reference'])] Order $order,
        Request $request,
        EntityManagerInterface $em,
        NotificationService $notifications,
        OrderEventLogger $events,
    ): RedirectResponse {
        $carrier = trim((string) $request->request->get('carrier', '')) ?: null;
        $tracking = trim((string) $request->request->get('tracking_number', '')) ?: null;
        $etaStr = trim((string) $request->request->get('estimated_delivery_at', ''));
        $eta = null;
        if ($etaStr !== '') {
            try {
                $eta = new \DateTimeImmutable($etaStr);
            } catch (\Exception) {
                $this->addFlash('error', 'Date de livraison invalide.');
                return $this->redirectToRoute('app_admin_order_detail', ['reference' => $order->getReference()]);
            }
        }

        $changed = $order->getCarrier() !== $carrier
            || $order->getTrackingNumber() !== $tracking
            || $order->getEstimatedDeliveryAt()?->format('Y-m-d') !== $eta?->format('Y-m-d');

        $order->setCarrier($carrier)->setTrackingNumber($tracking)->setEstimatedDeliveryAt($eta);

        if ($changed) {
            $events->logShippingUpdated($order, $carrier, $tracking, $eta);
            $em->flush();
            $notifications->notifyCompany(
                $order->getCompany(),
                sprintf('Livraison mise à jour · Commande %s', $order->getReference()),
                sprintf('%s%s%s',
                    $carrier ?: 'Transporteur non défini',
                    $tracking ? ' · n° ' . $tracking : '',
                    $eta ? ' · livraison estimée ' . $eta->format('d/m/Y') : '',
                ),
                $this->generateUrl('app_order_detail', ['reference' => $order->getReference()]),
                Notification::TYPE_INFO,
            );
            $this->addFlash('success', 'Livraison mise à jour.');
        } else {
            $this->addFlash('info', 'Aucun changement.');
        }

        return $this->redirectToRoute('app_admin_order_detail', ['reference' => $order->getReference()]);
    }

    #[Route('/{reference}/transition', name: 'app_admin_order_transition', methods: ['POST'], requirements: ['reference' => self::REF_PATTERN])]
    public function transition(
        #[MapEntity(mapping: ['reference' => 'reference'])] Order $order,
        Request $request,
        EntityManagerInterface $em,
        NotificationService $notifications,
        OrderEventLogger $events,
    ): RedirectResponse {
        $target = OrderStatus::tryFrom((string) $request->request->get('status', ''));
        $allowed = self::TRANSITIONS[$order->getStatus()->value] ?? [];

        if (!$target || !in_array($target, $allowed, true)) {
            $this->addFlash('error', 'Transition non autorisée.');
            return $this->redirectToRoute('app_admin_order_detail', ['reference' => $order->getReference()]);
        }

        $previousStatus = $order->getStatus();
        $order->setStatus($target);
        $events->logStatusChanged($order, $previousStatus, $target);

        if ($request->request->has('admin_notes')) {
            $newNote = trim((string) $request->request->get('admin_notes')) ?: null;
            if ($newNote !== $order->getAdminNotes()) {
                $order->setAdminNotes($newNote);
                if ($newNote) {
                    $events->logAdminNote($order, $newNote);
                }
            }
        }

        $em->flush();

        // Notify the client manager who created the order
        $type = match ($target) {
            OrderStatus::CANCELLED => Notification::TYPE_DESTRUCTIVE,
            OrderStatus::DELIVERED => Notification::TYPE_SUCCESS,
            default => Notification::TYPE_INFO,
        };

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
