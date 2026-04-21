<?php

namespace App\Controller;

use App\Entity\MarkingAsset;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Repository\AntennaRepository;
use App\Service\Cart;
use App\Service\NotificationService;
use App\Service\OrderEventLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_CLIENT_MANAGER')]
final class CheckoutController extends AbstractController
{
    #[Route('/commander', name: 'app_checkout')]
    public function checkout(
        Request $request,
        Cart $cart,
        AntennaRepository $antennas,
        EntityManagerInterface $em,
        NotificationService $notifications,
        OrderEventLogger $events,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $company = $user->getCompany();

        if (!$company) {
            throw $this->createAccessDeniedException();
        }

        if ($cart->isEmpty()) {
            $this->addFlash('error', 'Votre panier est vide.');
            return $this->redirectToRoute('app_cart');
        }

        $companyAntennas = $antennas->findBy(['company' => $company], ['name' => 'ASC']);

        if (empty($companyAntennas)) {
            $this->addFlash('error', 'Aucune antenne configurée. Contactez Afdal pour en ajouter une.');
            return $this->redirectToRoute('app_cart');
        }

        $errors = [];
        $selectedAntennaId = (int) $request->request->get('antenna_id', 0);
        if ($selectedAntennaId === 0 && !$request->isMethod('POST')) {
            $selectedAntennaId = (int) $request->getSession()->get('preselected_antenna_id', 0);
        }
        $notes = (string) $request->request->get('notes', '');

        if ($request->isMethod('POST')) {
            $antenna = $selectedAntennaId > 0 ? $antennas->find($selectedAntennaId) : null;
            if (!$antenna || $antenna->getCompany()->getId() !== $company->getId()) {
                $errors['antenna'] = 'Veuillez sélectionner une antenne valide.';
            }

            if (!$request->request->getBoolean('cgv_accepted')) {
                $errors['cgv'] = 'Vous devez accepter les CGV.';
            }

            if (empty($errors)) {
                $stockIssues = [];
                $accessIssues = [];
                foreach ($cart->lines() as $line) {
                    $variant = $line['variant'];
                    $product = $variant->getProduct();
                    if (!$product->isPublished() || !$product->isAllowedFor($company)) {
                        $accessIssues[] = $product->getName();
                        continue;
                    }
                    $stock = $variant->getStock();
                    if ($stock !== null && $line['quantity'] > $stock) {
                        $stockIssues[] = sprintf('%s · %s · %s : %d demandé(s), %d disponible(s)',
                            $product->getName(),
                            $variant->getColor(),
                            $variant->getSize(),
                            $line['quantity'],
                            $stock,
                        );
                    }
                }
                if (!empty($accessIssues)) {
                    $this->addFlash('error', 'Produits non accessibles : ' . implode(', ', array_unique($accessIssues)) . '. Retirez-les du panier.');
                    return $this->redirectToRoute('app_cart');
                }
                if (!empty($stockIssues)) {
                    $this->addFlash('error', 'Stock insuffisant — ' . implode(' ; ', $stockIssues));
                    return $this->redirectToRoute('app_cart');
                }

                $order = (new Order())
                    ->setReference($this->generateReference($em))
                    ->setCompany($company)
                    ->setAntenna($antenna)
                    ->setCreatedBy($user)
                    ->setStatus(OrderStatus::PLACED)
                    ->setNotes($notes ?: null)
                    ->setPlacedAt(new \DateTimeImmutable());

                $pendingBats = [];
                foreach ($cart->lines() as $line) {
                    $marking = $line['marking'];
                    $logoPath = is_array($marking) ? ($marking['logo_path'] ?? null) : null;
                    // Strip logo_path from the stored marking JSON — it lives on MarkingAsset now
                    if ($logoPath !== null) {
                        unset($marking['logo_path']);
                    }

                    $item = (new OrderItem())
                        ->setVariant($line['variant'])
                        ->setQuantity($line['quantity'])
                        ->setUnitPriceCents($line['unit_price_cents'])
                        ->setMarking($marking);
                    $order->addItem($item);

                    if ($logoPath !== null) {
                        $pendingBats[] = ['item' => $item, 'logo' => $logoPath];
                    }
                }

                $em->persist($order);
                $em->flush();

                foreach ($pendingBats as $bat) {
                    $em->persist(new MarkingAsset($bat['item'], $user, $bat['logo'], 1));
                }
                $events->logCreated($order);
                $em->flush();
                $cart->clear();
                $request->getSession()->remove('preselected_antenna_id');

                $batInfo = !empty($pendingBats) ? sprintf(' · %d BAT à valider', count($pendingBats)) : '';
                $notifications->notifyAdmins(
                    sprintf('Nouvelle commande %s', $order->getReference()),
                    sprintf('%s · %s · %d pièces · %s%s',
                        $company->getName(),
                        $antenna->getName(),
                        $order->getTotalQuantity(),
                        number_format($order->getTotalCents() / 100, 2, ',', ' ') . ' €',
                        $batInfo,
                    ),
                    $this->generateUrl('app_admin_order_detail', ['reference' => $order->getReference()]),
                    \App\Entity\Notification::TYPE_SUCCESS,
                );

                return $this->redirectToRoute('app_checkout_confirmation', ['reference' => $order->getReference()]);
            }
        }

        return $this->render('checkout/form.html.twig', [
            'lines' => $cart->lines(),
            'total_cents' => $cart->totalCents(),
            'antennas' => $companyAntennas,
            'errors' => $errors,
            'selected_antenna_id' => $selectedAntennaId,
            'notes' => $notes,
        ]);
    }

    #[Route('/commander/{reference}/confirmation', name: 'app_checkout_confirmation')]
    public function confirmation(#[MapEntity(mapping: ['reference' => 'reference'])] Order $order): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($order->getCompany()->getId() !== $user->getCompany()?->getId()) {
            throw $this->createAccessDeniedException();
        }
        return $this->render('checkout/confirmation.html.twig', ['order' => $order]);
    }

    private function generateReference(EntityManagerInterface $em): string
    {
        $year = date('Y');
        $count = (int) $em->getConnection()->fetchOne(
            "SELECT COUNT(*) FROM orders WHERE reference LIKE ?",
            ['CMD-' . $year . '-%']
        );
        return sprintf('CMD-%s-%04d', $year, $count + 1);
    }
}
