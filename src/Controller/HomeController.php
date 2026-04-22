<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if ($user !== null) {
            return $this->redirectToRoute($user->isAdmin() ? 'app_admin' : 'app_catalogue');
        }

        return $this->render('home/index.html.twig');
    }
}
