<?php

namespace App\Tests\Service;

use App\Entity\PasswordEntry;
use App\Entity\PasswordShare;
use App\Entity\User;
use App\Repository\AppModuleRepository;
use App\Repository\PasswordShareRepository;
use App\Repository\UserModuleAccessRepository;
use App\Service\SecurityAccessService;
use PHPUnit\Framework\TestCase;

final class SecurityAccessServiceTest extends TestCase
{
    public function testRoleCapabilitiesAreCentralized(): void
    {
        $service = new SecurityAccessService(
            $this->createStub(PasswordShareRepository::class),
            $this->createStub(AppModuleRepository::class),
            $this->createStub(UserModuleAccessRepository::class),
        );
        $superAdmin = (new User())->setRoles(['ROLE_SUPER_ADMIN']);
        $admin = (new User())->setRoles(['ROLE_ADMIN']);
        $user = (new User())->setRoles(['ROLE_USER']);

        self::assertTrue($service->isSuperAdmin($superAdmin));
        self::assertTrue($service->isAdmin($superAdmin));
        self::assertTrue($service->isAdmin($admin));
        self::assertFalse($service->isAdmin($user));
        self::assertTrue($service->canDeletePasswords($superAdmin));
        self::assertTrue($service->canDeletePasswords($admin));
        self::assertFalse($service->canDeletePasswords($user));
    }

    public function testCreatorKeepsAccessToOwnPendingPassword(): void
    {
        $shareRepository = $this->createStub(PasswordShareRepository::class);
        $service = new SecurityAccessService(
            $shareRepository,
            $this->createStub(AppModuleRepository::class),
            $this->createStub(UserModuleAccessRepository::class),
        );
        $creator = $this->userWithId(10, ['ROLE_USER']);
        $entry = (new PasswordEntry())
            ->setName('Accès test')
            ->setLogin('creator@example.com')
            ->setEncryptedPassword('secret')
            ->setCreatedBy($creator)
            ->setIsValidated(false);

        self::assertTrue($service->canViewPassword($creator, $entry));
        self::assertTrue($service->canEditPasswordValue($creator, $entry));
        self::assertTrue($service->canTogglePasswordStatus($creator, $entry));
    }

    public function testOnlyAdminsCanSharePasswords(): void
    {
        $service = new SecurityAccessService(
            $this->createStub(PasswordShareRepository::class),
            $this->createStub(AppModuleRepository::class),
            $this->createStub(UserModuleAccessRepository::class),
        );
        $creator = $this->userWithId(12, ['ROLE_USER']);
        $admin = $this->userWithId(13, ['ROLE_ADMIN']);
        $superAdmin = $this->userWithId(14, ['ROLE_SUPER_ADMIN']);
        $entry = (new PasswordEntry())
            ->setName('Accès validé')
            ->setLogin('creator@example.com')
            ->setEncryptedPassword('secret')
            ->setCreatedBy($creator)
            ->setIsValidated(true)
            ->setIsActive(true);

        self::assertFalse($service->canSharePassword($creator, $entry));
        self::assertTrue($service->canSharePassword($admin, $entry));
        self::assertTrue($service->canSharePassword($superAdmin, $entry));
    }

    public function testSharedUserOnlySeesActiveValidatedPasswords(): void
    {
        $user = $this->userWithId(11, ['ROLE_USER']);
        $share = (new PasswordShare())->setUser($user)->setCanView(true)->setCanEditPassword(true);
        $shareRepository = $this->createStub(PasswordShareRepository::class);
        $shareRepository->method('findFor')->willReturn($share);
        $service = new SecurityAccessService(
            $shareRepository,
            $this->createStub(AppModuleRepository::class),
            $this->createStub(UserModuleAccessRepository::class),
        );
        $entry = (new PasswordEntry())
            ->setName('Accès validé')
            ->setLogin('user@example.com')
            ->setEncryptedPassword('secret')
            ->setIsValidated(true)
            ->setIsActive(true);

        self::assertTrue($service->canViewPassword($user, $entry));

        $entry->setIsValidated(false);
        self::assertFalse($service->canViewPassword($user, $entry));

        $entry->setIsValidated(true)->setIsActive(false);
        self::assertFalse($service->canViewPassword($user, $entry));
    }

    public function testAdminCanValidatePendingActivePasswords(): void
    {
        $service = new SecurityAccessService(
            $this->createStub(PasswordShareRepository::class),
            $this->createStub(AppModuleRepository::class),
            $this->createStub(UserModuleAccessRepository::class),
        );
        $admin = (new User())->setRoles(['ROLE_ADMIN']);
        $entry = (new PasswordEntry())
            ->setName('À valider')
            ->setLogin('admin@example.com')
            ->setEncryptedPassword('secret')
            ->setIsValidated(false)
            ->setIsActive(true);

        self::assertTrue($service->canValidatePassword($admin, $entry));

        $entry->setIsActive(false);
        self::assertFalse($service->canValidatePassword($admin, $entry));
    }

    /** @param list<string> $roles */
    private function userWithId(int $id, array $roles): User
    {
        $user = (new User())->setRoles($roles);
        $property = new \ReflectionProperty(User::class, 'id');
        $property->setValue($user, $id);

        return $user;
    }
}
