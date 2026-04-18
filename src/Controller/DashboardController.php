<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\CompanyRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->redirectToRoute($user->isAdmin() ? 'app_admin' : 'app_catalogue');
    }

    #[Route('/admin', name: 'app_admin')]
    #[IsGranted('ROLE_ADMIN')]
    public function admin(
        CompanyRepository $companies,
        ProductRepository $products,
        OrderRepository $orders,
    ): Response {
        return $this->render('dashboard/admin_stub.html.twig', [
            'stats' => [
                'companies' => $companies->count([]),
                'products' => $products->count(['active' => true]),
                'orders' => $orders->count([]),
                'to_process' => count($orders->findToProcess()),
            ],
        ]);
    }
}
