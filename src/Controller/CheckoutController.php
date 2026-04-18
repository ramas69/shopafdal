<?php

namespace App\Controller;

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

            if (empty($errors)) {
                $order = (new Order())
                    ->setReference($this->generateReference($em))
                    ->setCompany($company)
                    ->setAntenna($antenna)
                    ->setCreatedBy($user)
                    ->setStatus(OrderStatus::PLACED)
                    ->setNotes($notes ?: null)
                    ->setPlacedAt(new \DateTimeImmutable());

                foreach ($cart->lines() as $line) {
                    $item = (new OrderItem())
                        ->setVariant($line['variant'])
                        ->setQuantity($line['quantity'])
                        ->setUnitPriceCents($line['unit_price_cents'])
                        ->setMarking($line['marking']);
                    $order->addItem($item);
                }

                $em->persist($order);
                $em->flush();
                $events->logCreated($order);
                $em->flush();
                $cart->clear();
                $request->getSession()->remove('preselected_antenna_id');

                $notifications->notifyAdmins(
                    sprintf('Nouvelle commande %s', $order->getReference()),
                    sprintf('%s · %s · %d pièces · %s',
                        $company->getName(),
                        $antenna->getName(),
                        $order->getTotalQuantity(),
                        number_format($order->getTotalCents() / 100, 2, ',', ' ') . ' €'
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
