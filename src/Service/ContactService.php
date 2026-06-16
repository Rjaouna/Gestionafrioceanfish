<?php

namespace App\Service;

use App\Entity\Contact;
use App\Entity\User;
use App\Repository\ContactRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class ContactService
{
    public function __construct(
        private ContactRepository $repository,
        private EntityManagerInterface $entityManager,
        private SecurityAccessService $access,
        private ContactPermissionService $permission,
    ) {
    }

    /**
     * @param array{type?: string|null, city?: string|null} $filters
     *
     * @return list<Contact>
     */
    public function getVisibleContacts(User $user, array $filters = []): array
    {
        return $this->repository->findVisibleFor($user, $this->access->isAdmin($user), $filters);
    }

    /** @return list<string> */
    public function getTypeSuggestions(): array
    {
        return $this->repository->findDistinctTypes();
    }

    /** @return list<string> */
    public function getCitySuggestions(): array
    {
        return $this->repository->findDistinctCities();
    }

    public function create(Contact $contact, User $actor): Contact
    {
        if (!$this->permission->canCreate($actor)) {
            throw new AccessDeniedException();
        }

        $contact
            ->setIsActive(true)
            ->setCreatedBy($actor);

        $this->entityManager->persist($contact);
        $this->entityManager->flush();

        return $contact;
    }

    public function update(Contact $contact, User $actor): Contact
    {
        if (!$this->permission->canEdit($actor, $contact)) {
            throw new AccessDeniedException();
        }

        $this->entityManager->flush();

        return $contact;
    }

    public function delete(Contact $contact, User $actor): void
    {
        if (!$this->permission->canDelete($actor, $contact)) {
            throw new AccessDeniedException();
        }

        $this->entityManager->remove($contact);
        $this->entityManager->flush();
    }
}
