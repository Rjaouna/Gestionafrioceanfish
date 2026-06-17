<?php

namespace App\Service\Maintenance;

use App\Entity\Intervenant;
use App\Entity\Intervention;
use App\Entity\InterventionIntervenant;
use App\Entity\User;
use App\Repository\IntervenantRepository;
use App\Repository\InterventionIntervenantRepository;
use App\Repository\InterventionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class InterventionService
{
    public function __construct(
        private InterventionRepository $repository,
        private IntervenantRepository $intervenantRepository,
        private InterventionIntervenantRepository $assignmentRepository,
        private EntityManagerInterface $entityManager,
        private MaintenanceAccessService $access,
        private InterventionHistoryService $history,
    ) {
    }

    /** @return list<Intervention> */
    public function search(string $query, ?string $status, User $actor): array
    {
        $this->assertAccess($actor);

        return $this->repository->search($query, $status);
    }

    public function nextReference(): string
    {
        $today = new \DateTimeImmutable('today');
        $prefix = 'INT-'.$today->format('Ymd');
        $count = (int) $this->repository->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.reference LIKE :prefix')
            ->setParameter('prefix', $prefix.'-%')
            ->getQuery()
            ->getSingleScalarResult();

        return sprintf('%s-%03d', $prefix, $count + 1);
    }

    public function create(Intervention $intervention, User $actor): Intervention
    {
        if (!$this->access->canCreate($actor)) {
            throw new AccessDeniedException();
        }

        $this->prepareIntervention($intervention);
        $intervention
            ->setCreatedBy($actor)
            ->setIsActive(true);
        $this->entityManager->persist($intervention);
        $this->ensurePrimaryAssignment($intervention, $actor);
        $this->history->add($intervention, 'Création de l’intervention', $actor, null, $intervention->getStatus());
        $this->entityManager->flush();

        return $intervention;
    }

    public function update(Intervention $intervention, User $actor): Intervention
    {
        if (!$this->access->canEdit($actor)) {
            throw new AccessDeniedException();
        }

        $this->prepareIntervention($intervention);
        $this->ensurePrimaryAssignment($intervention, $actor);
        $this->entityManager->flush();

        return $intervention;
    }

    public function start(Intervention $intervention, User $actor): void
    {
        if (!$this->access->canChangeStatus($actor)) {
            throw new AccessDeniedException();
        }

        if ($intervention->getStatus() === 'terminee') {
            throw new \DomainException('Cette intervention est déjà terminée.');
        }

        $oldStatus = $intervention->getStatus();
        $now = new \DateTimeImmutable();
        $intervention
            ->setStartedAt($intervention->getStartedAt() ?? $now)
            ->setStatus('en_cours');

        $this->history->add($intervention, 'Démarrage de l’intervention', $actor, $oldStatus, 'en_cours');
        $this->entityManager->flush();
    }

    public function changeStatus(Intervention $intervention, string $status, User $actor, ?string $comment = null): void
    {
        if (!$this->access->canChangeStatus($actor)) {
            throw new AccessDeniedException();
        }

        if (!in_array($status, Intervention::STATUSES, true)) {
            throw new \DomainException('Statut d’intervention invalide.');
        }

        $oldStatus = $intervention->getStatus();
        $intervention->setStatus($status);
        if ($status === 'en_cours' && !$intervention->getStartedAt()) {
            $intervention->setStartedAt(new \DateTimeImmutable());
        }
        if ($status === 'terminee' && !$intervention->getEndedAt()) {
            $intervention->setEndedAt(new \DateTimeImmutable());
        }

        $this->history->add($intervention, 'Changement de statut', $actor, $oldStatus, $status, $comment);
        $this->entityManager->flush();
    }

    public function close(Intervention $intervention, string $workDone, ?string $resultStatus, ?string $comment, User $actor): void
    {
        if (!$this->access->canChangeStatus($actor)) {
            throw new AccessDeniedException();
        }

        if (!in_array($resultStatus, Intervention::RESULT_STATUSES, true)) {
            throw new \DomainException('Sélectionnez le résultat de l’intervention.');
        }

        $oldStatus = $intervention->getStatus();
        $now = new \DateTimeImmutable();
        $intervention
            ->setWorkDone($workDone)
            ->setResultStatus($resultStatus)
            ->setStatus('terminee')
            ->setStartedAt($intervention->getStartedAt() ?? $now)
            ->setEndedAt($now);
        $this->history->add($intervention, 'Fin de l’intervention', $actor, $oldStatus, 'terminee', $comment);
        $this->entityManager->flush();
    }

    public function assignIntervenant(Intervention $intervention, int $intervenantId, ?string $role, bool $main, User $actor): void
    {
        if (!$this->access->canAssignIntervenant($actor)) {
            throw new AccessDeniedException();
        }

        $intervenant = $this->intervenantRepository->find($intervenantId);
        if (!$intervenant instanceof Intervenant || !$intervenant->isActive()) {
            throw new \DomainException('Cet intervenant n’est pas disponible.');
        }

        $assignment = $this->assignmentRepository->findFor($intervention, $intervenant) ?? (new InterventionIntervenant())
            ->setIntervention($intervention)
            ->setIntervenant($intervenant)
            ->setCreatedBy($actor);
        $assignment
            ->setRoleOnIntervention($role)
            ->setIsMainIntervenant($main);

        $this->entityManager->persist($assignment);
        $this->history->add($intervention, 'Affectation d’un intervenant', $actor, null, null, $intervenant->getDisplayName());
        $this->entityManager->flush();
    }

    public function removeAssignment(InterventionIntervenant $assignment, User $actor): void
    {
        if (!$this->access->canDelete($actor)) {
            throw new AccessDeniedException();
        }

        $intervention = $assignment->getIntervention();
        $name = $assignment->getIntervenant()?->getDisplayName();
        $this->entityManager->remove($assignment);
        if ($intervention instanceof Intervention) {
            $this->history->add($intervention, 'Retrait d’un intervenant', $actor, null, null, $name);
        }
        $this->entityManager->flush();
    }

    public function archive(Intervention $intervention, User $actor): bool
    {
        if (!$this->access->canArchive($actor)) {
            throw new AccessDeniedException();
        }

        $intervention->setIsActive(!$intervention->isActive());
        $this->entityManager->flush();

        return $intervention->isActive();
    }

    public function delete(Intervention $intervention, User $actor): void
    {
        if (!$this->access->canDelete($actor)) {
            throw new AccessDeniedException();
        }

        $this->entityManager->remove($intervention);
        $this->entityManager->flush();
    }

    private function assertAccess(User $actor): void
    {
        if (!$this->access->canAccess($actor)) {
            throw new AccessDeniedException();
        }
    }

    private function prepareIntervention(Intervention $intervention): void
    {
        if (!$intervention->getReference()) {
            $intervention->setReference($this->nextReference());
        }

        if ($intervention->getIntervenant() === null && $intervention->getContract()?->getIntervenant() instanceof Intervenant) {
            $intervention->setIntervenant($intervention->getContract()->getIntervenant());
        }

        $intervenant = $intervention->getIntervenant();
        $contractIntervenant = $intervention->getContract()?->getIntervenant();
        if ($intervenant instanceof Intervenant && $contractIntervenant instanceof Intervenant && $intervenant->getId() !== $contractIntervenant->getId()) {
            throw new \DomainException('Le contrat sélectionné ne correspond pas à cet intervenant.');
        }

        if ($intervenant instanceof Intervenant) {
            $intervention
                ->setCustomerName($intervenant->getDisplayName())
                ->setCustomerEmail($intervenant->getEmail())
                ->setCustomerPhone($intervenant->getPhone());
        }

        if ($intervention->getStatus() === 'a_planifier' && $intervention->getPlannedAt() !== null) {
            $intervention->setStatus('planifiee');
        }
    }

    private function ensurePrimaryAssignment(Intervention $intervention, User $actor): void
    {
        $intervenant = $intervention->getIntervenant();
        if (!$intervenant instanceof Intervenant) {
            return;
        }

        foreach ($intervention->getAssignments() as $assignment) {
            if ($assignment->getIntervenant()?->getId() === $intervenant->getId()) {
                $assignment->setIsMainIntervenant(true);

                return;
            }
        }

        $assignment = (new InterventionIntervenant())
            ->setIntervention($intervention)
            ->setIntervenant($intervenant)
            ->setRoleOnIntervention('Intervenant principal')
            ->setIsMainIntervenant(true)
            ->setCreatedBy($actor);
        $intervention->addAssignment($assignment);
        $this->entityManager->persist($assignment);
    }
}
