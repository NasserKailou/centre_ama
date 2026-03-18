<?php

namespace App\Entity;

use App\Repository\ActeMedicalRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ActeMedicalRepository::class)]
#[ORM\Table(name: 'acte_medical')]
class ActeMedical
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200)]
    private string $designation;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $code = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $categorie = null; // consultation, examen, traitement, chirurgie

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => 0])]
    private float $prixNormal = 0;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => 0])]
    private float $prixPrisEnCharge = 0;

    #[ORM\Column]
    private bool $actif = true;

    public function getId(): ?int { return $this->id; }

    public function getDesignation(): string { return $this->designation; }
    public function setDesignation(string $v): static { $this->designation = $v; return $this; }

    public function getCode(): ?string { return $this->code; }
    public function setCode(?string $v): static { $this->code = $v; return $this; }

    public function getCategorie(): ?string { return $this->categorie; }
    public function setCategorie(?string $v): static { $this->categorie = $v; return $this; }

    public function getPrixNormal(): float { return (float)$this->prixNormal; }
    public function setPrixNormal(float $v): static { $this->prixNormal = $v; return $this; }

    public function getPrixPrisEnCharge(): float { return (float)$this->prixPrisEnCharge; }
    public function setPrixPrisEnCharge(float $v): static { $this->prixPrisEnCharge = $v; return $this; }

    public function isActif(): bool { return $this->actif; }
    public function setActif(bool $v): static { $this->actif = $v; return $this; }

    // ─── Alias de compatibilité ────────────────────────────────────────────

    /**
     * Alias de getDesignation() — utilisé dans certains templates / contrôleurs
     */
    public function getLibelle(): string { return $this->designation; }

    /**
     * Alias de getPrixPrisEnCharge() — orthographe alternative
     */
    public function getPrixPriseEnCharge(): float { return (float)$this->prixPrisEnCharge; }

    /**
     * Alias de getCategorie() — utilisé dans les anciens appels ->type
     */
    public function getType(): ?string { return $this->categorie; }

    public function __toString(): string { return $this->designation; }
}
