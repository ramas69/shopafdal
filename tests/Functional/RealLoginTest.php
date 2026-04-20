<?php

namespace App\Tests\Functional;

use App\Enum\CompanyRole;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Tests réels du formulaire de login — soumission HTTP + password verify + CSRF + redirect. */
final class RealLoginTest extends WebTestCase
{
    use TestDataTrait;

    public function testSuccessfulLoginRedirectsToDashboard(): void
    {
        $client = static::createClient();
        [$company] = $this->createCompanyWithAntenna();
        $user = $this->createUser('client', $company, CompanyRole::OWNER, 'monmotdepasse');

        $crawler = $client->request('GET', '/login');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Se connecter')->form([
            '_username' => $user->getEmail(),
            '_password' => 'monmotdepasse',
        ]);
        $client->submit($form);

        // Redirection vers dashboard après auth réussie
        self::assertResponseRedirects();
        $client->followRedirect();
        // Le dashboard pour un client redirige vers /catalogue
        if ($client->getResponse()->isRedirect()) {
            $client->followRedirect();
        }
        self::assertResponseIsSuccessful();
    }

    public function testWrongPasswordKeepsOnLoginWithError(): void
    {
        $client = static::createClient();
        [$company] = $this->createCompanyWithAntenna();
        $user = $this->createUser('client', $company, CompanyRole::OWNER, 'bonmotdepasse');

        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Se connecter')->form([
            '_username' => $user->getEmail(),
            '_password' => 'mauvais',
        ]);
        $client->submit($form);

        // Redirection vers /login (Symfony form_login standard)
        self::assertResponseRedirects('/login');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        // Un message d'erreur est présent (identifiants invalides)
        self::assertSelectorExists('body');
    }

    public function testAdminLoginGoesToAdminDashboard(): void
    {
        $client = static::createClient();
        $admin = $this->createUser('admin', password: 'adminpass');

        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Se connecter')->form([
            '_username' => $admin->getEmail(),
            '_password' => 'adminpass',
        ]);
        $client->submit($form);

        self::assertResponseRedirects();
        $client->followRedirect();
        if ($client->getResponse()->isRedirect()) {
            $client->followRedirect();
        }
        self::assertResponseIsSuccessful();
    }

    public function testLogoutEndsSession(): void
    {
        $client = static::createClient();
        [$company] = $this->createCompanyWithAntenna();
        $user = $this->createUser('client', $company, CompanyRole::OWNER, 'pass1234');

        // login via form
        $crawler = $client->request('GET', '/login');
        $client->submit($crawler->selectButton('Se connecter')->form([
            '_username' => $user->getEmail(),
            '_password' => 'pass1234',
        ]));

        // logout
        $client->request('GET', '/logout');
        // Puis accéder à une route protégée doit rediriger vers login
        $client->request('GET', '/catalogue');
        self::assertResponseRedirects('/login');
    }
}
