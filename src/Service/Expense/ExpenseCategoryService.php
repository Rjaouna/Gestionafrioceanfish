<?php

namespace App\Service\Expense;

use App\Entity\ExpenseCategory;
use App\Entity\User;
use App\Repository\ExpenseCategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\String\Slugger\SluggerInterface;

final readonly class ExpenseCategoryService
{
    public function __construct(
        private ExpenseCategoryRepository $repository,
        private EntityManagerInterface $entityManager,
        private ExpenseAccessService $access,
        private SluggerInterface $slugger,
    ) {
    }

    /** @return list<ExpenseCategory> */
    public function activeCategories(User $actor): array
    {
        $this->assertAccess($actor);

        return $this->repository->findActive();
    }

    /** @return list<ExpenseCategory> */
    public function search(User $actor, string $query = ''): array
    {
        if (!$this->access->canManageCategories($actor)) {
            throw new AccessDeniedException();
        }

        return $this->repository->search($query);
    }

    public function create(ExpenseCategory $category, User $actor): ExpenseCategory
    {
        if (!$this->access->canManageCategories($actor)) {
            throw new AccessDeniedException();
        }

        $this->prepare($category);
        $category->setCreatedBy($actor);
        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return $category;
    }

    public function findOrCreateFromName(string $name, User $actor): ?ExpenseCategory
    {
        $this->assertAccess($actor);

        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $baseSlug = (string) $this->slugger->slug($name)->lower();
        $baseSlug = $baseSlug !== '' ? $baseSlug : 'categorie';
        $slug = $baseSlug;
        $index = 2;

        while (($existing = $this->repository->findOneBy(['slug' => $slug])) instanceof ExpenseCategory) {
            if (mb_strtolower((string) $existing->getName()) === mb_strtolower($name)) {
                if (!$existing->isActive()) {
                    $existing->setIsActive(true);
                    $this->entityManager->flush();
                }

                return $existing;
            }

            $slug = sprintf('%s-%d', $baseSlug, $index);
            ++$index;
        }

        $category = (new ExpenseCategory())
            ->setName($name)
            ->setSlug($slug)
            ->setIcon('bi-receipt')
            ->setColor('secondary')
            ->setDescription('Catégorie créée depuis une dépense.')
            ->setCreatedBy($actor);

        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return $category;
    }

    public function update(ExpenseCategory $category, User $actor): ExpenseCategory
    {
        if (!$this->access->canManageCategories($actor)) {
            throw new AccessDeniedException();
        }

        $this->prepare($category);
        $this->entityManager->flush();

        return $category;
    }

    public function toggle(ExpenseCategory $category, User $actor): bool
    {
        if (!$this->access->canManageCategories($actor)) {
            throw new AccessDeniedException();
        }

        $category->setIsActive(!$category->isActive());
        $this->entityManager->flush();

        return $category->isActive();
    }

    private function assertAccess(User $actor): void
    {
        if (!$this->access->canAccess($actor)) {
            throw new AccessDeniedException();
        }
    }

    private function prepare(ExpenseCategory $category): void
    {
        if (!$category->getSlug()) {
            $category->setSlug((string) $this->slugger->slug((string) $category->getName())->lower());
        }
    }
}
