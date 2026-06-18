<?php

namespace App\Service\Trash;

use App\Entity\Appointment;
use App\Entity\Contact;
use App\Entity\Document;
use App\Entity\Expense;
use App\Entity\InventoryItem;
use App\Entity\Intervenant;
use App\Entity\Intervention;
use App\Entity\MaintenanceContract;
use App\Entity\PasswordEntry;
use App\Entity\User;
use App\Service\DocumentStorageService;
use App\Service\Expense\ExpenseDocumentService;
use App\Service\Inventory\InventoryFileService;
use App\Service\Maintenance\MaintenanceShareService;
use App\Service\SecurityAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class TrashService
{
    /** @var array<string, array{class: class-string, label: string, module: string, icon: string}> */
    private const TRASHABLES = [
        'contact' => ['class' => Contact::class, 'label' => 'Contact', 'module' => 'Carnet de contacts', 'icon' => 'bi-person-lines-fill'],
        'document' => ['class' => Document::class, 'label' => 'Document', 'module' => 'Gestion des documents', 'icon' => 'bi-file-earmark-text'],
        'password' => ['class' => PasswordEntry::class, 'label' => 'Mot de passe', 'module' => 'Coffre de mots de passe', 'icon' => 'bi-key'],
        'expense' => ['class' => Expense::class, 'label' => 'Dépense', 'module' => 'Dépenses', 'icon' => 'bi-cash-coin'],
        'maintenance-contract' => ['class' => MaintenanceContract::class, 'label' => 'Contrat de maintenance', 'module' => 'Maintenance', 'icon' => 'bi-clipboard-check'],
        'intervention' => ['class' => Intervention::class, 'label' => 'Intervention', 'module' => 'Maintenance', 'icon' => 'bi-tools'],
        'intervenant' => ['class' => Intervenant::class, 'label' => 'Intervenant', 'module' => 'Maintenance', 'icon' => 'bi-person-gear'],
        'appointment' => ['class' => Appointment::class, 'label' => 'Rendez-vous', 'module' => 'Agenda - RDV', 'icon' => 'bi-calendar-check'],
        'inventory-item' => ['class' => InventoryItem::class, 'label' => 'Materiel', 'module' => 'Inventaire', 'icon' => 'bi-box-seam'],
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private SecurityAccessService $access,
        private DocumentStorageService $documentStorage,
        private ExpenseDocumentService $expenseDocumentService,
        private MaintenanceShareService $maintenanceShareService,
        private InventoryFileService $inventoryFileService,
    ) {
    }

    public function moveToTrash(object $entity, User $user, ?string $reason = null): void
    {
        if (!$this->isTrashable($entity)) {
            throw new \InvalidArgumentException('Cet élément ne peut pas être déplacé dans la corbeille.');
        }

        $entity->setIsDeleted(true);
        $entity->setDeletedAt(new \DateTimeImmutable());
        $entity->setDeletedBy($user);
        $entity->setDeleteReason($reason);

        if (method_exists($entity, 'setIsActive')) {
            $entity->setIsActive(false);
        }

        $this->entityManager->flush();
    }

    public function restore(object $entity, User $user): void
    {
        $this->assertSuperAdmin($user);
        if (!$this->isTrashable($entity)) {
            throw new \InvalidArgumentException('Cet élément ne peut pas être restauré.');
        }

        $entity->setIsDeleted(false);
        $entity->setDeletedAt(null);
        $entity->setDeletedBy(null);
        $entity->setDeleteReason(null);

        if (method_exists($entity, 'setIsActive')) {
            $entity->setIsActive(true);
        }

        $this->entityManager->flush();
    }

    public function deletePermanently(object $entity, User $user): void
    {
        $this->assertSuperAdmin($user);
        if (!$this->isTrashable($entity)) {
            throw new \InvalidArgumentException('Cet élément ne peut pas être supprimé définitivement.');
        }

        if ($entity instanceof Document) {
            $this->documentStorage->delete($entity);
        }

        if ($entity instanceof Expense) {
            $this->expenseDocumentService->deleteFilesForExpense($entity);
        }

        if ($entity instanceof Intervenant || $entity instanceof MaintenanceContract || $entity instanceof Intervention) {
            $this->maintenanceShareService->removeSharesFor($entity);
        }

        if ($entity instanceof InventoryItem) {
            $this->inventoryFileService->deleteFilesForItem($entity);
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }

    public function isTrashable(object $entity): bool
    {
        return method_exists($entity, 'isDeleted')
            && method_exists($entity, 'setIsDeleted')
            && $this->typeForEntity($entity) !== null;
    }

    /** @return array<string, array{class: class-string, label: string, module: string, icon: string}> */
    public function getTrashableEntities(): array
    {
        return self::TRASHABLES;
    }

    /**
     * @param array{type?: string, module?: string, deletedBy?: string|int, dateFrom?: string, dateTo?: string} $filters
     *
     * @return list<array{type: string, typeLabel: string, module: string, icon: string, entity: object, id: int|null, title: string, deletedAt: ?\DateTimeImmutable, deletedBy: ?User, reason: ?string}>
     */
    public function findDeletedItems(string $query = '', array $filters = []): array
    {
        $items = [];
        $query = $this->normalize($query);
        foreach (self::TRASHABLES as $type => $config) {
            if (($filters['type'] ?? '') !== '' && ($filters['type'] ?? '') !== $type) {
                continue;
            }

            if (($filters['module'] ?? '') !== '' && ($filters['module'] ?? '') !== $config['module']) {
                continue;
            }

            $builder = $this->entityManager->getRepository($config['class'])->createQueryBuilder('e')
                ->leftJoin('e.deletedBy', 'deletedBy')
                ->addSelect('deletedBy')
                ->andWhere('e.isDeleted = true');

            if (($filters['deletedBy'] ?? '') !== '') {
                $builder
                    ->andWhere('deletedBy.id = :deletedById')
                    ->setParameter('deletedById', (int) $filters['deletedBy']);
            }

            if (($filters['dateFrom'] ?? '') !== '') {
                $builder
                    ->andWhere('e.deletedAt >= :dateFrom')
                    ->setParameter('dateFrom', new \DateTimeImmutable((string) $filters['dateFrom'].' 00:00:00'));
            }

            if (($filters['dateTo'] ?? '') !== '') {
                $builder
                    ->andWhere('e.deletedAt <= :dateTo')
                    ->setParameter('dateTo', new \DateTimeImmutable((string) $filters['dateTo'].' 23:59:59'));
            }

            foreach ($builder->getQuery()->getResult() as $entity) {
                $title = $this->titleFor($entity);
                $haystack = $this->normalize($title.' '.$config['label'].' '.$config['module'].' '.($entity->getDeletedBy()?->getDisplayName() ?? '').' '.($entity->getDeleteReason() ?? ''));
                if ($query !== '' && !str_contains($haystack, $query)) {
                    continue;
                }

                $items[] = [
                    'type' => $type,
                    'typeLabel' => $config['label'],
                    'module' => $config['module'],
                    'icon' => $config['icon'],
                    'entity' => $entity,
                    'id' => $entity->getId(),
                    'title' => $title,
                    'deletedAt' => $entity->getDeletedAt(),
                    'deletedBy' => $entity->getDeletedBy(),
                    'reason' => $entity->getDeleteReason(),
                ];
            }
        }

        usort($items, static function (array $first, array $second): int {
            return ($second['deletedAt']?->getTimestamp() ?? 0) <=> ($first['deletedAt']?->getTimestamp() ?? 0);
        });

        return $items;
    }

    /** @param array{type?: string, module?: string, deletedBy?: string|int, dateFrom?: string, dateTo?: string} $filters */
    public function countDeletedItems(string $query = '', array $filters = []): int
    {
        return count($this->findDeletedItems($query, $filters));
    }

    public function findTrashItem(string $type, int $id): object
    {
        $config = self::TRASHABLES[$type] ?? null;
        if ($config === null) {
            throw new \InvalidArgumentException('Type de corbeille invalide.');
        }

        $entity = $this->entityManager->getRepository($config['class'])->find($id);
        if (!is_object($entity) || !method_exists($entity, 'isDeleted') || !$entity->isDeleted()) {
            throw new \DomainException('Cet élément est introuvable dans la corbeille.');
        }

        return $entity;
    }

    public function typeForEntity(object $entity): ?string
    {
        foreach (self::TRASHABLES as $type => $config) {
            if ($entity instanceof $config['class']) {
                return $type;
            }
        }

        return null;
    }

    public function titleFor(object $entity): string
    {
        return match (true) {
            $entity instanceof Contact => (string) $entity->getFullName(),
            $entity instanceof Document => (string) $entity->getName(),
            $entity instanceof PasswordEntry => (string) $entity->getName(),
            $entity instanceof Expense => (string) $entity->getTitle(),
            $entity instanceof MaintenanceContract => (string) ($entity->getReference().' - '.$entity->getCustomerName()),
            $entity instanceof Intervention => (string) ($entity->getReference().' - '.$entity->getTitle()),
            $entity instanceof Intervenant => (string) $entity->getDisplayName(),
            $entity instanceof Appointment => (string) ($entity->getReference().' - '.$entity->getTitle()),
            $entity instanceof InventoryItem => (string) ($entity->getReference().' - '.$entity->getName()),
            default => 'Élément supprimé',
        };
    }

    private function assertSuperAdmin(User $user): void
    {
        if (!$this->access->isSuperAdmin($user)) {
            throw new AccessDeniedException();
        }
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
