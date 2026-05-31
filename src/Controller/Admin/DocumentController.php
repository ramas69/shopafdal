<?php

namespace App\Controller\Admin;

use App\Repository\OrderDocumentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/documents')]
#[IsGranted('ROLE_ADMIN')]
final class DocumentController extends AbstractController
{
    #[Route('', name: 'app_admin_documents', methods: ['GET'])]
    public function index(OrderDocumentRepository $documents): Response
    {
        return $this->render('admin/documents/index.html.twig', [
            'documents' => $documents->findAllRecent(),
        ]);
    }
}
