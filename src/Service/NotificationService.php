<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

final class NotificationService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $users,
    ) {}

    public function notify(
        User $recipient,
        string $title,
        ?string $message = null,
        ?string $linkUrl = null,
        string $type = Notification::TYPE_INFO,
    ): Notification {
        $notification = (new Notification())
            ->setRecipient($recipient)
            ->setTitle($title)
            ->setMessage($message)
            ->setLinkUrl($linkUrl)
            ->setType($type);
        $this->em->persist($notification);
        $this->em->flush();
        return $notification;
    }

    /** Notify all active Afdal admins. */
    public function notifyAdmins(
        string $title,
        ?string $message = null,
        ?string $linkUrl = null,
        string $type = Notification::TYPE_INFO,
    ): void {
        $admins = $this->users->findBy(['role' => UserRole::ADMIN, 'active' => true]);
        foreach ($admins as $admin) {
            $n = (new Notification())
                ->setRecipient($admin)
                ->setTitle($title)
                ->setMessage($message)
                ->setLinkUrl($linkUrl)
                ->setType($type);
            $this->em->persist($n);
        }
        $this->em->flush();
    }
}
