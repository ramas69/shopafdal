<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Enum\CompanyRole;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminTeamTest extends WebTestCase
{
    use TestDataTrait;

    public function testFindAfdalStaffReturnsOnlyUsersWithoutCompany(): void
    {
        self::bootKernel();
        [$company] = $this->createCompanyWithAntenna();
        $staff = $this->createUser('client');                       // sans company → employé Afdal
        $client = $this->createUser('client', $company, CompanyRole::OWNER); // rattaché → exclu

        $repo = static::getContainer()->get(UserRepository::class);
        $result = $repo->findAfdalStaff();

        $ids = array_map(fn (User $u) => $u->getId(), $result);
        self::assertContains($staff->getId(), $ids);
        self::assertNotContains($client->getId(), $ids);
    }
}
