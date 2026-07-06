<?php

namespace App\Repository;

use App\Entity\FishReception;
use App\Entity\FishReceptionStorageMovement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<FishReceptionStorageMovement> */
class FishReceptionStorageMovementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FishReceptionStorageMovement::class);
    }

    /** @return list<FishReceptionStorageMovement> */
    public function forReception(FishReception $reception): array
    {
        return $this->createQueryBuilder('movement')
            ->leftJoin('movement.createdBy', 'creator')
            ->addSelect('creator')
            ->andWhere('movement.reception = :reception')
            ->setParameter('reception', $reception)
            ->orderBy('movement.movementDate', 'ASC')
            ->addOrderBy('movement.movementTime', 'ASC')
            ->addOrderBy('movement.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<array{location: string, quantity: string}> */
    public function currentInitialStockByStorageLocation(): array
    {
        return $this->createQueryBuilder('movement')
            ->select('movement.location AS location')
            ->addSelect('SUM(movement.quantityKg) AS quantity')
            ->join('movement.reception', 'reception')
            ->andWhere('movement.storageStage = :stage')
            ->andWhere('reception.isDeleted = false')
            ->groupBy('movement.location')
            ->having('SUM(movement.quantityKg) > 0.001')
            ->orderBy('quantity', 'DESC')
            ->setParameter('stage', FishReceptionStorageMovement::STAGE_INITIAL)
            ->getQuery()
            ->getArrayResult();
    }
}
