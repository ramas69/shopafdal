<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Repository\AntennaRepository;
use App\Repository\OrderRepository;
use App\Service\Cart;
use App\Service\OrderExporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/commandes')]
#[IsGranted('ROLE_CLIENT_MANAGER')]
final class OrderController extends AbstractController
{
    private const REF_PATTERN = 'CMD-[0-9]{4}-[0-9]+';

    #[Route('', name: 'app_orders')]
    public function list(Request $request, OrderRepository $orders, AntennaRepository $antennas): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $company = $user->getCompany();

        $status = (string) $request->query->get('status', '');
        $antennaId = (int) $request->query->get('antenna', 0);

        $qb = $orders->createQueryBuilder('o')
            ->andWhere('o.company = :company')
            ->setParameter('company', $company)
            ->orderBy('o.createdAt', 'DESC');

        if ($status !== '') {
            $qb->andWhere('o.status = :status')->setParameter('status', OrderStatus::from($status));
        }
        if ($antennaId > 0) {
            $qb->andWhere('o.antenna = :antenna')->setParameter('antenna', $antennaId);
        }

        return $this->render('order/list.html.twig', [
            'orders' => $qb->getQuery()->getResult(),
            'statuses' => OrderStatus::cases(),
            'antennas' => $antennas->findBy(['company' => $company], ['name' => 'ASC']),
            'current_status' => $status,
            'current_antenna' => $antennaId,
        ]);
    }

    #[Route('/{reference}', name: 'app_order_detail', requirements: ['reference' => self::REF_PATTERN])]
    public function detail(#[MapEntity(mapping: ['reference' => 'reference'])] Order $order): Response
    {
        $this->assertOwns($order);
        return $this->render('order/detail.html.twig', [
            'order' => $order,
            'timeline' => $this->buildTimeline($order),
            'can_cancel' => in_array($order->getStatus(), [OrderStatus::DRAFT, OrderStatus::PLACED], true),
        ]);
    }

    #[Route('/export.csv', name: 'app_orders_export', methods: ['GET'])]
    public function exportMultiple(Request $request, OrderRepository $orders, OrderExporter $exporter): StreamedResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $company = $user->getCompany();

        $ids = array_filter(array_map('intval', (array) $request->query->all('ids')));

        $qb = $orders->createQueryBuilder('o')
            ->andWhere('o.company = :company')
            ->setParameter('company', $company)
            ->orderBy('o.createdAt', 'DESC');

        if (!empty($ids)) {
            $qb->andWhere('o.id IN (:ids)')->setParameter('ids', $ids);
        } else {
            // fallback to current filters from list (keeps export consistent with what's displayed)
            $status = (string) $request->query->get('status', '');
            $antennaId = (int) $request->query->get('antenna', 0);
            if ($status !== '') {
                $qb->andWhere('o.status = :status')->setParameter('status', OrderStatus::from($status));
            }
            if ($antennaId > 0) {
                $qb->andWhere('o.antenna = :antenna')->setParameter('antenna', $antennaId);
            }
        }

        $filename = sprintf('commandes-%s.csv', (new \DateTimeImmutable())->format('Y-m-d'));
        return $exporter->toCsv($qb->getQuery()->getResult(), $filename);
    }

    #[Route('/{reference}/export.csv', name: 'app_order_export', methods: ['GET'], requirements: ['reference' => self::REF_PATTERN])]
    public function exportOne(#[MapEntity(mapping: ['reference' => 'reference'])] Order $order, OrderExporter $exporter): StreamedResponse
    {
        $this->assertOwns($order);
        return $exporter->toCsv([$order], $order->getReference() . '.csv');
    }

    #[Route('/{reference}/reorder', name: 'app_order_reorder', methods: ['POST'], requirements: ['reference' => self::REF_PATTERN])]
    public function reorder(#[MapEntity(mapping: ['reference' => 'reference'])] Order $order, Cart $cart): RedirectResponse
    {
        $this->assertOwns($order);
        $added = 0;
        foreach ($order->getItems() as $item) {
            $cart->add($item->getVariant()->getId(), $item->getQuantity(), $item->getMarking());
            $added += $item->getQuantity();
        }
        $this->addFlash('success', sprintf('Commande %s ajoutée au panier (%d pièces). Ajustez avant de valider.', $order->getReference(), $added));
        return $this->redirectToRoute('app_cart');
    }

    #[Route('/{reference}/cancel', name: 'app_order_cancel', methods: ['POST'], requirements: ['reference' => self::REF_PATTERN])]
    public function cancel(#[MapEntity(mapping: ['reference' => 'reference'])] Order $order, EntityManagerInterface $em): RedirectResponse
    {
        $this->assertOwns($order);
        if (!in_array($order->getStatus(), [OrderStatus::DRAFT, OrderStatus::PLACED], true)) {
            $this->addFlash('error', 'Cette commande ne peut plus être annulée.');
            return $this->redirectToRoute('app_order_detail', ['reference' => $order->getReference()]);
        }
        $order->setStatus(OrderStatus::CANCELLED);
        $em->flush();
        $this->addFlash('success', 'Commande annulée.');
        return $this->redirectToRoute('app_order_detail', ['reference' => $order->getReference()]);
    }

    private function assertOwns(Order $order): void
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($order->getCompany()->getId() !== $user->getCompany()?->getId()) {
            throw $this->createAccessDeniedException();
        }
    }

    /**
     * @return array<int, array{status: OrderStatus, state: 'done'|'current'|'upcoming'|'cancelled'}>
     */
    private function buildTimeline(Order $order): array
    {
        $happyPath = [
            OrderStatus::PLACED,
            OrderStatus::CONFIRMED,
            OrderStatus::IN_PRODUCTION,
            OrderStatus::SHIPPED,
            OrderStatus::DELIVERED,
        ];
        $current = $order->getStatus();
        $timeline = [];

        if ($current === OrderStatus::CANCELLED) {
            return [['status' => OrderStatus::CANCELLED, 'state' => 'cancelled']];
        }

        $currentIdx = array_search($current, $happyPath, true);
        foreach ($happyPath as $idx => $status) {
            $state = match (true) {
                $currentIdx === false => 'upcoming',
                $idx < $currentIdx => 'done',
                $idx === $currentIdx => 'current',
                default => 'upcoming',
            };
            $timeline[] = ['status' => $status, 'state' => $state];
        }
        return $timeline;
    }
}
