<?php

namespace App\Tests\Service;

use App\Entity\Contact;
use App\Entity\ContactShare;
use App\Entity\User;
use App\Repository\AppModuleRepository;
use App\Repository\ContactShareRepository;
use App\Repository\PasswordShareRepository;
use App\Repository\UserModuleAccessRepository;
use App\Service\ContactPermissionService;
use App\Service\SecurityAccessService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ContactPermissionServiceTest extends TestCase
{
    #[Test]
    public function itAllowsARecipientToViewAnActiveSharedContact(): void
    {
        $recipient = (new User())->setEmail('user@example.com')->setPassword('hash');
        $contact = (new Contact())
            ->setFullName('Garage Martin')
            ->setType('Dépannage')
            ->setIsActive(true);
        $share = (new ContactShare())
            ->setContact($contact)
            ->setUser($recipient)
            ->setCanView(true)
            ->setIsActive(true);

        $repository = $this->createMock(ContactShareRepository::class);
        $repository
            ->expects($this->once())
            ->method('findFor')
            ->with($contact, $recipient)
            ->willReturn($share);

        self::assertTrue($this->service($repository)->canView($recipient, $contact));
    }

    #[Test]
    public function itRejectsAnInactiveShare(): void
    {
        $recipient = (new User())->setEmail('user@example.com')->setPassword('hash');
        $contact = (new Contact())
            ->setFullName('Garage Martin')
            ->setType('Dépannage')
            ->setIsActive(true);
        $share = (new ContactShare())
            ->setContact($contact)
            ->setUser($recipient)
            ->setCanView(true)
            ->setIsActive(false);

        $repository = $this->createMock(ContactShareRepository::class);
        $repository
            ->expects($this->once())
            ->method('findFor')
            ->with($contact, $recipient)
            ->willReturn($share);

        self::assertFalse($this->service($repository)->canView($recipient, $contact));
    }

    #[Test]
    public function itAllowsASuperAdminToManageContacts(): void
    {
        $admin = (new User())
            ->setEmail('admin@example.com')
            ->setRoles(['ROLE_SUPER_ADMIN'])
            ->setPassword('hash');
        $contact = (new Contact())
            ->setFullName('Client Exemple')
            ->setType('Client')
            ->setIsActive(true);

        $service = $this->service($this->createStub(ContactShareRepository::class));

        self::assertTrue($service->canCreate($admin));
        self::assertTrue($service->canEdit($admin, $contact));
        self::assertTrue($service->canShare($admin, $contact));
        self::assertTrue($service->canDelete($admin, $contact));
    }

    private function service(ContactShareRepository $contactShareRepository): ContactPermissionService
    {
        return new ContactPermissionService(
            new SecurityAccessService(
                $this->createStub(PasswordShareRepository::class),
                $this->createStub(AppModuleRepository::class),
                $this->createStub(UserModuleAccessRepository::class),
            ),
            $contactShareRepository,
        );
    }
}
