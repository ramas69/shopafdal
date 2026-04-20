<?php

namespace App\Tests\Functional;

use App\Entity\Antenna;
use App\Entity\Company;
use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\CompanyRole;
use App\Enum\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Helpers pour les tests fonctionnels — dama/doctrine-test-bundle rollback après chaque test.
 */
trait TestDataTrait
{
    protected function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function hasher(): UserPasswordHasherInterface
    {
        return static::getContainer()->get(UserPasswordHasherInterface::class);
    }

    protected function createCompanyWithAntenna(string $name = 'Test SA'): array
    {
        $suffix = bin2hex(random_bytes(3));
        $company = (new Company())->setName("$name $suffix")->setSlug("test-$suffix");
        $antenna = (new Antenna())
            ->setCompany($company)
            ->setName('Siège')
            ->setAddressLine('1 rue du Test')
            ->setPostalCode('75001')
            ->setCity('Paris');
        $this->em()->persist($company);
        $this->em()->persist($antenna);
        $this->em()->flush();
        return [$company, $antenna];
    }

    protected function createUser(string $role, ?Company $company = null, ?CompanyRole $companyRole = null, string $password = 'password123'): User
    {
        $suffix = bin2hex(random_bytes(3));
        $user = (new User())
            ->setEmail("test-$suffix@afdal.test")
            ->setFullName("Test $role")
            ->setRole($role === 'admin' ? UserRole::ADMIN : UserRole::CLIENT_MANAGER)
            ->setCompany($company)
            ->setCompanyRole($companyRole);
        $user->setPassword($this->hasher()->hashPassword($user, $password));
        $this->em()->persist($user);
        $this->em()->flush();
        return $user;
    }

    protected function createProduct(string $name = 'T-shirt Test', int $priceCents = 1500, int $stock = 50): Product
    {
        $suffix = bin2hex(random_bytes(3));
        $product = (new Product())
            ->setName("$name $suffix")
            ->setSlug("test-$suffix")
            ->setCategory('T-shirt')
            ->setBasePriceCents($priceCents);
        $product->publish();
        $this->em()->persist($product);

        foreach ([['M', 'Blanc', '#FFF'], ['L', 'Noir', '#000']] as [$size, $color, $hex]) {
            $v = (new ProductVariant())
                ->setProduct($product)
                ->setSize($size)->setColor($color)->setColorHex($hex)
                ->setSku("TEST-$suffix-$size")
                ->setStock($stock);
            $product->addVariant($v);
        }
        $this->em()->flush();
        return $product;
    }
}
