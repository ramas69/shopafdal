<?php

namespace App\Service;

use App\Entity\Order;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class OrderExporter
{
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
