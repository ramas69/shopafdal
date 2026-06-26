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

    public function testAdminPromotesAndDemotesStaff(): void
    {
        $client = static::createClient();
        $admin = $this->createUser('admin');
        $staff = $this->createUser('client'); // employé Afdal, CLIENT_MANAGER, sans company

        $client->loginUser($admin);

        $client->request('POST', '/admin/equipe/' . $staff->getId() . '/role');
        self::assertResponseRedirects();
        $this->em()->clear();
        self::assertSame(UserRole::ADMIN, $this->em()->getRepository(User::class)->find($staff->getId())->getRole());

        $client->request('POST', '/admin/equipe/' . $staff->getId() . '/role');
        $this->em()->clear();
        self::assertSame(UserRole::CLIENT_MANAGER, $this->em()->getRepository(User::class)->find($staff->getId())->getRole());
    }

    public function testAdminCannotChangeOwnRole(): void
    {
        $client = static::createClient();
        $admin = $this->createUser('admin');

        $client->loginUser($admin);
        $client->request('POST', '/admin/equipe/' . $admin->getId() . '/role');

        self::assertResponseRedirects();
        $this->em()->clear();
        self::assertSame(UserRole::ADMIN, $this->em()->getRepository(User::class)->find($admin->getId())->getRole());
    }

    public function testCannotToggleUserWithCompany(): void
    {
        $client = static::createClient();
        [$company] = $this->createCompanyWithAntenna();
        $admin = $this->createUser('admin');
        $member = $this->createUser('client', $company, CompanyRole::OWNER);

        $client->loginUser($admin);
        $client->request('POST', '/admin/equipe/' . $member->getId() . '/role');

        self::assertResponseStatusCodeSame(403);
        $this->em()->clear();
        self::assertSame(UserRole::CLIENT_MANAGER, $this->em()->getRepository(User::class)->find($member->getId())->getRole());
    }

    public function testClientCannotAccessTeam(): void
    {
        $client = static::createClient();
        [$company] = $this->createCompanyWithAntenna();
        $user = $this->createUser('client', $company, CompanyRole::OWNER);

        $client->loginUser($user);
        $client->request('GET', '/admin/equipe');

        self::assertResponseStatusCodeSame(403);
    }
}
