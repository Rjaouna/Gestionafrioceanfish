<?php

namespace App\Repository;

use App\Entity\InterimWorkerDocument;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<InterimWorkerDocument> */
class InterimWorkerDocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InterimWorkerDocument::class);
    }
}
