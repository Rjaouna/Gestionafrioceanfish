<?php

namespace App\Repository;

use App\Entity\PasswordRevealLink;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PasswordRevealLink> */
class PasswordRevealLinkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PasswordRevealLink::class);
    }

    public function findOneByRawToken(string $token): ?PasswordRevealLink
    {
        return $this->findOneBy(['tokenHash' => hash('sha256', $token)]);
    }
}
