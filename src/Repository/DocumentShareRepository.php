<?php

namespace App\Repository;

use App\Entity\Document;
use App\Entity\DocumentShare;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<DocumentShare> */
class DocumentShareRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentShare::class);
    }

    public function findFor(Document $document, User $user): ?DocumentShare
    {
        return $this->findOneBy(['document' => $document, 'user' => $user]);
    }

    /** @return list<DocumentShare> */
    public function findActiveFor(Document $document): array
    {
        return $this->createQueryBuilder('s')
            ->innerJoin('s.user', 'u')
            ->addSelect('u')
            ->andWhere('s.document = :document')
            ->andWhere('s.isActive = true')
            ->setParameter('document', $document)
            ->orderBy('u.firstName', 'ASC')
            ->addOrderBy('u.lastName', 'ASC')
            ->addOrderBy('u.email', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
