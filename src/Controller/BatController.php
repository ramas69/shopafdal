<?php

namespace App\Controller;

use App\Entity\MarkingAsset;
use App\Entity\Notification;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\User;
use App\Repository\MarkingAssetRepository;
use App\Service\NotificationService;
use App\Service\OrderEventLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/commandes/{reference}/bat', requirements: ['reference' => 'CMD-[0-9]{4}-[0-9]+'])]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class BatController extends AbstractController
{
    private const UPLOAD_PUBLIC_PREFIX = '/uploads/markings/';
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml', 'application/pdf'];

    public function __construct(
        #[Autowire('%kernel.project_dir%/public/uploads/markings')]
        private string $uploadDir,
    ) {}

    #[Route('/upload/{item}', name: 'app_bat_upload', methods: ['POST'], requirements: ['item' => '\d+'])]
    public function upload(
        #[MapEntity(mapping: ['reference' => 'reference'])] Order $order,
        #[MapEntity(mapping: ['item' => 'id'])] OrderItem $item,
        Request $request,
        MarkingAssetRepository $assets,
        EntityManagerInterface $em,
        NotificationService $notifications,
        OrderEventLogger $events,
    ): RedirectResponse {
        $this->assertClientOwns($order);
        $this->assertItemBelongsTo($item, $order);
        if (!$item->requiresMarking()) {
            throw $this->createAccessDeniedException('Cet article ne nécessite pas de marquage.');
        }

        /** @var UploadedFile|null $file */
        $file = $request->files->get('logo');
        if (!$file) {
            $max = ini_get('upload_max_filesize') ?: '?';
            $this->addFlash('error', sprintf('Aucun fichier reçu (taille max serveur : %s). Vérifiez la taille du fichier.', $max));
            return $this->redirectToRoute('app_order_detail', ['reference' => $order->getReference()]);
        }
        if (!$file->isValid()) {
            $code = $file->getError();
            $msg = match ($code) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => sprintf('Fichier trop volumineux (max %s).', ini_get('upload_max_filesize') ?: '?'),
                UPLOAD_ERR_PARTIAL => 'Téléversement interrompu, réessayez.',
                UPLOAD_ERR_NO_FILE => 'Aucun fichier sélectionné.',
                UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'Erreur serveur lors de la réception du fichier.',
                default => 'Fichier invalide.',
            };
            $this->addFlash('error', $msg);
            return $this->redirectToRoute('app_order_detail', ['reference' => $order->getReference()]);
        }
        $mime = $file->getMimeType();
        if (!$mime || !in_array($mime, self::ALLOWED_MIME, true)) {
            $this->addFlash('error', sprintf('Format non supporté (%s). Utilisez JPG, PNG, WebP, SVG ou PDF.', $mime ?: 'inconnu'));
            return $this->redirectToRoute('app_order_detail', ['reference' => $order->getReference()]);
        }

        $filename = bin2hex(random_bytes(12)) . '.' . ($file->guessExtension() ?: 'bin');
        try {
            $file->move($this->uploadDir, $filename);
        } catch (FileException) {
            $this->addFlash('error', 'Échec du téléversement.');
            return $this->redirectToRoute('app_order_detail', ['reference' => $order->getReference()]);
        }

        $previous = $assets->findLatestForItem($item);
        $version = $previous ? $previous->getVersion() + 1 : 1;

        /** @var User $user */
        $user = $this->getUser();
        $asset = new MarkingAsset($item, $user, self::UPLOAD_PUBLIC_PREFIX . $filename, $version);
        $em->persist($asset);

        $label = $this->itemLabel($item);
        $events->logBatUploaded($order, $label, $version);
        $em->flush();

        $notifications->notifyAdmins(
            sprintf('BAT reçu · Commande %s', $order->getReference()),
            sprintf('v%d · %s', $version, $label),
            $this->generateUrl('app_admin_order_detail', ['reference' => $order->getReference()]),
            Notification::TYPE_INFO,
        );

        $this->addFlash('success', sprintf('Logo v%d envoyé à Afdal pour validation.', $version));
        return $this->redirectToRoute('app_order_detail', ['reference' => $order->getReference()]);
    }

    #[Route('/approve/{asset}', name: 'app_bat_approve', methods: ['POST'], requirements: ['asset' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function approve(
        #[MapEntity(mapping: ['reference' => 'reference'])] Order $order,
        #[MapEntity(mapping: ['asset' => 'id'])] MarkingAsset $asset,
        EntityManagerInterface $em,
        NotificationService $notifications,
        OrderEventLogger $events,
    ): RedirectResponse {
        $this->assertAssetBelongsTo($asset, $order);

        /** @var User $admin */
        $admin = $this->getUser();
        $asset->approve($admin);

        $item = $asset->getOrderItem();
        $label = $this->itemLabel($item);
        $events->logBatApproved($order, $label, $asset->getVersion());
        $em->flush();

        $notifications->notifyCompany(
            $order->getCompany(),
            sprintf('BAT validé · Commande %s', $order->getReference()),
            sprintf('v%d · %s', $asset->getVersion(), $label),
            $this->generateUrl('app_order_detail', ['reference' => $order->getReference()]),
            Notification::TYPE_SUCCESS,
        );

        $this->addFlash('success', 'BAT validé.');
        return $this->redirectToRoute('app_admin_order_detail', ['reference' => $order->getReference()]);
    }

    #[Route('/approve-all', name: 'app_bat_approve_all', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function approveAllForOrder(
        #[MapEntity(mapping: ['reference' => 'reference'])] Order $order,
        EntityManagerInterface $em,
        NotificationService $notifications,
        OrderEventLogger $events,
    ): RedirectResponse {
        /** @var User $admin */
        $admin = $this->getUser();

        $pending = $em->createQueryBuilder()
            ->select('m')
            ->from(MarkingAsset::class, 'm')
            ->join('m.orderItem', 'i')
            ->where('i.order = :o')
            ->andWhere('m.status = :pending')
            ->setParameter('o', $order)
            ->setParameter('pending', \App\Enum\MarkingStatus::PENDING)
            ->getQuery()
            ->getResult();

        if (empty($pending)) {
            $this->addFlash('warning', 'Aucun BAT en attente sur cette commande.');
            return $this->redirectToRoute('app_admin_order_detail', ['reference' => $order->getReference()]);
        }

        foreach ($pending as $asset) {
            $asset->approve($admin);
            $events->logBatApproved($order, $this->itemLabel($asset->getOrderItem()), $asset->getVersion());
        }
        $em->flush();

        $notifications->notifyCompany(
            $order->getCompany(),
            sprintf('%d BAT validé(s) · Commande %s', count($pending), $order->getReference()),
            'Afdal a validé l\'ensemble des BAT en attente.',
            $this->generateUrl('app_order_detail', ['reference' => $order->getReference()]),
            Notification::TYPE_SUCCESS,
        );

        $this->addFlash('success', sprintf('%d BAT validé(s) en lot.', count($pending)));
        return $this->redirectToRoute('app_admin_order_detail', ['reference' => $order->getReference()]);
    }

    #[Route('/reject/{asset}', name: 'app_bat_reject', methods: ['POST'], requirements: ['asset' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function reject(
        #[MapEntity(mapping: ['reference' => 'reference'])] Order $order,
        #[MapEntity(mapping: ['asset' => 'id'])] MarkingAsset $asset,
        Request $request,
        EntityManagerInterface $em,
        NotificationService $notifications,
        OrderEventLogger $events,
    ): RedirectResponse {
        $this->assertAssetBelongsTo($asset, $order);

        /** @var User $admin */
        $admin = $this->getUser();
        $feedback = trim((string) $request->request->get('feedback', ''));
        if ($feedback === '') {
            $this->addFlash('error', 'Indiquez un motif pour aider le client à corriger.');
            return $this->redirectToRoute('app_admin_order_detail', ['reference' => $order->getReference()]);
        }
        $asset->reject($admin, $feedback);

        $item = $asset->getOrderItem();
        $label = $this->itemLabel($item);
        $events->logBatRejected($order, $label, $asset->getVersion(), $feedback);
        $em->flush();

        $notifications->notifyCompany(
            $order->getCompany(),
            sprintf('BAT à refaire · Commande %s', $order->getReference()),
            sprintf('v%d · %s — %s', $asset->getVersion(), $label, mb_substr($feedback, 0, 120)),
            $this->generateUrl('app_order_detail', ['reference' => $order->getReference()]),
            Notification::TYPE_WARNING,
        );

        $this->addFlash('success', 'BAT refusé · le client est notifié.');
        return $this->redirectToRoute('app_admin_order_detail', ['reference' => $order->getReference()]);
    }

    private function itemLabel(OrderItem $item): string
    {
        $v = $item->getVariant();
        return sprintf('%s · %s · %s', $v->getProduct()->getName(), $v->getColor(), $v->getSize());
    }

    private function assertClientOwns(Order $order): void
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user->isAdmin() && $order->getCompany()->getId() !== $user->getCompany()?->getId()) {
            throw $this->createAccessDeniedException();
        }
    }

    private function assertItemBelongsTo(OrderItem $item, Order $order): void
    {
        if ($item->getOrder()->getId() !== $order->getId()) {
            throw $this->createNotFoundException();
        }
    }

    private function assertAssetBelongsTo(MarkingAsset $asset, Order $order): void
    {
        if ($asset->getOrderItem()->getOrder()->getId() !== $order->getId()) {
            throw $this->createNotFoundException();
        }
    }
}
