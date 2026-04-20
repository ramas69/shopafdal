<?php

namespace App\Service;

use App\Entity\Order;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class OrderExporter
{
    public function __construct(private Connection $connection) {}

    public function toCsvRevenueByCompany(\DateTimeImmutable $from, \DateTimeImmutable $to, string $filename): StreamedResponse
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT c.name AS company, COUNT(DISTINCT o.id) AS orders_count,
                    COALESCE(SUM(oi.unit_price_cents * oi.quantity), 0) AS revenue_cents
             FROM companies c
             JOIN orders o ON o.company_id = c.id
             JOIN order_items oi ON oi.order_id = o.id
             WHERE o.created_at >= :from AND o.created_at < :to AND o.status != :cancelled
             GROUP BY c.id, c.name
             ORDER BY revenue_cents DESC',
            ['from' => $from->format('Y-m-d H:i:s'), 'to' => $to->format('Y-m-d H:i:s'), 'cancelled' => 'cancelled'],
        );
        return $this->stream($filename, function ($out) use ($rows, $from, $to) {
            fputcsv($out, ['Période du', $from->format('d/m/Y'), 'au', $to->format('d/m/Y')], ';');
            fputcsv($out, ['Entreprise', 'Nombre de commandes', 'CA HT (€)'], ';');
            $grandTotal = 0;
            foreach ($rows as $r) {
                fputcsv($out, [$r['company'], (int) $r['orders_count'], number_format(((int) $r['revenue_cents']) / 100, 2, ',', '')], ';');
                $grandTotal += (int) $r['revenue_cents'];
            }
            fputcsv($out, ['TOTAL', '', number_format($grandTotal / 100, 2, ',', '')], ';');
        });
    }

    public function toCsvCompanies(string $filename): StreamedResponse
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT c.id, c.name, c.city, c.siren,
                    COUNT(DISTINCT u.id) AS members,
                    COUNT(DISTINCT a.id) AS antennas,
                    COUNT(DISTINCT o.id) AS orders_total,
                    COALESCE(SUM(CASE WHEN o.status != \'cancelled\' THEN oi.unit_price_cents * oi.quantity ELSE 0 END), 0) AS revenue_cents
             FROM companies c
             LEFT JOIN users u ON u.company_id = c.id AND u.active = TRUE
             LEFT JOIN antennas a ON a.company_id = c.id
             LEFT JOIN orders o ON o.company_id = c.id
             LEFT JOIN order_items oi ON oi.order_id = o.id
             GROUP BY c.id, c.name, c.city, c.siren
             ORDER BY c.name ASC'
        );
        return $this->stream($filename, function ($out) use ($rows) {
            fputcsv($out, ['Entreprise', 'Ville', 'SIREN', 'Membres actifs', 'Antennes', 'Commandes', 'CA HT total (€)'], ';');
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r['name'], $r['city'] ?? '', $r['siren'] ?? '',
                    (int) $r['members'], (int) $r['antennas'], (int) $r['orders_total'],
                    number_format(((int) $r['revenue_cents']) / 100, 2, ',', ''),
                ], ';');
            }
        });
    }

    private function stream(string $filename, callable $writer): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($writer) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            $writer($out);
            fclose($out);
        });
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename));
        return $response;
    }

    /**
     * Build a CSV response (Excel-friendly: UTF-8 BOM, semicolon separator, French numbers).
     *
     * @param iterable<Order> $orders
     */
    public function toCsv(iterable $orders, string $filename): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($orders) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM for Excel
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Référence', 'Date', 'Statut', 'Antenne', 'Ville',
                'SKU', 'Produit', 'Catégorie', 'Couleur', 'Taille',
                'Quantité', 'Prix HT unitaire', 'Total HT ligne',
                'Marquage zone', 'Marquage taille', 'Notes commande',
            ], ';');

            foreach ($orders as $order) {
                foreach ($order->getItems() as $item) {
                    $variant = $item->getVariant();
                    fputcsv($out, [
                        $order->getReference(),
                        ($order->getPlacedAt() ?? $order->getCreatedAt())->format('d/m/Y'),
                        $order->getStatus()->label(),
                        $order->getAntenna()->getName(),
                        $order->getAntenna()->getCity(),
                        $variant->getSku(),
                        $variant->getProduct()->getName(),
                        $variant->getProduct()->getCategory() ?? '',
                        $variant->getColor(),
                        $variant->getSize(),
                        $item->getQuantity(),
                        number_format($item->getUnitPriceCents() / 100, 2, ',', ''),
                        number_format($item->getQuantity() * $item->getUnitPriceCents() / 100, 2, ',', ''),
                        $item->getMarking()['zone'] ?? '',
                        $item->getMarking()['size'] ?? '',
                        $order->getNotes() ?? '',
                    ], ';');
                }
            }
            fclose($out);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $disposition = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
        $response->headers->set('Content-Disposition', $disposition);
        return $response;
    }
}
