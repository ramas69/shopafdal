<?php

namespace App\Tests\Functional;

use App\Entity\Order;
use App\Entity\OrderDocument;
use App\Enum\CompanyRole;
use App\Enum\OrderStatus;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class OrderDocumentTest extends WebTestCase
{
    use TestDataTrait;

    private function createOrder(\App\Entity\Company $company, \App\Entity\Antenna $antenna, \App\Entity\User $createdBy, OrderStatus $status = OrderStatus::DRAFT): Order
    {
        $suffix = random_int(100000, 999999);
        $order = (new Order())
            ->setReference("CMD-2026-$suffix")
            ->setCompany($company)
            ->setAntenna($antenna)
            ->setCreatedBy($createdBy)
            ->setStatus($status);
        $this->em()->persist($order);
        $this->em()->flush();
        return $order;
    }

    private function tmpFile(string $name, string $content, string $mime): UploadedFile
    {
        $path = sys_get_temp_dir() . '/' . bin2hex(random_bytes(6)) . '-' . $name;
        file_put_contents($path, $content);
        return new UploadedFile($path, $name, $mime, null, true); // test mode
    }

    public function testClientUploadsPdfDocument(): void
    {
        $client = static::createClient();
        [$company, $antenna] = $this->createCompanyWithAntenna();
        $user = $this->createUser('client', $company, CompanyRole::OWNER);
        $order = $this->createOrder($company, $antenna, $user);

        $client->loginUser($user);
        $pdf = $this->tmpFile('devis.pdf', "%PDF-1.4\n%%EOF", 'application/pdf');
        $client->request(
            'POST',
            '/commandes/' . $order->getReference() . '/documents',
            [],
            ['document' => $pdf],
        );

        self::assertResponseRedirects('/commandes/' . $order->getReference());
        $docs = $this->em()->getRepository(OrderDocument::class)->findBy(['order' => $order]);
        self::assertCount(1, $docs);
        self::assertSame('devis.pdf', $docs[0]->getOriginalName());
        self::assertSame('application/pdf', $docs[0]->getMimeType());
        self::assertSame($user->getId(), $docs[0]->getUploadedBy()->getId());
    }

    public function testUploadRejectsDisallowedMime(): void
    {
        $client = static::createClient();
        [$company, $antenna] = $this->createCompanyWithAntenna();
        $user = $this->createUser('client', $company, CompanyRole::OWNER);
        $order = $this->createOrder($company, $antenna, $user);

        $client->loginUser($user);
        $txt = $this->tmpFile('note.txt', 'hello', 'text/plain');
        $client->request('POST', '/commandes/' . $order->getReference() . '/documents', [], ['document' => $txt]);

        self::assertResponseRedirects();
        self::assertCount(0, $this->em()->getRepository(OrderDocument::class)->findBy(['order' => $order]));
    }

    public function testUploadRejectsOversizeFile(): void
    {
        $client = static::createClient();
        [$company, $antenna] = $this->createCompanyWithAntenna();
        $user = $this->createUser('client', $company, CompanyRole::OWNER);
        $order = $this->createOrder($company, $antenna, $user);

        $client->loginUser($user);
        $big = $this->tmpFile('big.pdf', str_repeat('A', 11 * 1024 * 1024), 'application/pdf');
        $client->request('POST', '/commandes/' . $order->getReference() . '/documents', [], ['document' => $big]);

        self::assertResponseRedirects();
        self::assertCount(0, $this->em()->getRepository(OrderDocument::class)->findBy(['order' => $order]));
    }
}
