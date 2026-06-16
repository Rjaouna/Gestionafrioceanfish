<?php

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testRoleUserIsAlwaysPresentAndRolesAreFiltered(): void
    {
        $user = (new User())->setRoles(['ROLE_ADMIN', 'ROLE_UNKNOWN']);

        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $user->getRoles());
    }

    public function testDisplayNameFallsBackToEmail(): void
    {
        $user = (new User())->setEmail('USER@EXAMPLE.COM');

        self::assertSame('user@example.com', $user->getEmail());
        self::assertSame('user@example.com', $user->getDisplayName());
    }
}
