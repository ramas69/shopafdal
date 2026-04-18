<?php

namespace App\Entity;

use App\Repository\CompanyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CompanyRepository::class)]
#[ORM\Table(name: 'companies')]
#[ORM\HasLifecycleCallbacks]
class Company
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $name;

    #[ORM\Column(length: 180, unique: true)]
    private string $slug;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $siret = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Antenna> */
    #[ORM\OneToMany(targetEntity: Antenna::class, mappedBy: 'company', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $antennas;

    /** @var Collection<int, User> */
    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'company')]
    private Collection $users;

    /** @var Collection<int, Order> */
    #[ORM\OneToMany(targetEntity: Order::class, mappedBy: 'company')]
    private Collection $orders;

    public function __construct()
    {
        $this->antennas = new ArrayCollection();
        $this->users = new ArrayCollection();
        $this->orders = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): self { $this->slug = $slug; return $this; }
    public function getSiret(): ?string { return $this->siret; }
    public function setSiret(?string $siret): self { $this->siret = $siret; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getAntennas(): Collection { return $this->antennas; }
    public function getUsers(): Collection { return $this->users; }
    public function getOrders(): Collection { return $this->orders; }

    public function addAntenna(Antenna $antenna): self
    {
        if (!$this->antennas->contains($antenna)) {
            $this->antennas->add($antenna);
            $antenna->setCompany($this);
        }
        return $this;
    }
}
