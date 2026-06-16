<?php

namespace App\Tests\Form;

use App\Form\UserManagementType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;

final class UserManagementTypeTest extends TestCase
{
    public function testRoleFieldIsAbsentForNonSuperAdmins(): void
    {
        $fields = $this->buildFields(false);

        self::assertArrayNotHasKey('roles', $fields);
    }

    public function testRoleFieldIsAvailableButUnmappedForSuperAdmins(): void
    {
        $fields = $this->buildFields(true);

        self::assertArrayHasKey('roles', $fields);
        self::assertFalse($fields['roles']['mapped']);
        self::assertSame('ROLE_ADMIN', $fields['roles']['data']);
    }

    /** @return array<string, array<string, mixed>> */
    private function buildFields(bool $canManageRoles): array
    {
        $fields = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(
            static function (string|FormBuilderInterface $child, ?string $type = null, array $options = []) use (&$fields, $builder): FormBuilderInterface {
                if (is_string($child)) {
                    $fields[$child] = $options;
                }

                return $builder;
            },
        );

        (new UserManagementType())->buildForm($builder, [
            'password_required' => true,
            'selected_modules' => [],
            'can_manage_roles' => $canManageRoles,
            'selected_role' => 'ROLE_ADMIN',
        ]);

        return $fields;
    }
}
