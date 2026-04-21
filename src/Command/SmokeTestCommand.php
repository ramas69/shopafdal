<?php

namespace App\Command;

use App\Entity\Antenna;
use App\Entity\Company;
use App\Entity\CompanyPrice;
use App\Entity\Favorite;
use App\Entity\Invitation;
use App\Entity\MarkingAsset;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\OrderMessage;
use App\Entity\PriceTier;
use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\CompanyRole;
use App\Enum\MarkingStatus;
use App\Enum\OrderStatus;
use App\Enum\ProductStatus;
use App\Enum\UserRole;
use App\Service\PricingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Throwable;

#[AsCommand(name: 'app:smoke-test', description: 'Exercise the main flows end-to-end (in a rollback transaction by default).')]
final class SmokeTestCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
        private PricingService $pricing,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('keep', null, InputOption::VALUE_NONE, 'Ne rollback pas — persiste les données de test (préfixe `smoke-`).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Afdal — Smoke test');

        $keep = $input->getOption('keep');
        $conn = $this->em->getConnection();
        $conn->beginTransaction();

        $steps = [];
        $error = null;

        try {
            $steps[] = $this->step('Création entreprise + antenne', fn() => $this->createCompany());
            [$company, $antenna] = end($steps)['result'];

            $steps[] = $this->step('Création utilisateurs (admin + owner + member)', fn() => $this->createUsers($company));
            [$admin, $owner, $member] = end($steps)['result'];

            $steps[] = $this->step('Création produit + variantes + stock + paliers + tarif négocié', fn() => $this->createProduct($company));
            [$product, $variants] = end($steps)['result'];

            $steps[] = $this->step('Résolution de prix (PricingService)', fn() => $this->checkPricing($product, $company));

            $steps[] = $this->step('Favorite (owner ajoute le produit aux favoris)', fn() => $this->createFavorite($owner, $product));

            $steps[] = $this->step('Création commande PLACED avec 3 items + BAT upload', fn() => $this->createOrder($company, $antenna, $owner, $variants));
            /** @var Order $order */
            $order = end($steps)['result'];

            $steps[] = $this->step('Admin valide le BAT', fn() => $this->approveBat($order, $admin));

            $steps[] = $this->step('Admin met à jour livraison + transition SHIPPED', fn() => $this->updateShipping($order, $admin));

            $steps[] = $this->step('Messagerie (owner → admin puis admin → client)', fn() => $this->exchangeMessages($order, $owner, $admin));

            $steps[] = $this->step('Owner invite un membre via /parametres (simulé)', fn() => $this->inviteTeamMember($owner));

            $io->newLine();
        } catch (Throwable $t) {
            $error = $t;
        }

        if ($keep && $error === null) {
            $conn->commit();
            $io->warning('Données conservées (--keep).');
        } else {
            $conn->rollBack();
            if ($error === null) {
                $io->success('Rollback complet — la DB n\'a pas été modifiée.');
            }
        }

        $this->em->clear();

        $ok = 0;
        $fail = 0;
        foreach ($steps as $s) {
            if ($s['ok']) {
                $io->writeln(sprintf('  <fg=green>✓</> %-65s <fg=gray>%5.1f ms</>', $s['label'], $s['ms']));
                $ok++;
            } else {
                $io->writeln(sprintf('  <fg=red>✗</> %-65s %s', $s['label'], $s['error']));
                $fail++;
            }
        }

        if ($error !== null) {
            $io->error(sprintf('Step failed: %s', $error->getMessage()));
            $io->writeln($error->getTraceAsString());
            return Command::FAILURE;
        }

        $io->newLine();
        $io->success(sprintf('%d steps OK · %d KO', $ok, $fail));
        return $fail === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    private function step(string $label, \Closure $fn): array
    {
        $start = microtime(true);
        try {
            $result = $fn();
            return ['label' => $label, 'ok' => true, 'ms' => (microtime(true) - $start) * 1000, 'result' => $result];
        } catch (Throwable $t) {
            return ['label' => $label, 'ok' => false, 'error' => $t->getMessage(), 'ms' => 0, 'result' => null];
        }
    }

    /** @return array{0: Company, 1: Antenna} */
    private function createCompany(): array
    {
        $suffix = substr(bin2hex(random_bytes(4)), 0, 6);
        $company = (new Company())
            ->setName('Smoke Test SA ' . $suffix)
            ->setSlug('smoke-' . $suffix);

        $antenna = (new Antenna())
            ->setCompany($company)
            ->setName('Siège')
            ->setAddressLine('1 rue du Test')
            ->setPostalCode('75001')
            ->setCity('Paris');

        $this->em->persist($company);
        $this->em->persist($antenna);
        $this->em->flush();
        return [$company, $antenna];
    }

    /** @return array{0: User, 1: User, 2: User} */
    private function createUsers(Company $company): array
    {
        $suffix = substr(bin2hex(random_bytes(4)), 0, 6);

        $admin = (new User())
            ->setEmail("smoke-admin-$suffix@afdal.test")
            ->setFullName('Admin Smoke')
            ->setRole(UserRole::ADMIN);
        $admin->setPassword($this->hasher->hashPassword($admin, 'password123'));

        $owner = (new User())
            ->setEmail("smoke-owner-$suffix@client.test")
            ->setFullName('Owner Smoke')
            ->setRole(UserRole::CLIENT_MANAGER)
            ->setCompany($company)
            ->setCompanyRole(CompanyRole::OWNER);
        $owner->setPassword($this->hasher->hashPassword($owner, 'password123'));

        $member = (new User())
            ->setEmail("smoke-member-$suffix@client.test")
            ->setFullName('Member Smoke')
            ->setRole(UserRole::CLIENT_MANAGER)
            ->setCompany($company)
            ->setCompanyRole(CompanyRole::MEMBER);
        $member->setPassword($this->hasher->hashPassword($member, 'password123'));

        foreach ([$admin, $owner, $member] as $u) {
            $this->em->persist($u);
        }
        $this->em->flush();
        return [$admin, $owner, $member];
    }

    /** @return array{0: Product, 1: ProductVariant[]} */
    private function createProduct(Company $company): array
    {
        $suffix = substr(bin2hex(random_bytes(4)), 0, 6);
        $product = (new Product())
            ->setName('Polo Test ' . $suffix)
            ->setSlug('polo-test-' . $suffix)
            ->setCategory('Polo')
            ->setBasePriceCents(1500);
        $product->publish();
        $product->addAllowedCompany($company);
        $this->em->persist($product);

        $variants = [];
        foreach ([['S', 'Blanc', '#FFF'], ['M', 'Noir', '#000'], ['L', 'Blanc', '#FFF']] as $i => [$size, $color, $hex]) {
            $v = (new ProductVariant())
                ->setProduct($product)
                ->setSize($size)->setColor($color)->setColorHex($hex)
                ->setSku("SMOKE-$suffix-$size-" . strtoupper(substr($color, 0, 3)))
                ->setStock(50);
            $product->addVariant($v);
            $variants[] = $v;
        }

        // Palier volume : à partir de 20 unités → 12€
        $tier = (new PriceTier())->setProduct($product)->setMinQty(20)->setUnitPriceCents(1200);
        $this->em->persist($tier);

        // Tarif négocié pour cette entreprise : 10€ fixe
        $cp = (new CompanyPrice())->setCompany($company)->setProduct($product)->setUnitPriceCents(1000);
        $this->em->persist($cp);

        $this->em->flush();
        return [$product, $variants];
    }

    private function checkPricing(Product $product, Company $company): void
    {
        $negotiated = $this->pricing->resolveUnitPrice($product, $company, 1);
        if ($negotiated !== 1000) {
            throw new \RuntimeException("CompanyPrice should win (expected 1000, got $negotiated)");
        }
        // Sans company : palier volume doit s'appliquer pour qty ≥ 20
        $tierPrice = $this->pricing->resolveUnitPrice($product, null, 25);
        if ($tierPrice !== 1200) {
            throw new \RuntimeException("Tier should apply (expected 1200, got $tierPrice)");
        }
        $basePrice = $this->pricing->resolveUnitPrice($product, null, 5);
        if ($basePrice !== 1500) {
            throw new \RuntimeException("Base should apply (expected 1500, got $basePrice)");
        }
    }

    private function createFavorite(User $user, Product $product): void
    {
        $this->em->persist(new Favorite($user, $product));
        $this->em->flush();
    }

    /** @param ProductVariant[] $variants */
    private function createOrder(Company $company, Antenna $antenna, User $owner, array $variants): Order
    {
        $order = (new Order())
            ->setReference('CMD-TEST-' . substr(bin2hex(random_bytes(3)), 0, 6))
            ->setCompany($company)
            ->setAntenna($antenna)
            ->setCreatedBy($owner)
            ->setStatus(OrderStatus::PLACED)
            ->setPlacedAt(new \DateTimeImmutable());

        foreach ($variants as $i => $v) {
            $qty = [10, 5, 8][$i];
            $item = (new OrderItem())
                ->setVariant($v)
                ->setQuantity($qty)
                ->setUnitPriceCents($this->pricing->resolveUnitPrice($v->getProduct(), $company, $qty))
                ->setMarking(['zone' => 'poitrine', 'size' => 'A4']);
            $order->addItem($item);
        }

        $this->em->persist($order);
        $this->em->flush();

        // BAT upload simulé sur le premier item
        $firstItem = $order->getItems()->first();
        $bat = new MarkingAsset($firstItem, $owner, '/uploads/markings/smoke-fake.png', 1);
        $this->em->persist($bat);
        $this->em->flush();

        $latest = $this->em->getRepository(MarkingAsset::class)->findOneBy(['orderItem' => $firstItem], ['version' => 'DESC']);
        if (!$latest || $latest->getStatus() !== MarkingStatus::PENDING) {
            throw new \RuntimeException('BAT pending state not applied');
        }
        if ($order->getTotalCents() !== 23 * 1000) {
            throw new \RuntimeException('Order total mismatched: expected 23000, got ' . $order->getTotalCents());
        }
        return $order;
    }

    private function approveBat(Order $order, User $admin): void
    {
        $firstItem = $order->getItems()->first();
        $bat = $this->em->getRepository(MarkingAsset::class)->findOneBy(['orderItem' => $firstItem], ['version' => 'DESC']);
        if (!$bat) {
            throw new \RuntimeException('No BAT found for approval');
        }
        $bat->approve($admin);
        $this->em->flush();
        if (!$bat->isApproved()) {
            throw new \RuntimeException('Approve did not flip status');
        }
    }

    private function updateShipping(Order $order, User $admin): void
    {
        $order->setCarrier('Chronopost')
            ->setTrackingNumber('SMOKE123456789')
            ->setEstimatedDeliveryAt(new \DateTimeImmutable('+3 days'))
            ->setStatus(OrderStatus::CONFIRMED);
        $this->em->flush();

        $order->setStatus(OrderStatus::IN_PRODUCTION);
        $this->em->flush();

        $order->setStatus(OrderStatus::SHIPPED);
        $this->em->flush();

        if ($order->getShippedAt() === null) {
            throw new \RuntimeException('shippedAt should be auto-set by status transition');
        }
        if ($order->getTrackingUrl() === null) {
            throw new \RuntimeException('Tracking URL should be generated for Chronopost');
        }
    }

    private function exchangeMessages(Order $order, User $owner, User $admin): void
    {
        $this->em->persist(new OrderMessage($order, $owner, 'Bonjour, votre commande est bien reçue ?'));
        $this->em->persist(new OrderMessage($order, $admin, 'Oui, on démarre la production ce jour.'));
        $this->em->flush();
        if (count($this->em->getRepository(OrderMessage::class)->findBy(['order' => $order])) < 2) {
            throw new \RuntimeException('Messages not persisted');
        }
    }

    private function inviteTeamMember(User $owner): void
    {
        if (!$owner->isCompanyOwner()) {
            throw new \RuntimeException('Owner should have OWNER role');
        }
        $invite = (new Invitation())
            ->setEmail('smoke-invitee-' . bin2hex(random_bytes(2)) . '@client.test')
            ->setCompany($owner->getCompany())
            ->setCompanyRole(CompanyRole::MEMBER);
        $this->em->persist($invite);
        $this->em->flush();
        if (!$invite->isPending()) {
            throw new \RuntimeException('Invitation should be pending');
        }
    }
}
