<?php

namespace App\Controller;

use App\Entity\Antenna;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Repository\AntennaRepository;
use App\Repository\OrderRepository;
use App\Service\OrderExporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/antennes')]
#[IsGranted('ROLE_CLIENT_MANAGER')]
final class AntennaController extends AbstractController
{
    #[Route('', name: 'app_antennas')]
    public function list(AntennaRepository $antennas): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        return $this->render('antenna/list.html.twig', [
            'antennas' => $antennas->findBy(['company' => $user->getCompany()], ['name' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'app_antenna_new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        return $this->handleForm(new Antenna(), $request, $em);
    }

    #[Route('/{id}', name: 'app_antenna_detail', requirements: ['id' => '\d+'])]
    public function detail(Antenna $antenna, Request $request, OrderRepository $orders, AntennaRepository $antennas, EntityManagerInterface $em): Response
    {
        $this->assertOwns($antenna);

        // Timeline filters
        $statusFilter = (string) $request->query->get('status', '');
        $fromDate = (string) $request->query->get('from', '');
        $toDate = (string) $request->query->get('to', '');

        $qb = $orders->createQueryBuilder('o')
            ->andWhere('o.antenna = :antenna')
            ->setParameter('antenna', $antenna)
            ->orderBy('o.createdAt', 'DESC');

        if ($statusFilter !== '') {
            $qb->andWhere('o.status = :status')->setParameter('status', OrderStatus::from($statusFilter));
        }
        if ($fromDate !== '') {
            $qb->andWhere('o.createdAt >= :from')->setParameter('from', new \DateTimeImmutable($fromDate));
        }
        if ($toDate !== '') {
            $qb->andWhere('o.createdAt <= :to')->setParameter('to', new \DateTimeImmutable($toDate . ' 23:59:59'));
        }
        $filteredOrders = $qb->getQuery()->getResult();

        // Full stats (unfiltered)
        $allOrders = $orders->findBy(['antenna' => $antenna], ['createdAt' => 'DESC']);
        $totalQty = 0;
        $totalCents = 0;
        foreach ($allOrders as $o) {
            $totalQty += $o->getTotalQuantity();
            $totalCents += $o->getTotalCents();
        }

        // Top 5 products for this antenna
        $topProducts = $em->getConnection()->fetchAllAssociative(
            'SELECT p.name AS product_name, pv.size, pv.color, pv.color_hex, p.slug, SUM(oi.quantity) AS total_qty
             FROM order_items oi
             JOIN orders o ON o.id = oi.order_id
             JOIN product_variants pv ON pv.id = oi.variant_id
             JOIN products p ON p.id = pv.product_id
             WHERE o.antenna_id = :antenna_id
             GROUP BY p.id, pv.id, p.name, pv.size, pv.color, pv.color_hex, p.slug
             ORDER BY total_qty DESC
             LIMIT 5',
            ['antenna_id' => $antenna->getId()]
        );

        // Monthly orders count — last 6 months
        $now = new \DateTimeImmutable();
        $monthly = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = $now->modify("first day of -$i month")->setTime(0, 0);
            $monthEnd = $monthStart->modify('last day of this month')->setTime(23, 59, 59);
            $count = 0;
            $qty = 0;
            foreach ($allOrders as $o) {
                $ts = $o->getPlacedAt() ?? $o->getCreatedAt();
                if ($ts >= $monthStart && $ts <= $monthEnd) {
                    $count++;
                    $qty += $o->getTotalQuantity();
                }
            }
            $monthly[] = [
                'label' => $monthStart->format('M y'),
                'count' => $count,
                'qty' => $qty,
            ];
        }
        $maxMonthly = max(array_map(fn($m) => $m['count'], $monthly)) ?: 1;

        // Comparison: average orders across other antennas of same company
        $siblingAntennas = array_values(array_filter(
            $antennas->findBy(['company' => $antenna->getCompany()]),
            fn($a) => $a->getId() !== $antenna->getId()
        ));
        $siblingOrdersAvg = 0;
        if (!empty($siblingAntennas)) {
            $siblingTotal = 0;
            foreach ($siblingAntennas as $sibling) {
                $siblingTotal += count($orders->findBy(['antenna' => $sibling]));
            }
            $siblingOrdersAvg = round($siblingTotal / count($siblingAntennas), 1);
        }

        return $this->render('antenna/detail.html.twig', [
            'antenna' => $antenna,
            'orders' => $filteredOrders,
            'last_order' => $allOrders[0] ?? null,
            'top_products' => $topProducts,
            'monthly' => $monthly,
            'max_monthly' => $maxMonthly,
            'sibling_count' => count($siblingAntennas),
            'sibling_orders_avg' => $siblingOrdersAvg,
            'stats' => [
                'orders' => count($allOrders),
                'total_qty' => $totalQty,
                'total_cents' => $totalCents,
            ],
            'statuses' => OrderStatus::cases(),
            'current_status' => $statusFilter,
            'current_from' => $fromDate,
            'current_to' => $toDate,
        ]);
    }

    #[Route('/{id}/export.csv', name: 'app_antenna_export', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function export(Antenna $antenna, OrderRepository $orders, OrderExporter $exporter): StreamedResponse
    {
        $this->assertOwns($antenna);
        $antennaOrders = $orders->findBy(['antenna' => $antenna], ['createdAt' => 'DESC']);
        $filename = sprintf('commandes-%s-%s.csv', $antenna->getName(), (new \DateTimeImmutable())->format('Y-m-d'));
        $filename = preg_replace('/[^a-z0-9._-]+/i', '-', $filename);
        return $exporter->toCsv($antennaOrders, $filename);
    }

    #[Route('/{id}/commander', name: 'app_antenna_order', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function startOrder(Antenna $antenna, Request $request): RedirectResponse
    {
        $this->assertOwns($antenna);
        $request->getSession()->set('preselected_antenna_id', $antenna->getId());
        $this->addFlash('success', sprintf('Vous préparez une commande pour %s. Choisissez les produits puis validez.', $antenna->getName()));
        return $this->redirectToRoute('app_catalogue');
    }

    #[Route('/{id}/edit', name: 'app_antenna_edit')]
    public function edit(Antenna $antenna, Request $request, EntityManagerInterface $em): Response
    {
        $this->assertOwns($antenna);
        return $this->handleForm($antenna, $request, $em);
    }

    #[Route('/{id}/delete', name: 'app_antenna_delete', methods: ['POST'])]
    public function delete(Antenna $antenna, EntityManagerInterface $em): RedirectResponse
    {
        $this->assertOwns($antenna);
        $em->remove($antenna);
        $em->flush();
        $this->addFlash('success', sprintf('Antenne "%s" supprimée.', $antenna->getName()));
        return $this->redirectToRoute('app_antennas');
    }

    private function handleForm(Antenna $antenna, Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $isNew = $antenna->getId() === null;
        $errors = [];

        if ($request->isMethod('POST')) {
            $data = $this->extractFormData($request);
            $errors = $this->validateFormData($data);

            if (empty($errors)) {
                $antenna
                    ->setName($data['name'])
                    ->setAddressLine($data['address'])
                    ->setPostalCode($data['postalCode'])
                    ->setCity($data['city'])
                    ->setPhone($data['phone'] ?: null)
                    ->setNotes($data['notes'] ?: null)
                    ->setContactName($data['contactName'] ?: null)
                    ->setContactEmail($data['contactEmail'] ?: null)
                    ->setContactPhone($data['contactPhone'] ?: null);
                if ($isNew) {
                    $antenna->setCompany($user->getCompany());
                    $em->persist($antenna);
                }
                $em->flush();
                $this->addFlash('success', $isNew ? 'Antenne créée.' : 'Antenne mise à jour.');
                return $this->redirectToRoute('app_antennas');
            }
        }

        return $this->render('antenna/form.html.twig', [
            'antenna' => $antenna,
            'is_new' => $isNew,
            'errors' => $errors,
        ]);
    }

    /** @return array<string,string> */
    private function extractFormData(Request $request): array
    {
        return [
            'name' => trim((string) $request->request->get('name', '')),
            'address' => trim((string) $request->request->get('address_line', '')),
            'postalCode' => trim((string) $request->request->get('postal_code', '')),
            'city' => trim((string) $request->request->get('city', '')),
            'phone' => trim((string) $request->request->get('phone', '')),
            'notes' => trim((string) $request->request->get('notes', '')),
            'contactName' => trim((string) $request->request->get('contact_name', '')),
            'contactEmail' => trim((string) $request->request->get('contact_email', '')),
            'contactPhone' => trim((string) $request->request->get('contact_phone', '')),
        ];
    }

    /**
     * @param array<string,string> $data
     * @return array<string,string>
     */
    private function validateFormData(array $data): array
    {
        $errors = [];
        if ($data['name'] === '') {
            $errors['name'] = 'Nom requis.';
        }
        if ($data['address'] === '') {
            $errors['address_line'] = 'Adresse requise.';
        }
        if ($data['postalCode'] === '') {
            $errors['postal_code'] = 'Code postal requis.';
        }
        if ($data['city'] === '') {
            $errors['city'] = 'Ville requise.';
        }
        if ($data['contactEmail'] !== '' && !filter_var($data['contactEmail'], FILTER_VALIDATE_EMAIL)) {
            $errors['contact_email'] = 'Email de contact invalide.';
        }
        return $errors;
    }

    private function assertOwns(Antenna $antenna): void
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($antenna->getCompany()->getId() !== $user->getCompany()?->getId()) {
            throw $this->createAccessDeniedException();
        }
    }
}
