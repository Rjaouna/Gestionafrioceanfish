<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Doctrine\Common\EventSubscriber;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::onFlush)]
final class EntityAuditSubscriber implements EventSubscriber
{
    public function __construct(private readonly Security $security)
    {
    }

    public function getSubscribedEvents(): array
    {
        return [Events::prePersist, Events::onFlush];
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$this->isAuditable($entity)) {
            return;
        }

        $now = new \DateTimeImmutable();
        $user = $this->currentUser();
        $entity->setCreatedAt($now);
        if ($user instanceof User || $entity->getCreatedBy() === null) {
            $entity->setCreatedBy($user);
        }
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $entityManager = $args->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();
        $now = new \DateTimeImmutable();
        $user = $this->currentUser();

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            if (!$this->isAuditable($entity)) {
                continue;
            }

            $entity->setUpdatedAt($now);
            $entity->setUpdatedBy($user);
            $metadata = $entityManager->getClassMetadata($entity::class);
            $unitOfWork->recomputeSingleEntityChangeSet($metadata, $entity);
        }
    }

    private function currentUser(): ?User
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user : null;
    }

    private function isAuditable(object $entity): bool
    {
        return method_exists($entity, 'setCreatedAt')
            && method_exists($entity, 'setCreatedBy')
            && method_exists($entity, 'setUpdatedAt')
            && method_exists($entity, 'setUpdatedBy');
    }
}
