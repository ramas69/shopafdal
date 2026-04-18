<?php

namespace App\Entity;

use App\Enum\ProductStatus;
use App\Repository\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table(name: 'products')]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $name;

    #[ORM\Column(length: 180, unique: true)]
    private string $slug;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $category = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $material = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $basePriceCents = 0;

    /** @var string[] */
    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $images = [];

    #[ORM\Column(enumType: ProductStatus::class, options: ['default' => 'draft'])]
    private ProductStatus $status = ProductStatus::DRAFT;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, ProductVariant> */
    #[ORM\OneToMany(targetEntity: ProductVariant::class, mappedBy: 'product', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $variants;

    public function __construct()
    {
        $this->variants = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $v): self { $this->name = $v; return $this; }
    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $v): self { $this->slug = $v; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): self { $this->description = $v; return $this; }
    public function getCategory(): ?string { return $this->category; }
    public function setCategory(?string $v): self { $this->category = $v; return $this; }
    public function getMaterial(): ?string { return $this->material; }
    public function setMaterial(?string $v): self { $this->material = $v; return $this; }
    public function getBasePriceCents(): int { return $this->basePriceCents; }
    public function setBasePriceCents(int $v): self { $this->basePriceCents = $v; return $this; }
    public function getImages(): array { return $this->images; }
    public function setImages(array $v): self { $this->images = array_values($v); return $this; }
    public function getStatus(): ProductStatus { return $this->status; }
    public function getPublishedAt(): ?\DateTimeImmutable { return $this->publishedAt; }
    public function isPublished(): bool { return $this->status === ProductStatus::PUBLISHED; }
    public function publish(): self
    {
        $this->status = ProductStatus::PUBLISHED;
        $this->publishedAt ??= new \DateTimeImmutable();
        return $this;
    }
    public function unpublish(): self
    {
        $this->status = ProductStatus::DRAFT;
        return $this;
    }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getVariants(): Collection { return $this->variants; }

    public function addVariant(ProductVariant $variant): self
    {
        if (!$this->variants->contains($variant)) {
            $this->variants->add($variant);
            $variant->setProduct($this);
        }
        return $this;
    }
}
