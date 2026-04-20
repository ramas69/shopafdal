<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\OrderEvent;
use App\Entity\User;
use App\Enum\OrderStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class OrderEventLogger
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
    ) {}

    public function logCreated(Order $order): void
    {
        $this->log($order, OrderEvent::TYPE_CREATED, sprintf(
            'Commande créée · %d pièces · %s',
            $order->getTotalQuantity(),
            $this->formatPrice($order->getTotalCents()),
        ));
    }

    public function logStatusChanged(Order $order, OrderStatus $from, OrderStatus $to): void
    {
        $this->log(
            $order,
            $to === OrderStatus::CANCELLED ? OrderEvent::TYPE_CANCELLED : OrderEvent::TYPE_STATUS_CHANGED,
            sprintf('Statut : %s → %s', $from->label(), $to->label()),
            ['from' => $from->value, 'to' => $to->value],
        );
    }

    /**
     * @param array<int, array{label:string, qty:int}> $added
     * @param array<int, array{label:string, qty:int}> $removed
     * @param array<int, array{label:string, from:int, to:int}> $changed
     */
    public function logItemsEdited(Order $order, array $added, array $removed, array $changed): void
    {
        $parts = [];
        if ($added) {
            $parts[] = count($added) . ' ajoutée(s)';
        }
        if ($removed) {
            $parts[] = count($removed) . ' retirée(s)';
        }
        if ($changed) {
            $parts[] = count($changed) . ' quantité(s) modifiée(s)';
        }
        if (empty($parts)) {
            return;
        }

        $this->log(
            $order,
            OrderEvent::TYPE_ITEMS_EDITED,
            sprintf(
                'Articles modifiés · %s · nouveau total %s',
                implode(' · ', $parts),
                $this->formatPrice($order->getTotalCents()),
            ),
            ['added' => $added, 'removed' => $removed, 'changed' => $changed],
        );
    }

    public function logAntennaChanged(Order $order, string $fromName, string $toName): void
    {
        if ($fromName === $toName) {
            return;
        }
        $this->log(
            $order,
            OrderEvent::TYPE_ANTENNA_CHANGED,
            sprintf('Antenne livraison : %s → %s', $fromName, $toName),
            ['from' => $fromName, 'to' => $toName],
        );
    }

    public function logNotesUpdated(Order $order, ?string $from, ?string $to): void
    {
        if (($from ?? '') === ($to ?? '')) {
            return;
        }
        $this->log(
            $order,
            OrderEvent::TYPE_NOTES_UPDATED,
            $to ? 'Notes client mises à jour' : 'Notes client supprimées',
            ['from' => $from, 'to' => $to],
        );
    }

    public function logShippingUpdated(Order $order, ?string $carrier, ?string $tracking, ?\DateTimeImmutable $eta): void
    {
        $parts = [];
        if ($carrier) {
            $parts[] = $carrier;
        }
        if ($tracking) {
            $parts[] = 'n° ' . $tracking;
        }
        if ($eta) {
            $parts[] = 'ETA ' . $eta->format('d/m/Y');
        }
        $this->log(
            $order,
            OrderEvent::TYPE_SHIPPING_UPDATED,
            'Livraison : ' . (empty($parts) ? 'détails effacés' : implode(' · ', $parts)),
            ['carrier' => $carrier, 'tracking' => $tracking, 'eta' => $eta?->format('Y-m-d')],
        );
    }

    public function logBatUploaded(Order $order, string $itemLabel, int $version): void
    {
        $this->log(
            $order,
            OrderEvent::TYPE_BAT_UPLOADED,
            sprintf('BAT v%d téléversé · %s', $version, $itemLabel),
            ['item' => $itemLabel, 'version' => $version],
        );
    }

    public function logBatApproved(Order $order, string $itemLabel, int $version): void
    {
        $this->log(
            $order,
            OrderEvent::TYPE_BAT_APPROVED,
            sprintf('BAT v%d validé · %s', $version, $itemLabel),
            ['item' => $itemLabel, 'version' => $version],
        );
    }

    public function logBatRejected(Order $order, string $itemLabel, int $version, ?string $feedback): void
    {
        $this->log(
            $order,
            OrderEvent::TYPE_BAT_REJECTED,
            sprintf('BAT v%d refusé · %s', $version, $itemLabel),
            ['item' => $itemLabel, 'version' => $version, 'feedback' => $feedback],
        );
    }

    public function logAdminNote(Order $order, string $note): void
    {
        $this->log(
            $order,
            OrderEvent::TYPE_ADMIN_NOTE,
            'Note admin ajoutée',
            ['note' => $note],
        );
    }

    private function log(Order $order, string $type, string $summary, ?array $data = null): void
    {
        $actor = $this->security->getUser();
        $event = (new OrderEvent())
            ->setOrder($order)
            ->setActor($actor instanceof User ? $actor : null)
            ->setType($type)
            ->setSummary($summary)
            ->setData($data);
        $this->em->persist($event);
        // flush is intentionally NOT called here — let the caller flush with the business change
    }

    private function formatPrice(int $cents): string
    {
        return number_format($cents / 100, 2, ',', ' ') . ' €';
    }
}
