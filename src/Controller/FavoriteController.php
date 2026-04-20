<?php

namespace App\Controller;

use App\Entity\Favorite;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\FavoriteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/favoris')]
#[IsGranted('ROLE_CLIENT_MANAGER')]
final class FavoriteController extends AbstractController
{
    #[Route('', name: 'app_favorites')]
    public function list(FavoriteRepository $favorites): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        return $this->render('favorite/list.html.twig', [
            'favorites' => $favorites->findRecentForUser($user),
        ]);
    }

    #[Route('/toggle/{id}', name: 'app_favorites_toggle', methods: ['POST'])]
    public function toggle(
        Product $product,
        Request $request,
        FavoriteRepository $favorites,
        EntityManagerInterface $em,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if (!$product->isPublished()) {
            throw $this->createNotFoundException();
        }

        $existing = $favorites->findOneByUserAndProduct($user, $product);
        if ($existing !== null) {
            $em->remove($existing);
            $state = false;
        } else {
            $em->persist(new Favorite($user, $product));
            $state = true;
        }
        $em->flush();

        if ($request->headers->get('Accept') === 'application/json' || $request->isXmlHttpRequest()) {
            return new JsonResponse(['favorited' => $state]);
        }

        $this->addFlash('success', $state ? 'Ajouté aux favoris.' : 'Retiré des favoris.');
        return $this->redirect($request->headers->get('referer') ?: $this->generateUrl('app_favorites'));
    }
}
