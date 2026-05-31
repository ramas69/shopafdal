<?php

namespace App\Tests\Functional;

use App\Entity\Order;
use App\Entity\OrderDocument;
use App\Enum\CompanyRole;
use App\Enum\OrderStatus;
use App\Repository\OrderDocumentRepository;
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

    private function persistDocument(Order $order, \App\Entity\User $uploader, string $originalName = 'devis.pdf'): OrderDocument
    {
        $publicDir = static::getContainer()->getParameter('kernel.project_dir') . '/public/uploads/order-docs';
        if (!is_dir($publicDir)) {
            mkdir($publicDir, 0775, true);
        }
        $stored = bin2hex(random_bytes(8)) . '.pdf';
        file_put_contents($publicDir . '/' . $stored, "%PDF-1.4\n%%EOF");
        $doc = new OrderDocument($order, $uploader, '/uploads/order-docs/' . $stored, $originalName, 'application/pdf', 12);
        $this->em()->persist($doc);
        $this->em()->flush();
        return $doc;
    }

    public function testAdminDownloadsDocumentWithOriginalName(): void
    {
        $client = static::createClient();
        [$company, $antenna] = $this->createCompanyWithAntenna();
        $owner = $this->createUser('client', $company, CompanyRole::OWNER);
        $admin = $this->createUser('admin');
        $order = $this->createOrder($company, $antenna, $owner);
        $doc = $this->persistDocument($order, $owner, 'bon-commande.pdf');

        $client->loginUser($admin);
        $client->request('GET', '/commandes/documents/' . $doc->getId() . '/download');

        self::assertResponseIsSuccessful();
        $disposition = $client->getResponse()->headers->get('Content-Disposition');
        self::assertStringContainsString('bon-commande.pdf', (string) $disposition);
    }

    public function testCrossCompanyDownloadIsForbidden(): void
    {
        $client = static::createClient();
        [$companyA, $antennaA] = $this->createCompanyWithAntenna('Alpha');
        [$companyB] = $this->createCompanyWithAntenna('Beta');
        $ownerA = $this->createUser('client', $companyA, CompanyRole::OWNER);
        $outsiderB = $this->createUser('client', $companyB, CompanyRole::OWNER);
        $order = $this->createOrder($companyA, $antennaA, $ownerA);
        $doc = $this->persistDocument($order, $ownerA);

        $client->loginUser($outsiderB);
        $client->request('GET', '/commandes/documents/' . $doc->getId() . '/download');

        self::assertResponseStatusCodeSame(403);
    }

    public function testAuthorDeletesDocumentOnDraftOrder(): void
    {
        $client = static::createClient();
        [$company, $antenna] = $this->createCompanyWithAntenna();
        $owner = $this->createUser('client', $company, CompanyRole::OWNER);
        $order = $this->createOrder($company, $antenna, $owner, OrderStatus::DRAFT);
        $doc = $this->persistDocument($order, $owner);
        $docId = $doc->getId();

        $client->loginUser($owner);
        $client->request('POST', '/commandes/documents/' . $docId . '/delete');

        self::assertResponseRedirects();
        $this->em()->clear();
        self::assertNull($this->em()->getRepository(OrderDocument::class)->find($docId));
    }

    public function testDeleteForbiddenOnConfirmedOrder(): void
    {
        $client = static::createClient();
        [$company, $antenna] = $this->createCompanyWithAntenna();
        $owner = $this->createUser('client', $company, CompanyRole::OWNER);
        $order = $this->createOrder($company, $antenna, $owner, OrderStatus::CONFIRMED);
        $doc = $this->persistDocument($order, $owner);

        $client->loginUser($owner);
        $client->request('POST', '/commandes/documents/' . $doc->getId() . '/delete');

        self::assertResponseStatusCodeSame(403);
        self::assertNotNull($this->em()->getRepository(OrderDocument::class)->find($doc->getId()));
    }

    public function testNonAuthorCannotDelete(): void
    {
        $client = static::createClient();
        [$company, $antenna] = $this->createCompanyWithAntenna();
        $owner = $this->createUser('client', $company, CompanyRole::OWNER);
        $other = $this->createUser('client', $company, CompanyRole::MEMBER);
        $order = $this->createOrder($company, $antenna, $owner, OrderStatus::DRAFT);
        $doc = $this->persistDocument($order, $owner);

        $client->loginUser($other);
        $client->request('POST', '/commandes/documents/' . $doc->getId() . '/delete');

        self::assertResponseStatusCodeSame(403);
        self::assertNotNull($this->em()->getRepository(OrderDocument::class)->find($doc->getId()));
    }

    public function testFindForCompanyScopesAndOrders(): void
    {
        self::bootKernel();
        [$companyA, $antennaA] = $this->createCompanyWithAntenna('Alpha');
        [$companyB, $antennaB] = $this->createCompanyWithAntenna('Beta');
        $ownerA = $this->createUser('client', $companyA, CompanyRole::OWNER);
        $ownerB = $this->createUser('client', $companyB, CompanyRole::OWNER);
        $orderA = $this->createOrder($companyA, $antennaA, $ownerA);
        $orderB = $this->createOrder($companyB, $antennaB, $ownerB);
        $this->persistDocument($orderA, $ownerA, 'alpha-1.pdf');
        $this->persistDocument($orderB, $ownerB, 'beta-1.pdf');

        $repo = static::getContainer()->get(OrderDocumentRepository::class);
        $docsA = $repo->findForCompany($companyA);

        self::assertCount(1, $docsA);
        self::assertSame('alpha-1.pdf', $docsA[0]->getOriginalName());
    }

    public function testClientDocumentsPageShowsOwnCompanyOnly(): void
    {
        $client = static::createClient();
        [$companyA, $antennaA] = $this->createCompanyWithAntenna('Alpha');
        [$companyB, $antennaB] = $this->createCompanyWithAntenna('Beta');
        $ownerA = $this->createUser('client', $companyA, CompanyRole::OWNER);
        $ownerB = $this->createUser('client', $companyB, CompanyRole::OWNER);
        $this->persistDocument($this->createOrder($companyA, $antennaA, $ownerA), $ownerA, 'alpha-doc.pdf');
        $this->persistDocument($this->createOrder($companyB, $antennaB, $ownerB), $ownerB, 'beta-doc.pdf');

        $client->loginUser($ownerA);
        $client->request('GET', '/documents');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('alpha-doc.pdf', $client->getResponse()->getContent());
        self::assertStringNotContainsString('beta-doc.pdf', $client->getResponse()->getContent());
    }

    public function testAdminDocumentsPageListsAll(): void
    {
        $client = static::createClient();
        [$companyA, $antennaA] = $this->createCompanyWithAntenna('Alpha');
        $ownerA = $this->createUser('client', $companyA, CompanyRole::OWNER);
        $admin = $this->createUser('admin');
        $this->persistDocument($this->createOrder($companyA, $antennaA, $ownerA), $ownerA, 'alpha-doc.pdf');

        $client->loginUser($admin);
        $client->request('GET', '/admin/documents');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('alpha-doc.pdf', $client->getResponse()->getContent());
    }

    public function testClientCannotAccessAdminDocuments(): void
    {
        $client = static::createClient();
        [$company] = $this->createCompanyWithAntenna();
        $user = $this->createUser('client', $company, CompanyRole::OWNER);

        $client->loginUser($user);
        $client->request('GET', '/admin/documents');

        self::assertResponseStatusCodeSame(403);
    }

    public function testFindAllRecentReturnsEveryDocument(): void
    {
        self::bootKernel();
        [$companyA, $antennaA] = $this->createCompanyWithAntenna('Alpha');
        [$companyB, $antennaB] = $this->createCompanyWithAntenna('Beta');
        $ownerA = $this->createUser('client', $companyA, CompanyRole::OWNER);
        $ownerB = $this->createUser('client', $companyB, CompanyRole::OWNER);
        $this->persistDocument($this->createOrder($companyA, $antennaA, $ownerA), $ownerA, 'a.pdf');
        $this->persistDocument($this->createOrder($companyB, $antennaB, $ownerB), $ownerB, 'b.pdf');

        $repo = static::getContainer()->get(OrderDocumentRepository::class);
        self::assertGreaterThanOrEqual(2, count($repo->findAllRecent()));
    }

    public function testAdminWithoutCompanyForbiddenOnClientDocuments(): void
    {
        $client = static::createClient();
        $admin = $this->createUser('admin');

        $client->loginUser($admin);
        $client->request('GET', '/documents');

        self::assertResponseStatusCodeSame(403);
    }
}
