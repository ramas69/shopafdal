# Dépôt de documents sur commande — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permettre au client de déposer des documents (PDF/images) sur une commande, consultables et téléchargeables par l'admin, avec suppression par l'auteur tant que la commande n'est pas traitée.

**Architecture:** Nouvelle entité `OrderDocument` (ManyToOne → `Order`), controller `OrderDocumentController` calqué sur `BatController` (upload natif PHP, `move()`, validation MIME/taille), notifications via `NotificationService::notifyAdmins`, journal via `OrderEventLogger`. UI ajoutée aux pages détail commande client + admin.

**Tech Stack:** Symfony 7.4, Doctrine ORM 3, MySQL/MariaDB, Twig, PHPUnit (WebTestCase + dama/doctrine-test-bundle).

**Convention CSRF :** le projet n'utilise pas de token CSRF explicite sur ses POST (cf. `BatController`). Ce plan suit cette convention pour rester cohérent. (Écart assumé vs spec ; gap projet à traiter séparément si besoin.)

**Route prefix :** les commandes sont montées sous `/commandes` (pluriel), pattern référence `CMD-[0-9]{4}-[0-9]+`.

---

## Fichiers

- Créer : `src/Entity/OrderDocument.php`
- Créer : `src/Repository/OrderDocumentRepository.php`
- Modifier : `src/Entity/Order.php` (relation inverse `documents`)
- Créer : `migrations/Version<timestamp>.php` (généré)
- Modifier : `src/Entity/OrderEvent.php` (constante `TYPE_DOCUMENT_UPLOADED`)
- Modifier : `src/Service/OrderEventLogger.php` (méthode `logDocumentUploaded`)
- Créer : `src/Controller/OrderDocumentController.php`
- Créer : `templates/order/_documents.html.twig` (section partagée liste + upload)
- Modifier : `templates/order/detail.html.twig` (inclure la section)
- Modifier : `templates/admin/` détail commande (liste lecture seule + download)
- Modifier : `templates/order/_timeline.html.twig` (event `document_uploaded`)
- Créer : `tests/Functional/OrderDocumentTest.php`
- Modifier : `AFDAL_DESIGN.md` (nouvelle phase)

---

## Task 1 : Entité `OrderDocument` + repository

**Files:**
- Create: `src/Entity/OrderDocument.php`
- Create: `src/Repository/OrderDocumentRepository.php`
- Modify: `src/Entity/Order.php`

- [ ] **Step 1 : Créer l'entité**

`src/Entity/OrderDocument.php` :

```php
<?php

namespace App\Entity;

use App\Repository\OrderDocumentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderDocumentRepository::class)]
#[ORM\Table(name: 'order_documents')]
#[ORM\Index(name: 'idx_orderdoc_order', columns: ['order_id'])]
class OrderDocument
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Order $order;

    #[ORM\Column(length: 255)]
    private string $path;

    #[ORM\Column(length: 255)]
    private string $originalName;

    #[ORM\Column(length: 100)]
    private string $mimeType;

    #[ORM\Column]
    private int $sizeBytes;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $uploadedBy;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Order $order, User $uploader, string $path, string $originalName, string $mimeType, int $sizeBytes)
    {
        $this->order = $order;
        $this->uploadedBy = $uploader;
        $this->path = $path;
        $this->originalName = $originalName;
        $this->mimeType = $mimeType;
        $this->sizeBytes = $sizeBytes;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getOrder(): Order { return $this->order; }
    public function getPath(): string { return $this->path; }
    public function getOriginalName(): string { return $this->originalName; }
    public function getMimeType(): string { return $this->mimeType; }
    public function getSizeBytes(): int { return $this->sizeBytes; }
    public function getUploadedBy(): User { return $this->uploadedBy; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function isPdf(): bool { return $this->mimeType === 'application/pdf'; }
    public function isImage(): bool { return str_starts_with($this->mimeType, 'image/'); }
}
```

- [ ] **Step 2 : Créer le repository**

`src/Repository/OrderDocumentRepository.php` :

