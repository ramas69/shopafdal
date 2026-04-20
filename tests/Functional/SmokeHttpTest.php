<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Smoke HTTP : les routes publiques/non authentifiées répondent. */
final class SmokeHttpTest extends WebTestCase
{
    public function testHomePageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
    }

    public function testLoginPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('button[type="submit"]', 'Se connecter');
    }

    public function testForgotPasswordPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/reset-password');
        self::assertResponseIsSuccessful();
    }

    public function testCgvPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/cgv');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Conditions Générales de Vente');
    }

    public function testCatalogueRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('GET', '/catalogue');
        self::assertResponseRedirects('/login');
    }

    public function testAdminRequiresAdminRole(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin');
        // redirige sur login (anonyme) ou AccessDenied (authentifié non-admin) — les 2 acceptables ici
        self::assertTrue(
            $client->getResponse()->isRedirect() || $client->getResponse()->getStatusCode() === 403,
            'Admin route should be protected'
        );
    }
}
