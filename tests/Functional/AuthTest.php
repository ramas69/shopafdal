<?php

namespace App\Tests\Functional;

use App\Enum\CompanyRole;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AuthTest extends WebTestCase
{
    use TestDataTrait;

    public function testClientManagerCanLoginAndSeeCatalogue(): void
    {
        $client = static::createClient();
        [$company] = $this->createCompanyWithAntenna();
        $user = $this->createUser('client', $company, CompanyRole::OWNER, 'secretpass');

        $client->loginUser($user);
        $client->request('GET', '/catalogue');
        self::assertResponseIsSuccessful();
    }

    public function testAdminCanLoginAndSeeDashboard(): void
    {
        $client = static::createClient();
        $admin = $this->createUser('admin');

        $client->loginUser($admin);
        $client->request('GET', '/admin');
        self::assertResponseIsSuccessful();
    }

    public function testClientCannotAccessAdmin(): void
    {
        $client = static::createClient();
        [$company] = $this->createCompanyWithAntenna();
        $user = $this->createUser('client', $company, CompanyRole::OWNER);

        $client->loginUser($user);
        $client->request('GET', '/admin');
        self::assertResponseStatusCodeSame(403);
    }
}
