<?php

namespace App\Controller;

use App\Service\Cart;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/panier')]
#[IsGranted('ROLE_CLIENT_MANAGER')]
final class CartController extends AbstractController
{
    #[Route('', name: 'app_cart')]
    public function index(Cart $cart): Response
    {
        return $this->render('cart/index.html.twig', [
            'lines' => $cart->lines(),
            'total_cents' => $cart->totalCents(),
        ]);
    }

    #[Route('/update/{lineId}', name: 'app_cart_update', methods: ['POST'])]
    public function update(string $lineId, Request $request, Cart $cart): RedirectResponse
    {
        $qty = (int) $request->request->get('quantity', 1);
        foreach ($cart->lines() as $line) {
            if ($line['line_id'] !== $lineId) {
                continue;
            }
            $stock = $line['variant']->getStock();
            if ($stock !== null && $qty > $stock) {
                $qty = $stock;
                $this->addFlash('warning', sprintf('Quantité ajustée au stock disponible (%d).', $stock));
            }
            break;
        }
        $cart->updateQuantity($lineId, $qty);
        return $this->redirectToRoute('app_cart');
    }

    #[Route('/remove/{lineId}', name: 'app_cart_remove', methods: ['POST'])]
    public function remove(string $lineId, Cart $cart): RedirectResponse
    {
        $cart->remove($lineId);
        return $this->redirectToRoute('app_cart');
    }

    #[Route('/clear', name: 'app_cart_clear', methods: ['POST'])]
    public function clear(Cart $cart): RedirectResponse
    {
        $cart->clear();
        $this->addFlash('success', 'Panier vidé.');
        return $this->redirectToRoute('app_cart');
    }
}