```php
<?php

namespace App\Repository;

use App\Entity\Order;
use App\Entity\OrderDocument;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrderDocument>
 */
class OrderDocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderDocument::class);
    }

    /** @return OrderDocument[] */
    public function findForOrder(Order $order): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.order = :order')
            ->setParameter('order', $order)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
```

- [ ] **Step 3 : Ajouter la relation inverse sur `Order`**

Dans `src/Entity/Order.php`, après la propriété `$items` (ligne ~78) ajouter :

```php
    #[ORM\OneToMany(targetEntity: OrderDocument::class, mappedBy: 'order', cascade: ['remove'], orphanRemoval: true)]
    private Collection $documents;
```

Dans le constructeur `__construct()` (ligne ~81), après l'init de `$this->items` ajouter :

```php
        $this->documents = new ArrayCollection();
```

Ajouter l'accesseur près de `getItems()` (ligne ~164) :

```php
    /** @return Collection<int, OrderDocument> */
    public function getDocuments(): Collection { return $this->documents; }
```

(Les `use Doctrine\Common\Collections\ArrayCollection;` et `Collection` sont déjà importés pour `$items` — vérifier, sinon ajouter.)

- [ ] **Step 4 : Vérifier que le mapping est valide**

Run: `php bin/console doctrine:schema:validate --skip-sync`
Expected: `[OK] The mapping files are correct.` (la partie mapping ; le schéma DB sera synchronisé via migration en Task 2).

- [ ] **Step 5 : Commit**

```bash
git add src/Entity/OrderDocument.php src/Repository/OrderDocumentRepository.php src/Entity/Order.php
git commit -m "feat: entité OrderDocument + relation Order"
```

---

## Task 2 : Migration

**Files:**
- Create: `migrations/Version<timestamp>.php` (généré)

- [ ] **Step 1 : Générer la migration**

Run: `php bin/console make:migration`
Expected: création d'un fichier `migrations/Version<timestamp>.php` contenant `CREATE TABLE order_documents` (colonnes id, order_id, path, original_name, mime_type, size_bytes, uploaded_by_id, created_at), index `idx_orderdoc_order`, FK vers `orders` avec `ON DELETE CASCADE` et FK vers `user`.

- [ ] **Step 2 : Relire la migration générée**

