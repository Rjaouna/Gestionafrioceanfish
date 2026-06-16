<?php

namespace App\Tests\Service;

use App\Entity\AppModule;
use App\Entity\User;
use App\Repository\AppModuleRepository;
use App\Repository\PasswordShareRepository;
use App\Repository\UserModuleAccessRepository;
use App\Repository\UserRepository;
use App\Service\ModuleAccessService;
use App\Service\SecurityAccessService;
use App\Service\UserManagementService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserManagementServiceTest extends TestCase
{
    public function testAdminCannotAssignAReservedRoleWhenCreatingAUser(): void
    {
        $admin = (new User())->setRoles(['ROLE_ADMIN']);
        $user = new User();

        $this->service()->create($user, 'password-long-enough', [], $admin, 'ROLE_SUPER_ADMIN');

        self::assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function testAdminCannotChangeAnExistingUserRole(): void
    {
        $admin = (new User())->setRoles(['ROLE_ADMIN']);
        $user = (new User())->setRoles(['ROLE_USER']);

        $this->service()->update($user, null, [], $admin, 'ROLE_ADMIN');

        self::assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function testSuperAdminCanChangeAUserRole(): void
    {
        $superAdmin = (new User())->setRoles(['ROLE_SUPER_ADMIN']);
        $user = (new User())->setRoles(['ROLE_USER']);

        $this->service()->update($user, null, [], $superAdmin, 'ROLE_ADMIN');

        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $user->getRoles());
    }

    private function service(): UserManagementService
    {
        $module = (new AppModule())
            ->setName('Gestion des utilisateurs')
            ->setSlug('users')
            ->setRouteName('app_user_index');

        $moduleRepository = $this->createStub(AppModuleRepository::class);
        $moduleRepository->method('findOneBy')->willReturn($module);

        $moduleAccessRepository = $this->createStub(UserModuleAccessRepository::class);
        $moduleAccessRepository->method('hasAccess')->willReturn(true);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $securityAccess = new SecurityAccessService(
            $this->createStub(PasswordShareRepository::class),
            $moduleRepository,
            $moduleAccessRepository,
        );
        $moduleAccess = new ModuleAccessService(
            $moduleRepository,
            $moduleAccessRepository,
            $entityManager,
            $securityAccess,
        );
        $passwordHasher = $this->createStub(UserPasswordHasherInterface::class);
        $passwordHasher->method('hashPassword')->willReturn('hashed-password');

        return new UserManagementService(
            $this->createStub(UserRepository::class),
            $entityManager,
            $passwordHasher,
            $moduleAccess,
            $securityAccess,
        );
    }
}
