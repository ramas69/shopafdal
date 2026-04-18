<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\User;
use App\Repository\OrderRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/commandes')]
#[IsGranted('ROLE_CLIENT_MANAGER')]
final class OrderController extends AbstractController
{
    #[Route('', name: 'app_orders')]
    public function list(OrderRepository $orders): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        return $this->render('order/list.html.twig', [
            'orders' => $orders->findForCompany($user->getCompany()),
        ]);
    }

    #[Route('/{reference}', name: 'app_order_detail')]
    public function detail(#[MapEntity(mapping: ['reference' => 'reference'])] Order $order): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($order->getCompany()->getId() !== $user->getCompany()?->getId()) {
            throw $this->createAccessDeniedException();
        }
        return $this->render('order/detail.html.twig', ['order' => $order]);
    }
}
