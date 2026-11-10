<?php

namespace App\Tests\Entity;

use App\Entity\User;
use App\Security\UserChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

class UserRolesTest extends TestCase
{
    public function testNewAccountIsActiveCustomer(): void
    {
        $user = new User();

        self::assertSame(['ROLE_USER'], $user->getRoles());
        self::assertTrue($user->isActive());
        self::assertFalse($user->isStaff());
    }

    public function testStaffCannotAlsoBeAdministrator(): void
    {
        $user = (new User())->setIsStaff(true);

        self::assertSame(['ROLE_STAFF', 'ROLE_USER'], $user->getRoles());
        $user->setIsAdmin(true);

        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $user->getRoles());
        self::assertFalse($user->isStaff());
    }

    public function testDisabledAccountIsRejectedBeforeAuthentication(): void
    {
        $user = (new User())->setIsActive(false);
        $checker = new UserChecker();

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $checker->checkPreAuth($user);
    }
}
