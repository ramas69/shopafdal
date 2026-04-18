<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Repository\AntennaRepository;
use App\Repository\OrderRepository;
use App\Entity\Notification;
use App\Entity\OrderItem;
use App\Repository\ProductRepository;
use App\Repository\ProductVariantRepository;
use App\Service\Cart;
use App\Service\NotificationService;
use App\Service\OrderEventLogger;
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

    #[Route('/{reference}/edit', name: 'app_order_edit', requirements: ['reference' => self::REF_PATTERN])]
    public function edit(
        #[MapEntity(mapping: ['reference' => 'reference'])] Order $order,
        Request $request,
        AntennaRepository $antennas,
        ProductRepository $productsRepo,
        EntityManagerInterface $em,
        NotificationService $notifications,
        OrderEventLogger $events,
    ): Response {
        $this->assertOwns($order);
        if ($order->getStatus() !== OrderStatus::PLACED) {
            $this->addFlash('error', 'Cette commande ne peut plus être modifiée (Afdal l\'a déjà confirmée ou traitée).');
            return $this->redirectToRoute('app_order_detail', ['reference' => $order->getReference()]);
        }

        /** @var User $user */
        $user = $this->getUser();
        $companyAntennas = $antennas->findBy(['company' => $user->getCompany()], ['name' => 'ASC']);

        if ($request->isMethod('POST')) {
            $quantities = $request->request->all('quantity'); // keyed by item id
            $removed = $request->request->all('remove');
            $antennaId = (int) $request->request->get('antenna_id', 0);
            $notes = trim((string) $request->request->get('notes', ''));

            $antenna = $antennaId > 0 ? $antennas->find($antennaId) : null;
            if (!$antenna || $antenna->getCompany()->getId() !== $user->getCompany()?->getId()) {
                $this->addFlash('error', 'Antenne invalide.');
                return $this->redirectToRoute('app_order_edit', ['reference' => $order->getReference()]);
            }

            $itemsRemoved = [];
            $itemsChanged = [];
            $previousAntenna = $order->getAntenna()->getName();
            $previousNotes = $order->getNotes();

            foreach ($order->getItems() as $item) {
                $itemId = (string) $item->getId();
                $label = sprintf('%s · %s · %s',
                    $item->getVariant()->getProduct()->getName(),
                    $item->getVariant()->getColor(),
                    $item->getVariant()->getSize()
                );
                if (isset($removed[$itemId])) {
                    $itemsRemoved[] = ['label' => $label, 'qty' => $item->getQuantity()];
                    $em->remove($item);
                    continue;
                }
                $qty = (int) ($quantities[$itemId] ?? $item->getQuantity());
                if ($qty < 1) {
                    $itemsRemoved[] = ['label' => $label, 'qty' => $item->getQuantity()];
                    $em->remove($item);
                } elseif ($qty !== $item->getQuantity()) {
                    $itemsChanged[] = ['label' => $label, 'from' => $item->getQuantity(), 'to' => $qty];
                    $item->setQuantity($qty);
                }
            }

            if ($order->getItems()->filter(fn($i) => !$em->getUnitOfWork()->isScheduledForDelete($i))->isEmpty()) {
                $this->addFlash('error', 'La commande doit contenir au moins un article.');
                return $this->redirectToRoute('app_order_edit', ['reference' => $order->getReference()]);
            }

            $order->setAntenna($antenna);
            $order->setNotes($notes ?: null);
            $order->setStatus(OrderStatus::PLACED); // trigger updatedAt via setter

            $em->flush(); // flush changes so totals are up to date

            $events->logItemsEdited($order, added: [], removed: $itemsRemoved, changed: $itemsChanged);
            $events->logAntennaChanged($order, $previousAntenna, $antenna->getName());
            $events->logNotesUpdated($order, $previousNotes, $order->getNotes());
            $em->flush();

            $notifications->notifyAdmins(
                sprintf('Commande %s modifiée', $order->getReference()),
                sprintf('Le client a ajusté la commande avant confirmation. Nouveau total : %s',
                    number_format($order->getTotalCents() / 100, 2, ',', ' ') . ' €'
                ),
                $this->generateUrl('app_admin_order_detail', ['reference' => $order->getReference()]),
                Notification::TYPE_WARNING,
            );

            $this->addFlash('success', 'Commande mise à jour.');
            return $this->redirectToRoute('app_order_detail', ['reference' => $order->getReference()]);
        }

        $availableProducts = $productsRepo->findPublished();
        $productsJson = array_map(fn($p) => [
            'id' => $p->getId(),
            'name' => $p->getName(),
            'category' => $p->getCategory() ?? '',
            'price' => $p->getBasePriceCents(),
            'image' => $p->getImages()[0] ?? null,
            'variants' => array_values(array_map(fn($v) => [
                'id' => $v->getId(),
                'size' => $v->getSize(),
                'color' => $v->getColor(),
                'hex' => $v->getColorHex(),
                'sku' => $v->getSku(),
            ], $p->getVariants()->toArray())),
        ], $availableProducts);

        return $this->render('order/edit.html.twig', [
            'order' => $order,
            'antennas' => $companyAntennas,
            'available_products_json' => json_encode($productsJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
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

    #[Route('/{reference}/add-item', name: 'app_order_add_item', methods: ['POST'], requirements: ['reference' => self::REF_PATTERN])]
    public function addItem(
        #[MapEntity(mapping: ['reference' => 'reference'])] Order $order,
        Request $request,
        ProductVariantRepository $variants,
        EntityManagerInterface $em,
        NotificationService $notifications,
        OrderEventLogger $events,
    ): RedirectResponse {
        $this->assertOwns($order);
        if ($order->getStatus() !== OrderStatus::PLACED) {
            $this->addFlash('error', 'La commande ne peut plus être modifiée.');
            return $this->redirectToRoute('app_order_detail', ['reference' => $order->getReference()]);
        }

        $quantities = $request->request->all('quantities');
        $markingZone = trim((string) $request->request->get('marking_zone', ''));
        $marking = $markingZone !== '' ? [
            'zone' => $markingZone,
            'size' => (string) $request->request->get('marking_size', 'A4'),
        ] : null;

        $added = [];
        $totalAddedQty = 0;

        foreach ($quantities as $variantId => $qtyRaw) {
            $qty = (int) $qtyRaw;
            if ($qty < 1) {
                continue;
            }
            $variant = $variants->find((int) $variantId);
            if (!$variant) {
                continue;
            }

            $label = sprintf('%s · %s · %s',
                $variant->getProduct()->getName(),
                $variant->getColor(),
                $variant->getSize()
            );

            // Merge if same variant + same marking already exists; otherwise add new item
            $merged = false;
            foreach ($order->getItems() as $existing) {
                if ($existing->getVariant()->getId() === $variant->getId()
                    && ($existing->getMarking() ?? []) === ($marking ?? [])) {
                    $existing->setQuantity($existing->getQuantity() + $qty);
                    $merged = true;
                    break;
                }
            }
            if (!$merged) {
                $item = (new OrderItem())
                    ->setVariant($variant)
                    ->setQuantity($qty)
                    ->setUnitPriceCents($variant->getProduct()->getBasePriceCents())
                    ->setMarking($marking);
                $order->addItem($item);
                $em->persist($item);
            }
            $added[] = ['label' => $label, 'qty' => $qty];
            $totalAddedQty += $qty;
        }

        if (empty($added)) {
            $this->addFlash('error', 'Indiquez au moins une quantité pour ajouter au panier.');
            return $this->redirectToRoute('app_order_edit', ['reference' => $order->getReference()]);
        }

        $order->setStatus(OrderStatus::PLACED); // refresh updatedAt
        $em->flush();
        $events->logItemsEdited($order, added: $added, removed: [], changed: []);
        $em->flush();

        $notifications->notifyAdmins(
            sprintf('Commande %s : %d article(s) ajouté(s)', $order->getReference(), count($added)),
            sprintf('+%d pièce(s) · nouveau total %s',
                $totalAddedQty,
                number_format($order->getTotalCents() / 100, 2, ',', ' ') . ' €'
            ),
            $this->generateUrl('app_admin_order_detail', ['reference' => $order->getReference()]),
            Notification::TYPE_WARNING,
        );

        $this->addFlash('success', sprintf('%d pièce(s) ajoutée(s) (%d ligne(s)) à la commande.', $totalAddedQty, count($added)));
        return $this->redirectToRoute('app_order_edit', ['reference' => $order->getReference()]);
    }

    #[Route('/{reference}/cancel', name: 'app_order_cancel', methods: ['POST'], requirements: ['reference' => self::REF_PATTERN])]
    public function cancel(
        #[MapEntity(mapping: ['reference' => 'reference'])] Order $order,
        EntityManagerInterface $em,
        NotificationService $notifications,
        OrderEventLogger $events,
    ): RedirectResponse {
        $this->assertOwns($order);
        if (!in_array($order->getStatus(), [OrderStatus::DRAFT, OrderStatus::PLACED], true)) {
            $this->addFlash('error', 'Cette commande ne peut plus être annulée.');
            return $this->redirectToRoute('app_order_detail', ['reference' => $order->getReference()]);
        }
        $previousStatus = $order->getStatus();
        $order->setStatus(OrderStatus::CANCELLED);
        $events->logStatusChanged($order, $previousStatus, OrderStatus::CANCELLED);
        $em->flush();

        $notifications->notifyAdmins(
            sprintf('Commande %s annulée', $order->getReference()),
            sprintf('Le client %s a annulé sa commande avant confirmation.', $order->getCompany()->getName()),
            $this->generateUrl('app_admin_order_detail', ['reference' => $order->getReference()]),
            Notification::TYPE_DESTRUCTIVE,
        );

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
     * @return array<int, array{status: OrderStatus, state: 'done'|'current'|'upcoming'|'cancelled', at: ?\DateTimeImmutable}>
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

        if ($current === OrderStatus::CANCELLED) {
            return [[
                'status' => OrderStatus::CANCELLED,
                'state' => 'cancelled',
                'at' => $order->getCancelledAt(),
            ]];
        }

        $timeline = [];
        $currentIdx = array_search($current, $happyPath, true);
        foreach ($happyPath as $idx => $status) {
            $state = match (true) {
                $currentIdx === false => 'upcoming',
                $idx < $currentIdx => 'done',
                $idx === $currentIdx => 'current',
                default => 'upcoming',
            };
            $timeline[] = [
                'status' => $status,
                'state' => $state,
                'at' => $order->getStatusTimestamp($status),
            ];
        }
        return $timeline;
    }
}
