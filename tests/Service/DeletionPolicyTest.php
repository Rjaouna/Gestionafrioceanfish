<?php

namespace App\Tests\Service;

use App\Entity\AppModule;
use App\Entity\Contact;
use App\Entity\ContactShare;
use App\Entity\Document;
use App\Entity\DocumentShare;
use App\Entity\Expense;
use App\Entity\ExpenseDocument;
use App\Entity\ExpenseShare;
use App\Entity\User;
use App\Repository\AppModuleRepository;
use App\Repository\ContactShareRepository;
use App\Repository\DocumentShareRepository;
use App\Repository\ExpenseShareRepository;
use App\Repository\PasswordShareRepository;
use App\Repository\UserModuleAccessRepository;
use App\Service\ContactPermissionService;
use App\Service\DocumentPermissionService;
use App\Service\Expense\ExpenseAccessService;
use App\Service\Maintenance\MaintenanceAccessService;
use App\Service\SecurityAccessService;
use PHPUnit\Framework\TestCase;

final class DeletionPolicyTest extends TestCase
{
    public function testNormalUsersCannotDeleteSharedBusinessObjects(): void
    {
        $user = $this->userWithId(1, ['ROLE_USER']);
        $security = $this->securityWithModuleAccess();

        $contact = (new Contact())
            ->setFullName('Client Test')
            ->setType('Client')
            ->setCreatedBy($user)
            ->setIsActive(true);
        $contactPermission = new ContactPermissionService($security, $this->createStub(ContactShareRepository::class));

        $document = (new Document())
            ->setName('Document Test')
            ->setFileName('file.pdf')
            ->setOriginalFileName('file.pdf')
            ->setMimeType('application/pdf')
            ->setCreatedBy($user);
        $documentPermission = new DocumentPermissionService($security, $this->createStub(DocumentShareRepository::class));

        $expense = (new Expense())
            ->setTitle('Depense Test')
            ->setReference('EXP-001')
            ->setSupplierName('Fournisseur')
            ->setCreatedBy($user);
        $expenseAccess = new ExpenseAccessService($security, $this->createStub(ExpenseShareRepository::class));
        $expenseDocument = (new ExpenseDocument())->setExpense($expense)->setIsActive(true);

        $maintenanceAccess = new MaintenanceAccessService($security);

        self::assertFalse($contactPermission->canDelete($user, $contact));
        self::assertFalse($documentPermission->canDelete($user, $document));
        self::assertFalse($expenseAccess->canDelete($user, $expense));
        self::assertFalse($expenseAccess->canDeleteDocument($user, $expenseDocument));
        self::assertFalse($maintenanceAccess->canDelete($user));
        self::assertFalse($security->canDeletePasswords($user));
    }

    public function testAdminsCanDeleteAndReclaimBusinessObjects(): void
    {
        $creator = $this->userWithId(1, ['ROLE_USER']);
        $admin = $this->userWithId(2, ['ROLE_ADMIN']);
        $security = $this->securityWithModuleAccess();

        $contact = (new Contact())
            ->setFullName('Client Test')
            ->setType('Client')
            ->setCreatedBy($creator)
            ->setIsActive(true);
        $contactPermission = new ContactPermissionService($security, $this->createStub(ContactShareRepository::class));

        $document = (new Document())
            ->setName('Document Test')
            ->setFileName('file.pdf')
            ->setOriginalFileName('file.pdf')
            ->setMimeType('application/pdf')
            ->setCreatedBy($creator);
        $documentPermission = new DocumentPermissionService($security, $this->createStub(DocumentShareRepository::class));

        $expense = (new Expense())
            ->setTitle('Depense Test')
            ->setReference('EXP-001')
            ->setSupplierName('Fournisseur')
            ->setCreatedBy($creator);
        $expenseAccess = new ExpenseAccessService($security, $this->createStub(ExpenseShareRepository::class));
        $expenseDocument = (new ExpenseDocument())->setExpense($expense)->setIsActive(true);

        $maintenanceAccess = new MaintenanceAccessService($security);

        self::assertTrue($contactPermission->canDelete($admin, $contact));
        self::assertTrue($contactPermission->canShare($admin, $contact));
        self::assertTrue($documentPermission->canDelete($admin, $document));
        self::assertTrue($documentPermission->canShare($admin, $document));
        self::assertTrue($expenseAccess->canDelete($admin, $expense));
        self::assertTrue($expenseAccess->canShare($admin, $expense));
        self::assertTrue($expenseAccess->canDeleteDocument($admin, $expenseDocument));
        self::assertTrue($maintenanceAccess->canDelete($admin));
        self::assertTrue($security->canDeletePasswords($admin));
    }

    public function testSharedExpenseRecipientCanViewButCannotDelete(): void
    {
        $owner = $this->userWithId(1, ['ROLE_USER']);
        $recipient = $this->userWithId(2, ['ROLE_USER']);
        $security = $this->securityWithModuleAccess();
        $expense = (new Expense())
            ->setTitle('Depense partagee')
            ->setReference('EXP-002')
            ->setSupplierName('Fournisseur')
            ->setCreatedBy($owner)
            ->setIsActive(true);
        $share = (new ExpenseShare())
            ->setExpense($expense)
            ->setUser($recipient)
            ->setCanView(true)
            ->setIsActive(true);

        $shareRepository = $this->createStub(ExpenseShareRepository::class);
        $shareRepository
            ->method('findFor')
            ->willReturn($share);
        $access = new ExpenseAccessService($security, $shareRepository);

        self::assertTrue($access->canView($recipient, $expense));
        self::assertFalse($access->canEdit($recipient, $expense));
        self::assertFalse($access->canDelete($recipient, $expense));
    }

