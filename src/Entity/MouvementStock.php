<?php

namespace App\Entity;

use App\Repository\MouvementStockRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MouvementStockRepository::class)]
#[ORM\Table(name: 'mouvement_stock')]
class MouvementStock
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ProduitPharmaceutique::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ProduitPharmaceutique $produit;

    #[ORM\Column(length: 20)]
    private string $type; // entree, sortie, ajustement, retour

    #[ORM\Column]
    private int $quantite;

    #[ORM\Column]
    private int $stockApres;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $reference = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getProduit(): ProduitPharmaceutique { return $this->produit; }
    public function setProduit(ProduitPharmaceutique $v): static { $this->produit = $v; return $this; }

    public function getType(): string { return $this->type; }
    public function setType(string $v): static { $this->type = $v; return $this; }

    public function getQuantite(): int { return $this->quantite; }
    public function setQuantite(int $v): static { $this->quantite = $v; return $this; }

    public function getStockApres(): int { return $this->stockApres; }
    public function setStockApres(int $v): static { $this->stockApres = $v; return $this; }

    public function getReference(): ?string { return $this->reference; }
    public function setReference(?string $v): static { $this->reference = $v; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): static { $this->notes = $v; return $this; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $v): static { $this->user = $v; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
