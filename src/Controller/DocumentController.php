<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\OrderDocumentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/documents')]
#[IsGranted('ROLE_CLIENT_MANAGER')]
final class DocumentController extends AbstractController
{
    #[Route('', name: 'app_documents', methods: ['GET'])]
    public function index(OrderDocumentRepository $documents): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('documents/index.html.twig', [
            'documents' => $documents->findForCompany($user->getCompany()),
        ]);
    }
}
