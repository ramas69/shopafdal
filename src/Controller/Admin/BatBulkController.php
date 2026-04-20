<?php

namespace App\Controller\Admin;

use App\Entity\MarkingAsset;
use App\Entity\Notification;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\User;
use App\Service\NotificationService;
use App\Service\OrderEventLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/bat')]
#[IsGranted('ROLE_ADMIN')]
final class BatBulkController extends AbstractController
{
    #[Route('/bulk-approve', name: 'app_bat_bulk_approve', methods: ['POST'])]
    public function bulkApprove(
        Request $request,
        EntityManagerInterface $em,
        NotificationService $notifications,
        OrderEventLogger $events,
    ): RedirectResponse {
        /** @var User $admin */
        $admin = $this->getUser();

        $raw = $request->request->all('assets');
        $ids = array_values(array_filter(array_map('intval', is_array($raw) ? $raw : [])));
        if (empty($ids)) {
            $this->addFlash('warning', 'Aucun BAT sélectionné.');
            return $this->redirectToRoute('app_admin');
        }

        /** @var MarkingAsset[] $selected */
        $selected = $em->getRepository(MarkingAsset::class)->findBy(['id' => $ids]);

        $approvedByOrder = [];
        foreach ($selected as $asset) {
            if (!$asset->isPending()) {
                continue;
            }
            $asset->approve($admin);
            $order = $asset->getOrderItem()->getOrder();
            $events->logBatApproved($order, $this->itemLabel($asset->getOrderItem()), $asset->getVersion());
            $approvedByOrder[$order->getId()] ??= ['order' => $order, 'count' => 0];
            $approvedByOrder[$order->getId()]['count']++;
        }
        $em->flush();

        foreach ($approvedByOrder as $entry) {
            /** @var Order $o */
            $o = $entry['order'];
            $notifications->notifyCompany(
                $o->getCompany(),
                sprintf('%d BAT validé(s) · Commande %s', $entry['count'], $o->getReference()),
                'Afdal a validé vos BAT.',
                $this->generateUrl('app_order_detail', ['reference' => $o->getReference()]),
                Notification::TYPE_SUCCESS,
            );
        }

        $total = array_sum(array_map(fn($e) => $e['count'], $approvedByOrder));
        if ($total === 0) {
            $this->addFlash('warning', 'Aucun BAT en attente parmi les sélectionnés.');
        } else {
            $this->addFlash('success', sprintf('%d BAT validé(s) en lot sur %d commande(s).', $total, count($approvedByOrder)));
        }
        return $this->redirectToRoute('app_admin');
    }

    private function itemLabel(OrderItem $item): string
    {
        $v = $item->getVariant();
        return sprintf('%s · %s · %s', $v->getProduct()->getName(), $v->getColor(), $v->getSize());
    }
}
