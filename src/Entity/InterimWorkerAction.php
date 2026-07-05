<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableUserTrait;
use App\Repository\InterimWorkerActionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InterimWorkerActionRepository::class)]
#[ORM\Table(name: 'interim_worker_action')]
#[ORM\Index(name: 'idx_interim_worker_action_worker', columns: ['worker_id'])]
#[ORM\Index(name: 'idx_interim_worker_action_type', columns: ['action_type'])]
#[ORM\Index(name: 'idx_interim_worker_action_action_at', columns: ['action_at'])]
#[ORM\Index(name: 'idx_interim_worker_action_performed_by', columns: ['performed_by_id'])]
#[ORM\Index(name: 'idx_interim_worker_action_created_by', columns: ['created_by_id'])]
#[ORM\Index(name: 'idx_interim_worker_action_updated_by', columns: ['updated_by_id'])]
class InterimWorkerAction
{
    use TimestampableUserTrait;

    public const TYPE_STATUS_CHANGE = 'status_change';
    public const TYPE_MISSION_END = 'mission_end';
    public const TYPE_DO_NOT_RECALL = 'do_not_recall';

    public const TYPE_LABELS = [
        self::TYPE_STATUS_CHANGE => 'Changement de statut',
        self::TYPE_MISSION_END => 'Fin de mission',
        self::TYPE_DO_NOT_RECALL => 'A ne pas rappeler',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: InterimWorker::class, inversedBy: 'actions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?InterimWorker $worker = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $performedBy = null;

    #[ORM\Column(length: 40)]
    #[Assert\NotBlank]
    private string $actionType = self::TYPE_STATUS_CHANGE;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $previousStatus = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $newStatus = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $actionAt;

    public function __construct()
    {
        $this->actionAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWorker(): ?InterimWorker
    {
        return $this->worker;
    }

    public function setWorker(?InterimWorker $worker): static
    {
        $this->worker = $worker;

        return $this;
    }

    public function getPerformedBy(): ?User
    {
        return $this->performedBy;
    }

    public function setPerformedBy(?User $performedBy): static
    {
        $this->performedBy = $performedBy;

        return $this;
    }

    public function getActionType(): string
    {
        return $this->actionType;
    }

    public function setActionType(string $actionType): static
    {
        if (!isset(self::TYPE_LABELS[$actionType])) {
            throw new \InvalidArgumentException('Type d action intérimaire invalide.');
        }

        $this->actionType = $actionType;

        return $this;
    }

    public function getActionTypeLabel(): string
    {
        return self::TYPE_LABELS[$this->actionType] ?? $this->actionType;
    }

    public function getPreviousStatus(): ?string
    {
        return $this->previousStatus;
    }

    public function setPreviousStatus(?string $previousStatus): static
    {
        $this->previousStatus = $previousStatus !== null && isset(InterimWorker::STATUS_LABELS[$previousStatus]) ? $previousStatus : null;

        return $this;
    }

    public function getPreviousStatusLabel(): ?string
    {
        return $this->previousStatus !== null ? InterimWorker::STATUS_LABELS[$this->previousStatus] ?? $this->previousStatus : null;
    }

    public function getNewStatus(): ?string
    {
        return $this->newStatus;
    }

    public function setNewStatus(?string $newStatus): static
    {
        $this->newStatus = $newStatus !== null && isset(InterimWorker::STATUS_LABELS[$newStatus]) ? $newStatus : null;

        return $this;
    }

    public function getNewStatusLabel(): ?string
    {
        return $this->newStatus !== null ? InterimWorker::STATUS_LABELS[$this->newStatus] ?? $this->newStatus : null;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): static
    {
        $reason = trim((string) $reason);
        $this->reason = $reason !== '' ? $reason : null;

        return $this;
    }

    public function getActionAt(): \DateTimeImmutable
    {
        return $this->actionAt;
    }

    public function setActionAt(\DateTimeImmutable $actionAt): static
    {
        $this->actionAt = $actionAt;

        return $this;
    }
}