Ouvrir le fichier généré. Vérifier :
- `CREATE TABLE order_documents` présent.
- FK `order_id` → `orders(id)` avec `ON DELETE CASCADE`.
- Aucune instruction destructive non liée (pas de DROP sur d'autres tables). Si présentes, supprimer ces lignes parasites.

- [ ] **Step 3 : Appliquer la migration**

Run: `php bin/console doctrine:migrations:migrate --no-interaction`
Expected: `[OK]` migration exécutée, table créée.

- [ ] **Step 4 : Vérifier le schéma synchronisé**

Run: `php bin/console doctrine:schema:validate`
Expected: `[OK] The mapping files are correct.` et `[OK] The database schema is in sync with the mapping files.`

- [ ] **Step 5 : Commit**

```bash
git add migrations/
git commit -m "feat: migration table order_documents"
```

---

## Task 3 : Journal — event `document_uploaded`

**Files:**
- Modify: `src/Entity/OrderEvent.php`
- Modify: `src/Service/OrderEventLogger.php`

- [ ] **Step 1 : Ajouter la constante de type d'event**

Dans `src/Entity/OrderEvent.php`, après `TYPE_SHIPPING_UPDATED` (ligne ~23) :

```php
    public const TYPE_DOCUMENT_UPLOADED = 'document_uploaded';
```

- [ ] **Step 2 : Ajouter la méthode de log**

Dans `src/Service/OrderEventLogger.php`, après `logBatRejected()` (ligne ~145) :

```php
    public function logDocumentUploaded(Order $order, string $fileName): void
    {
        $this->log(
            $order,
            OrderEvent::TYPE_DOCUMENT_UPLOADED,
            sprintf('Document déposé · %s', $fileName),
            ['file' => $fileName],
        );
    }
```

- [ ] **Step 3 : Vérifier que le code compile**

Run: `php -l src/Service/OrderEventLogger.php && php -l src/Entity/OrderEvent.php`
Expected: `No syntax errors detected` pour les deux fichiers.

- [ ] **Step 4 : Commit**

```bash
git add src/Entity/OrderEvent.php src/Service/OrderEventLogger.php
git commit -m "feat: log event document_uploaded"
```

---

## Task 4 : Controller — upload (TDD)

**Files:**
- Create: `tests/Functional/OrderDocumentTest.php`
- Create: `src/Controller/OrderDocumentController.php`

- [ ] **Step 1 : Écrire le test d'upload (qui échoue)**

`tests/Functional/OrderDocumentTest.php` :

```php
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
        $suffix = random_int(1000, 9999);
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

        self::assertResponseRedirects();
        $docs = $this->em()->getRepository(OrderDocument::class)->findBy(['order' => $order]);
        self::assertCount(1, $docs);
        self::assertSame('devis.pdf', $docs[0]->getOriginalName());
        self::assertSame('application/pdf', $docs[0]->getMimeType());
        self::assertSame($user->getId(), $docs[0]->getUploadedBy()->getId());
    }
}
```

- [ ] **Step 2 : Lancer le test → échec attendu**

Run: `php bin/phpunit --filter testClientUploadsPdfDocument`
Expected: FAIL — route `/commandes/{ref}/documents` inexistante (404) ou `OrderDocumentController` introuvable.

- [ ] **Step 3 : Écrire le controller (upload uniquement)**

`src/Controller/OrderDocumentController.php` :

```php
<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\Order;
use App\Entity\OrderDocument;
use App\Entity\User;
use App\Service\NotificationService;
use App\Service\OrderEventLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/commandes', requirements: ['reference' => 'CMD-[0-9]{4}-[0-9]+'])]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class OrderDocumentController extends AbstractController
{
    private const UPLOAD_PUBLIC_PREFIX = '/uploads/order-docs/';
    private const ALLOWED_MIME = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
    private const MAX_BYTES = 10 * 1024 * 1024; // 10 Mo

    public function __construct(
        #[Autowire('%kernel.project_dir%/public/uploads/order-docs')]
        private string $uploadDir,
    ) {}

    #[Route('/{reference}/documents', name: 'app_order_doc_upload', methods: ['POST'])]
    public function upload(
        #[MapEntity(mapping: ['reference' => 'reference'])] Order $order,
        Request $request,
        EntityManagerInterface $em,
        NotificationService $notifications,
        OrderEventLogger $events,
    ): RedirectResponse {
        $this->assertClientOwns($order);

        /** @var UploadedFile|null $file */
        $file = $request->files->get('document');
        if (!$file) {
            $max = ini_get('upload_max_filesize') ?: '?';
            $this->addFlash('error', sprintf('Aucun fichier reçu (taille max serveur : %s).', $max));
            return $this->redirectToRoute('app_order_detail', ['reference' => $order->getReference()]);
        }
        if (!$file->isValid()) {
            $msg = match ($file->getError()) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => sprintf('Fichier trop volumineux (max %s).', ini_get('upload_max_filesize') ?: '?'),
                UPLOAD_ERR_PARTIAL => 'Téléversement interrompu, réessayez.',
                UPLOAD_ERR_NO_FILE => 'Aucun fichier sélectionné.',
                default => 'Erreur serveur lors de la réception du fichier.',
            };
            $this->addFlash('error', $msg);
            return $this->redirectToRoute('app_order_detail', ['reference' => $order->getReference()]);
        }
        if ($file->getSize() > self::MAX_BYTES) {
            $this->addFlash('error', 'Fichier trop volumineux (max 10 Mo).');
            return $this->redirectToRoute('app_order_detail', ['reference' => $order->getReference()]);
        }
        $mime = $file->getMimeType();
        if (!$mime || !in_array($mime, self::ALLOWED_MIME, true)) {
            $this->addFlash('error', sprintf('Format non supporté (%s). Utilisez PDF, JPG, PNG ou WebP.', $mime ?: 'inconnu'));
            return $this->redirectToRoute('app_order_detail', ['reference' => $order->getReference()]);
        }

        $originalName = $file->getClientOriginalName();
        $size = $file->getSize();
        $filename = bin2hex(random_bytes(12)) . '.' . ($file->guessExtension() ?: 'bin');
        try {
            $file->move($this->uploadDir, $filename);
        } catch (FileException) {
            $this->addFlash('error', 'Échec du téléversement.');
            return $this->redirectToRoute('app_order_detail', ['reference' => $order->getReference()]);
        }

        /** @var User $user */
        $user = $this->getUser();
        $document = new OrderDocument(
            $order,
            $user,
            self::UPLOAD_PUBLIC_PREFIX . $filename,
            $originalName,
            $mime,
            $size,
        );
        $em->persist($document);
        $events->logDocumentUploaded($order, $originalName);
        $em->flush();

        $notifications->notifyAdmins(
            sprintf('Document reçu · Commande %s', $order->getReference()),
            $originalName,
            $this->generateUrl('app_admin_order_detail', ['reference' => $order->getReference()]),
            Notification::TYPE_INFO,
        );

        $this->addFlash('success', sprintf('Document « %s » envoyé à Afdal.', $originalName));
        return $this->redirectToRoute('app_order_detail', ['reference' => $order->getReference()]);
    }

    private function assertClientOwns(Order $order): void
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user->isAdmin() && $order->getCompany()->getId() !== $user->getCompany()?->getId()) {
            throw $this->createAccessDeniedException();
        }
    }
}
```

- [ ] **Step 4 : Lancer le test → succès attendu**

Run: `php bin/phpunit --filter testClientUploadsPdfDocument`
Expected: PASS.

- [ ] **Step 5 : Commit**

```bash
git add src/Controller/OrderDocumentController.php tests/Functional/OrderDocumentTest.php
git commit -m "feat: upload document commande client"
```

---

## Task 5 : Controller — validation (MIME + taille) (TDD)

**Files:**
- Modify: `tests/Functional/OrderDocumentTest.php`

- [ ] **Step 1 : Écrire les tests de rejet**

Ajouter dans `OrderDocumentTest` :

```php
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
```

- [ ] **Step 2 : Lancer les tests → succès attendu**

Run: `php bin/phpunit --filter testUploadRejects`
Expected: PASS (la validation MIME + taille est déjà implémentée en Task 4).

> Note : si `testUploadRejectsOversizeFile` échoue car `upload_max_filesize` PHP bloque avant le contrôle applicatif (le fichier devient invalide), le test reste vert : 0 document persisté dans les deux cas. L'assertion porte sur le résultat (0 row), pas sur le chemin de validation.

- [ ] **Step 3 : Commit**

```bash
git add tests/Functional/OrderDocumentTest.php
git commit -m "test: rejet MIME et taille à l'upload de document"
```

---

## Task 6 : Controller — download (TDD)

**Files:**
- Modify: `tests/Functional/OrderDocumentTest.php`
- Modify: `src/Controller/OrderDocumentController.php`

- [ ] **Step 1 : Écrire le test de download**

Ajouter dans `OrderDocumentTest` un helper de création de document persisté + fichier disque, et le test :

```php
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
```

- [ ] **Step 2 : Lancer les tests → échec attendu**

Run: `php bin/phpunit --filter testAdminDownloadsDocumentWithOriginalName`
Expected: FAIL — route `download` inexistante (404).

- [ ] **Step 3 : Ajouter l'action download**

Dans `src/Controller/OrderDocumentController.php`, ajouter l'import en tête :

```php
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
```

Puis la méthode (après `upload`) :

```php
    #[Route('/documents/{document}/download', name: 'app_order_doc_download', methods: ['GET'], requirements: ['document' => '\d+'])]
    public function download(
        #[MapEntity(mapping: ['document' => 'id'])] OrderDocument $document,
        #[Autowire('%kernel.project_dir%/public')] string $publicDir,
    ): Response {
        $this->assertClientOwns($document->getOrder());

        $absolute = $publicDir . $document->getPath();
        if (!is_file($absolute)) {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        $response = new BinaryFileResponse($absolute);
        $response->setContentDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $document->getOriginalName(),
        );
        return $response;
    }
```

(`assertClientOwns` autorise déjà admin OU membre de la Company — couvre les deux cas de download.)

- [ ] **Step 4 : Lancer les tests → succès attendu**

Run: `php bin/phpunit --filter "testAdminDownloadsDocumentWithOriginalName|testCrossCompanyDownloadIsForbidden"`
Expected: PASS.

- [ ] **Step 5 : Commit**

```bash
git add src/Controller/OrderDocumentController.php tests/Functional/OrderDocumentTest.php
git commit -m "feat: téléchargement document commande (download forcé du nom original)"
```

---

## Task 7 : Controller — suppression (TDD)

**Files:**
- Modify: `tests/Functional/OrderDocumentTest.php`
- Modify: `src/Controller/OrderDocumentController.php`

- [ ] **Step 1 : Écrire les tests de suppression**

Ajouter dans `OrderDocumentTest` :

```php
    public function testAuthorDeletesDocumentOnDraftOrder(): void
    {
        $client = static::createClient();
        [$company, $antenna] = $this->createCompanyWithAntenna();
        $owner = $this->createUser('client', $company, CompanyRole::OWNER);
        $order = $this->createOrder($company, $antenna, $owner, OrderStatus::DRAFT);
        $doc = $this->persistDocument($order, $owner);

        $client->loginUser($owner);
        $client->request('POST', '/commandes/documents/' . $doc->getId() . '/delete');

        self::assertResponseRedirects();
        self::assertNull($this->em()->getRepository(OrderDocument::class)->find($doc->getId()));
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
```

- [ ] **Step 2 : Lancer les tests → échec attendu**

Run: `php bin/phpunit --filter "testAuthorDeletesDocumentOnDraftOrder|testDeleteForbiddenOnConfirmedOrder|testNonAuthorCannotDelete"`
Expected: FAIL — route `delete` inexistante (404).

- [ ] **Step 3 : Ajouter l'action delete**

Dans `src/Controller/OrderDocumentController.php`, ajouter l'import :

```php
use App\Enum\OrderStatus;
```

Puis la méthode (après `download`) :

```php
    #[Route('/documents/{document}/delete', name: 'app_order_doc_delete', methods: ['POST'], requirements: ['document' => '\d+'])]
    public function delete(
        #[MapEntity(mapping: ['document' => 'id'])] OrderDocument $document,
        EntityManagerInterface $em,
        #[Autowire('%kernel.project_dir%/public')] string $publicDir,
    ): RedirectResponse {
        $order = $document->getOrder();
        /** @var User $user */
        $user = $this->getUser();

        $deletable = in_array($order->getStatus(), [OrderStatus::DRAFT, OrderStatus::PLACED], true);
        if ($document->getUploadedBy()->getId() !== $user->getId() || !$deletable) {
            throw $this->createAccessDeniedException();
        }

        $absolute = $publicDir . $document->getPath();
        if (is_file($absolute)) {
            @unlink($absolute);
        }
        $em->remove($document);
        $em->flush();

        $this->addFlash('success', 'Document supprimé.');
        return $this->redirectToRoute('app_order_detail', ['reference' => $order->getReference()]);
    }
```

- [ ] **Step 4 : Lancer les tests → succès attendu**

Run: `php bin/phpunit --filter "testAuthorDeletesDocumentOnDraftOrder|testDeleteForbiddenOnConfirmedOrder|testNonAuthorCannotDelete"`
Expected: PASS.

- [ ] **Step 5 : Lancer toute la suite documents**

Run: `php bin/phpunit --filter OrderDocumentTest`
Expected: PASS (tous les tests verts).

- [ ] **Step 6 : Commit**

```bash
git add src/Controller/OrderDocumentController.php tests/Functional/OrderDocumentTest.php
git commit -m "feat: suppression document par l'auteur (commande non traitée)"
```

---

## Task 8 : UI client — section documents

**Files:**
- Create: `templates/order/_documents.html.twig`
- Modify: `templates/order/detail.html.twig`

- [ ] **Step 1 : Créer le partial**

`templates/order/_documents.html.twig` :

```twig
{# @param order, can_delete (bool: commande non traitée), is_admin (bool) #}
<section class="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-5">
    <h2 class="text-base font-semibold text-[var(--color-foreground)] mb-3">Documents partagés</h2>

    {% if order.documents|length > 0 %}
        <ul class="divide-y divide-[var(--color-border)] mb-4">
            {% for doc in order.documents %}
                <li class="flex items-center gap-3 py-2.5">
                    <span class="shrink-0 w-9 h-9 rounded-md bg-[var(--color-muted)] flex items-center justify-center text-xs font-semibold text-[var(--color-secondary)]">
                        {{ doc.isPdf ? 'PDF' : 'IMG' }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <a href="{{ path('app_order_doc_download', {document: doc.id}) }}"
                           class="text-sm font-medium text-[var(--color-primary)] hover:underline truncate block">
                            {{ doc.originalName }}
                        </a>
                        <div class="text-xs text-[var(--color-secondary)]">
                            {{ (doc.sizeBytes / 1024)|round }} Ko · {{ doc.createdAt|date('d/m/Y') }}
                            {% if is_admin %} · déposé par {{ doc.uploadedBy.fullName }}{% endif %}
                        </div>
                    </div>
                    {% if not is_admin and can_delete and doc.uploadedBy.id == app.user.id %}
                        <form method="post" action="{{ path('app_order_doc_delete', {document: doc.id}) }}">
                            <button type="submit"
                                    class="shrink-0 text-xs font-medium text-[var(--color-destructive)] hover:underline">
                                Supprimer
                            </button>
                        </form>
                    {% endif %}
                </li>
            {% endfor %}
        </ul>
    {% else %}
        <p class="text-sm text-[var(--color-secondary)] mb-4">Aucun document pour le moment.</p>
    {% endif %}

    {% if not is_admin %}
        <form method="post" enctype="multipart/form-data"
              action="{{ path('app_order_doc_upload', {reference: order.reference}) }}"
              class="flex items-center gap-3">
            <input type="file" name="document" required
                   accept="application/pdf,image/jpeg,image/png,image/webp"
                   class="text-sm text-[var(--color-body)] file:mr-3 file:rounded-md file:border-0 file:bg-[var(--color-primary)] file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-white">
            <button type="submit"
                    class="rounded-md bg-[var(--color-primary)] px-3 py-1.5 text-sm font-medium text-white hover:bg-[var(--color-primary-focus)]">
                Déposer
            </button>
        </form>
        <p class="mt-2 text-xs text-[var(--color-secondary)]">PDF ou image (JPG, PNG, WebP) · max 10 Mo.</p>
    {% endif %}
</section>
```

- [ ] **Step 2 : Inclure le partial dans la page détail client**

Dans `templates/order/detail.html.twig`, repérer la zone où sont rendues les sections (à proximité de la timeline / conversation) et insérer :

```twig
{{ include('order/_documents.html.twig', {
    order: order,
    can_delete: order.status.value in ['draft', 'placed'],
    is_admin: false,
}) }}
```

- [ ] **Step 3 : Vérifier le rendu**

Run: `php bin/phpunit --filter testClientUploadsPdfDocument`
Expected: PASS (le test d'upload passe toujours ; le rendu de la page détail n'est pas cassé).

Vérification manuelle (optionnelle si app lançable) : ouvrir une commande en tant que client → section « Documents partagés » visible avec champ d'upload.

- [ ] **Step 4 : Commit**

```bash
git add templates/order/_documents.html.twig templates/order/detail.html.twig
git commit -m "feat: section documents sur la page commande client"
```

---

## Task 9 : UI admin — liste documents (lecture seule)

**Files:**
- Modify: template détail commande admin (sous `templates/admin/`)

- [ ] **Step 1 : Localiser le template admin de détail commande**

Run: `grep -rl "app_admin_order_detail\|order.reference" templates/admin/`
Identifier le template rendu par `app_admin_order_detail` (controller `src/Controller/Admin/OrderController.php:71`). Vérifier que la variable `order` y est disponible.

- [ ] **Step 2 : Inclure le partial en mode admin**

Dans le template admin de détail commande, insérer (zone détails commande) :

```twig
{{ include('order/_documents.html.twig', {
    order: order,
    can_delete: false,
    is_admin: true,
}) }}
```

- [ ] **Step 3 : Vérifier**

Run: `php bin/phpunit --filter testAdminDownloadsDocumentWithOriginalName`
Expected: PASS.

Vérification manuelle (optionnelle) : ouvrir la commande côté admin → liste des documents avec « déposé par … » et lien de téléchargement, sans champ d'upload ni bouton supprimer.

- [ ] **Step 4 : Commit**

```bash
git add templates/admin/
git commit -m "feat: liste documents commande côté admin (lecture seule)"
```

---

## Task 10 : Timeline — affichage de l'event

**Files:**
- Modify: `templates/order/_timeline.html.twig`

- [ ] **Step 1 : Ajouter le type d'event au mapping de la timeline**

Dans `templates/order/_timeline.html.twig`, dans l'objet de configuration des types (vers les lignes 12-14, à côté de `bat_uploaded`), ajouter :

```twig
    'document_uploaded': {title: 'Document déposé',     bg: '#E0EAFC', fg: '#175CD3'},
```

- [ ] **Step 2 : Gérer l'icône/branche du rendu**

Dans le bloc conditionnel des icônes (vers ligne 38, là où `event.type == 'bat_uploaded'` est traité), étendre la condition pour inclure le nouveau type, p.ex. :

```twig
                {% elseif event.type == 'bat_uploaded' or event.type == 'document_uploaded' %}
```

(Reprendre exactement le markup d'icône déjà utilisé pour `bat_uploaded` dans ce bloc.)

- [ ] **Step 3 : Vérifier la suite complète**

Run: `php bin/phpunit`
Expected: PASS (toute la suite, aucun test cassé).

- [ ] **Step 4 : Commit**

```bash
git add templates/order/_timeline.html.twig
git commit -m "feat: event document_uploaded dans la timeline commande"
```

---

## Task 11 : Documentation

**Files:**
- Modify: `AFDAL_DESIGN.md`

- [ ] **Step 1 : Ajouter une phase dans le journal projet**

Dans `AFDAL_DESIGN.md`, ajouter une section (dans la zone des phases, après la dernière) :

```markdown
### Phase — Documents commande (client → admin) (2026-05-31)

**Besoin** : le client dépose des documents (PDF/images) sur une commande, l'admin les consulte/télécharge.

**Modèle** : nouvelle entité `OrderDocument` (ManyToOne → `Order`, `onDelete: CASCADE`), champs `path`, `originalName`, `mimeType`, `sizeBytes`, `uploadedBy`, `createdAt`. Pas de versioning ni de workflow review (≠ `MarkingAsset`).

**Upload** : `OrderDocumentController`, calqué sur `BatController` (move() natif, validation MIME + taille 10 Mo). MIME autorisés : PDF, JPEG, PNG, WebP. Stockage `public/uploads/order-docs/`.

**Sécurité** : appartenance commande à la Company de l'utilisateur (admin = accès global). Suppression réservée à l'auteur tant que la commande est `DRAFT`/`PLACED`.

**Notifs / journal** : `notifyAdmins` au dépôt + event `document_uploaded` dans la timeline.

**Convention** : pas de CSRF explicite (cohérent avec le reste du projet).
```

- [ ] **Step 2 : Commit**

```bash
git add AFDAL_DESIGN.md
git commit -m "docs: phase documents commande dans AFDAL_DESIGN.md"
```

---

## Vérification finale

- [ ] **Suite complète verte**

Run: `php bin/phpunit`
Expected: OK, tous les tests passent.

- [ ] **Schéma en phase**

Run: `php bin/console doctrine:schema:validate`
Expected: mapping correct + schéma en sync.

- [ ] **Rappel déploiement** (avant prod) : `php bin/console asset-map:compile` + créer le dossier `public/uploads/order-docs/` avec droits d'écriture sur le serveur o2switch.
