<?php

namespace App\Entity;

use App\Repository\AntennaRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AntennaRepository::class)]
#[ORM\Table(name: 'antennas')]
class Antenna
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'antennas')]
    #[ORM\JoinColumn(nullable: false)]
    private Company $company;

    #[ORM\Column(length: 180)]
    private string $name;

    #[ORM\Column(type: 'text')]
    private string $addressLine;

    #[ORM\Column(length: 20)]
    private string $postalCode;

    #[ORM\Column(length: 120)]
    private string $city;

    #[ORM\Column(length: 2, options: ['default' => 'FR'])]
    private string $country = 'FR';

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getCompany(): Company { return $this->company; }
    public function setCompany(Company $company): self { $this->company = $company; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function getAddressLine(): string { return $this->addressLine; }
    public function setAddressLine(string $v): self { $this->addressLine = $v; return $this; }
    public function getPostalCode(): string { return $this->postalCode; }
    public function setPostalCode(string $v): self { $this->postalCode = $v; return $this; }
    public function getCity(): string { return $this->city; }
    public function setCity(string $v): self { $this->city = $v; return $this; }
    public function getCountry(): string { return $this->country; }
    public function setCountry(string $v): self { $this->country = $v; return $this; }
    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $v): self { $this->phone = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getFullAddress(): string
    {
        return sprintf('%s, %s %s', $this->addressLine, $this->postalCode, $this->city);
    }
}