    public function testNormalCreatorLosesAccessWhenShareIsRemoved(): void
    {
        $creator = $this->userWithId(1, ['ROLE_USER']);
        $security = $this->securityWithModuleAccess();

        $contact = (new Contact())
            ->setFullName('Client Test')
            ->setType('Client')
            ->setCreatedBy($creator)
            ->setIsActive(true);
        $contactPermission = new ContactPermissionService($security, $this->createStub(ContactShareRepository::class));

        $document = (new Document())
            ->setName('Document Test')
            ->setFileName('file.pdf')
            ->setOriginalFileName('file.pdf')
            ->setMimeType('application/pdf')
            ->setCreatedBy($creator);
        $documentPermission = new DocumentPermissionService($security, $this->createStub(DocumentShareRepository::class));

        $expense = (new Expense())
            ->setTitle('Depense Test')
            ->setReference('EXP-003')
            ->setSupplierName('Fournisseur')
            ->setCreatedBy($creator)
            ->setIsActive(true);
        $expenseAccess = new ExpenseAccessService($security, $this->createStub(ExpenseShareRepository::class));

        self::assertFalse($contactPermission->canView($creator, $contact));
        self::assertFalse($contactPermission->canEdit($creator, $contact));
        self::assertFalse($documentPermission->canView($creator, $document));
        self::assertFalse($documentPermission->canDownload($creator, $document));
        self::assertFalse($documentPermission->canEdit($creator, $document));
        self::assertFalse($expenseAccess->canView($creator, $expense));
        self::assertFalse($expenseAccess->canEdit($creator, $expense));
    }

    public function testNormalCreatorKeepsAccessThroughExplicitShare(): void
    {
        $creator = $this->userWithId(1, ['ROLE_USER']);
        $security = $this->securityWithModuleAccess();

        $contact = (new Contact())
            ->setFullName('Client Test')
            ->setType('Client')
            ->setCreatedBy($creator)
            ->setIsActive(true);
        $contactShare = (new ContactShare())
            ->setContact($contact)
            ->setUser($creator)
            ->setCanView(true)
            ->setIsActive(true);
        $contactShareRepository = $this->createStub(ContactShareRepository::class);
        $contactShareRepository->method('findFor')->willReturn($contactShare);
        $contactPermission = new ContactPermissionService($security, $contactShareRepository);

        $document = (new Document())
            ->setName('Document Test')
            ->setFileName('file.pdf')
            ->setOriginalFileName('file.pdf')
            ->setMimeType('application/pdf')
            ->setCreatedBy($creator);
        $documentShare = (new DocumentShare())
            ->setDocument($document)
            ->setUser($creator)
            ->setCanView(true)
            ->setCanDownload(true)
            ->setIsActive(true);
        $documentShareRepository = $this->createStub(DocumentShareRepository::class);
        $documentShareRepository->method('findFor')->willReturn($documentShare);
        $documentPermission = new DocumentPermissionService($security, $documentShareRepository);

        $expense = (new Expense())
            ->setTitle('Depense Test')
            ->setReference('EXP-004')
            ->setSupplierName('Fournisseur')
            ->setCreatedBy($creator)
            ->setIsActive(true);
        $expenseShare = (new ExpenseShare())
            ->setExpense($expense)
            ->setUser($creator)
            ->setCanView(true)
            ->setIsActive(true);
        $expenseShareRepository = $this->createStub(ExpenseShareRepository::class);
        $expenseShareRepository->method('findFor')->willReturn($expenseShare);
        $expenseAccess = new ExpenseAccessService($security, $expenseShareRepository);

        self::assertTrue($contactPermission->canView($creator, $contact));
        self::assertTrue($contactPermission->canEdit($creator, $contact));
        self::assertTrue($documentPermission->canView($creator, $document));
        self::assertTrue($documentPermission->canDownload($creator, $document));
        self::assertTrue($documentPermission->canEdit($creator, $document));
        self::assertTrue($expenseAccess->canView($creator, $expense));
        self::assertTrue($expenseAccess->canEdit($creator, $expense));
    }

    private function securityWithModuleAccess(): SecurityAccessService
    {
        $moduleRepository = $this->createStub(AppModuleRepository::class);
        $moduleRepository->method('findOneBy')->willReturnCallback(static function (array $criteria): ?AppModule {
            $slug = (string) ($criteria['slug'] ?? 'module');

            return (new AppModule())
                ->setName($slug)
                ->setSlug($slug)
                ->setRouteName('app_'.$slug.'_index')
                ->setIsActive(true);
        });

        $moduleAccessRepository = $this->createStub(UserModuleAccessRepository::class);
        $moduleAccessRepository->method('hasAccess')->willReturn(true);

        return new SecurityAccessService(
            $this->createStub(PasswordShareRepository::class),
            $moduleRepository,
            $moduleAccessRepository,
        );
    }

    /** @param list<string> $roles */
    private function userWithId(int $id, array $roles): User
    {
        $user = (new User())
            ->setEmail(sprintf('user%d@example.com', $id))
            ->setPassword('hash')
            ->setRoles($roles);
        $property = new \ReflectionProperty(User::class, 'id');
        $property->setValue($user, $id);

        return $user;
    }
}
