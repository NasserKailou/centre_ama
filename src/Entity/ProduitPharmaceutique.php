<?php

namespace App\Entity;

use App\Repository\ProduitPharmaceutiqueRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProduitPharmaceutiqueRepository::class)]
#[ORM\Table(name: 'produit_pharmaceutique')]
class ProduitPharmaceutique
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200)]
    private string $designation;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $dci = null; // Dénomination Commune Internationale

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $reference = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $categorie = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $forme = null; // comprimé, sirop, injection, etc.

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $dosage = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $unite = null; // boite, flacon, unité, etc.

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => 0])]
    private float $prixAchat = 0;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => 0])]
    private float $prixVente = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $stockDisponible = 0;

    #[ORM\Column(options: ['default' => 10])]
    private int $stockMinimum = 10;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $fournisseur = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $datePeremption = null;

    #[ORM\Column]
    private bool $actif = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getDesignation(): string { return $this->designation; }
    public function setDesignation(string $v): static { $this->designation = $v; return $this; }

    public function getDci(): ?string { return $this->dci; }
    public function setDci(?string $v): static { $this->dci = $v; return $this; }

    public function getReference(): ?string { return $this->reference; }
    public function setReference(?string $v): static { $this->reference = $v; return $this; }

    public function getCategorie(): ?string { return $this->categorie; }
    public function setCategorie(?string $v): static { $this->categorie = $v; return $this; }

    public function getForme(): ?string { return $this->forme; }
    public function setForme(?string $v): static { $this->forme = $v; return $this; }

    public function getDosage(): ?string { return $this->dosage; }
    public function setDosage(?string $v): static { $this->dosage = $v; return $this; }

    public function getUnite(): ?string { return $this->unite; }
    public function setUnite(?string $v): static { $this->unite = $v; return $this; }

    public function getPrixAchat(): float { return (float)$this->prixAchat; }
    public function setPrixAchat(float $v): static { $this->prixAchat = $v; return $this; }

    public function getPrixVente(): float { return (float)$this->prixVente; }
    public function setPrixVente(float $v): static { $this->prixVente = $v; return $this; }

    public function getStockDisponible(): int { return $this->stockDisponible; }
    public function setStockDisponible(int $v): static { $this->stockDisponible = $v; return $this; }

    public function getStockMinimum(): int { return $this->stockMinimum; }
    public function setStockMinimum(int $v): static { $this->stockMinimum = $v; return $this; }

    public function getFournisseur(): ?string { return $this->fournisseur; }
    public function setFournisseur(?string $v): static { $this->fournisseur = $v; return $this; }

    public function getDatePeremption(): ?\DateTimeInterface { return $this->datePeremption; }
    public function setDatePeremption(?\DateTimeInterface $v): static { $this->datePeremption = $v; return $this; }

    public function isActif(): bool { return $this->actif; }
    public function setActif(bool $v): static { $this->actif = $v; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    // ─── Alias et helpers ─────────────────────────────────────────────────

    /** Alias de getDesignation() pour la compatibilité */
    public function getNom(): string { return $this->designation; }

    /** Alias de getStockDisponible() */
    public function getStockActuel(): int { return $this->stockDisponible; }

    /** Alias de getPrixVente() */
    public function getPrix(): float { return $this->prixVente; }

    /** Nom complet avec DCI et dosage */
    public function getNomComplet(): string
    {
        $parts = [$this->designation];
        if ($this->dci && $this->dci !== $this->designation) $parts[] = '(' . $this->dci . ')';
        if ($this->dosage) $parts[] = $this->dosage;
        return implode(' ', $parts);
    }

    /** Vérifie si le stock est suffisant */
    public function isDisponible(int $quantite = 1): bool
    {
        return $this->stockDisponible >= $quantite;
    }

    /** Décrémente le stock */
    public function decrementerStock(int $quantite): void
    {
        $this->stockDisponible = max(0, $this->stockDisponible - $quantite);
    }

    /** Incrémente le stock */
    public function incrementerStock(int $quantite): void
    {
        $this->stockDisponible += $quantite;
    }

    /** Retourne le statut du stock */
    public function getStatutStock(): string
    {
        if ($this->stockDisponible <= 0) return 'rupture';
        if ($this->stockDisponible <= $this->stockMinimum) return 'critique';
        return 'normal';
    }

    public function __toString(): string { return $this->designation; }
}

