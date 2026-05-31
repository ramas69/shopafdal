<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\Order;
use App\Entity\OrderDocument;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Service\AppMailer;
use App\Service\NotificationService;
use App\Service\OrderEventLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/commandes', requirements: ['reference' => 'CMD-[0-9]{4}-[0-9]+'])]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class OrderDocumentController extends AbstractController
{
    private const UPLOAD_PUBLIC_PREFIX = '/uploads/order-docs/';
    private const ALLOWED_MIME = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
    private const MAX_BYTES = 10 * 1024 * 1024; // 10 Mo

    public function __construct(
        #[Autowire('%kernel.project_dir%/public/uploads/order-docs')]
        private string $uploadDir,
    ) {}

    #[Route('/{reference}/documents', name: 'app_order_doc_upload', methods: ['POST'])]
    public function upload(
        #[MapEntity(mapping: ['reference' => 'reference'])] Order $order,
        Request $request,
        EntityManagerInterface $em,
        NotificationService $notifications,
        OrderEventLogger $events,
        AppMailer $mailer,
    ): RedirectResponse {
        $this->assertClientOwns($order);

        /** @var UploadedFile|null $file */
        $file = $request->files->get('document');
        if (!$file) {
            $max = ini_get('upload_max_filesize') ?: '?';
            $this->addFlash('error', sprintf('Aucun fichier reçu (taille max serveur : %s).', $max));
            return $this->redirectToRoute('app_order_detail', ['reference' => $order->getReference()]);
        }
        if (!$file->isValid()) {
            $msg = match ($file->getError()) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => sprintf('Fichier trop volumineux (max %s).', ini_get('upload_max_filesize') ?: '?'),
                UPLOAD_ERR_PARTIAL => 'Téléversement interrompu, réessayez.',
                UPLOAD_ERR_NO_FILE => 'Aucun fichier sélectionné.',
                UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'Erreur serveur lors de la réception du fichier.',
                default => 'Fichier invalide.',
            };
            $this->addFlash('error', $msg);
            return $this->redirectToRoute('app_order_detail', ['reference' => $order->getReference()]);
        }
        if ($file->getSize() > self::MAX_BYTES) {
            $this->addFlash('error', 'Fichier trop volumineux (max 10 Mo).');
            return $this->redirectToRoute('app_order_detail', ['reference' => $order->getReference()]);
        }
        $mime = $file->getMimeType();
        if (!$mime || !in_array($mime, self::ALLOWED_MIME, true)) {
            $this->addFlash('error', sprintf('Format non supporté (%s). Utilisez PDF, JPG, PNG ou WebP.', $mime ?: 'inconnu'));
            return $this->redirectToRoute('app_order_detail', ['reference' => $order->getReference()]);
        }

        $originalName = $file->getClientOriginalName();
        $size = $file->getSize();
        $filename = bin2hex(random_bytes(12)) . '.' . ($file->guessExtension() ?: 'bin');
        try {
            $file->move($this->uploadDir, $filename);
        } catch (FileException) {
            $this->addFlash('error', 'Échec du téléversement.');
            return $this->redirectToRoute('app_order_detail', ['reference' => $order->getReference()]);
        }

        /** @var User $user */
        $user = $this->getUser();
        $document = new OrderDocument(
            $order,
            $user,
            self::UPLOAD_PUBLIC_PREFIX . $filename,
            $originalName,
            $mime,
            $size,
        );
        $em->persist($document);
        $events->logDocumentUploaded($order, $originalName);
        try {
            $em->flush();
        } catch (\Throwable) {
            @unlink($this->uploadDir . '/' . $filename);
            $this->addFlash('error', "Échec de l'enregistrement du document.");
            return $this->redirectToRoute('app_order_detail', ['reference' => $order->getReference()]);
        }

        try {
            $notifications->notifyAdmins(
                sprintf('Document reçu · Commande %s', $order->getReference()),
                $originalName,
                $this->generateUrl('app_admin_order_detail', ['reference' => $order->getReference()]),
                Notification::TYPE_INFO,
            );
        } catch (\Throwable) {
            // notification best-effort, ne bloque pas le dépôt
        }

        $adminRecipient = $mailer->notificationRecipientAdmin();
        if ($adminRecipient !== null) {
            $mailer->sendSilently(
                (new TemplatedEmail())
                    ->to($adminRecipient)
                    ->subject(sprintf('Document reçu · Commande %s — %s', $order->getReference(), $order->getCompany()->getName()))
                    ->htmlTemplate('emails/order/document_uploaded_admin.html.twig')
                    ->context(['order' => $order, 'document' => $document]),
                'order_doc_uploaded:' . $order->getReference(),
            );
        }

        $this->addFlash('success', sprintf('Document « %s » envoyé à Afdal.', $originalName));
        return $this->redirectToRoute('app_order_detail', ['reference' => $order->getReference()]);
    }

    #[Route('/documents/{document}/download', name: 'app_order_doc_download', methods: ['GET'], requirements: ['document' => '\d+'])]
    public function download(
        #[MapEntity(mapping: ['document' => 'id'])] OrderDocument $document,
        #[Autowire('%kernel.project_dir%/public')] string $publicDir,
    ): Response {
        $this->assertClientOwns($document->getOrder());

        $absolute = $publicDir . $document->getPath();
        if (!is_file($absolute)) {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        $response = new BinaryFileResponse($absolute);
        $response->setContentDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $document->getOriginalName(),
        );
        return $response;
    }

    #[Route('/documents/{document}/delete', name: 'app_order_doc_delete', methods: ['POST'], requirements: ['document' => '\d+'])]
    public function delete(
        #[MapEntity(mapping: ['document' => 'id'])] OrderDocument $document,
        EntityManagerInterface $em,
        #[Autowire('%kernel.project_dir%/public')] string $publicDir,
    ): RedirectResponse {
        $order = $document->getOrder();
        /** @var User $user */
        $user = $this->getUser();

        $deletable = in_array($order->getStatus(), [OrderStatus::DRAFT, OrderStatus::PLACED], true);
        $sameCompany = !$user->isAdmin() && $order->getCompany()->getId() === $user->getCompany()?->getId();
        if (!$sameCompany || !$deletable) {
            throw $this->createAccessDeniedException();
        }

        $absolute = $publicDir . $document->getPath();
        if (is_file($absolute)) {
            @unlink($absolute);
        }
        $em->remove($document);
        $em->flush();

        $this->addFlash('success', 'Document supprimé.');
        return $this->redirectToRoute('app_order_detail', ['reference' => $order->getReference()]);
    }

    private function assertClientOwns(Order $order): void
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user->isAdmin() && $order->getCompany()->getId() !== $user->getCompany()?->getId()) {
            throw $this->createAccessDeniedException();
        }
    }
}
