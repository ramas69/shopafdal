<?php

namespace App\Repository;

use App\Entity\MailSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MailSettings>
 */
class MailSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MailSettings::class);
    }

    /**
     * Singleton : retourne la ligne unique ou la crée à la volée.
     */
    public function getOrCreate(EntityManagerInterface $em): MailSettings
    {
        $settings = $this->findOneBy([]);
        if (!$settings) {
            $settings = new MailSettings();
            $em->persist($settings);
            $em->flush();
        }
        return $settings;
    }
}
