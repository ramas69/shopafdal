<?php

namespace App\Tests\Functional;

use App\Enum\CompanyRole;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ResetPasswordTest extends WebTestCase
{
    use TestDataTrait;

    public function testRequestPasswordResetFlashesDevUrlWithNullMailer(): void
    {
        $client = static::createClient();
        [$company] = $this->createCompanyWithAntenna();
        $user = $this->createUser('client', $company, CompanyRole::OWNER);

        $crawler = $client->request('GET', '/reset-password');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Envoyer le lien')->form();
        // Le nom du champ est standard symfonycasts : reset_password_request_form[email]
        foreach ($form->all() as $field) {
            if (str_ends_with($field->getName(), '[email]')) {
                $field->setValue($user->getEmail());
            }
        }
        $client->submit($form);
        self::assertResponseRedirects('/reset-password/check-email');

        $client->followRedirect();
        self::assertResponseIsSuccessful();
        // Le fallback dev doit exposer l'URL
        self::assertStringContainsString('/reset-password/reset/', $client->getResponse()->getContent());
    }

    public function testUnknownEmailStillRedirectsWithoutRevealing(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/reset-password');

        $form = $crawler->selectButton('Envoyer le lien')->form();
        foreach ($form->all() as $field) {
            if (str_ends_with($field->getName(), '[email]')) {
                $field->setValue('inconnu@nowhere.test');
            }
        }
        $client->submit($form);
        self::assertResponseRedirects('/reset-password/check-email');
    }
}
