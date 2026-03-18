<?php

namespace App\Entity;

use App\Repository\LigneFactureRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LigneFactureRepository::class)]
#[ORM\Table(name: 'ligne_facture')]
class LigneFacture
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: FactureGlobale::class, inversedBy: 'lignes')]
    #[ORM\JoinColumn(nullable: false)]
    private FactureGlobale $factureGlobale;

    #[ORM\Column(length: 20, options: ['default' => 'acte'])]
    private string $typeLigne = 'acte'; // acte, produit, examen

    #[ORM\ManyToOne(targetEntity: ActeMedical::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?ActeMedical $acteMedical = null;

    #[ORM\ManyToOne(targetEntity: ProduitPharmaceutique::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?ProduitPharmaceutique $produit = null;

    #[ORM\Column(length: 255)]
    private string $designation;

    #[ORM\Column]
    private int $quantite = 1;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private float $prixUnitaire = 0;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private float $sousTotal = 0;

    public function getId(): ?int { return $this->id; }

    public function getFactureGlobale(): FactureGlobale { return $this->factureGlobale; }
    public function setFactureGlobale(FactureGlobale $v): static { $this->factureGlobale = $v; return $this; }

    public function getTypeLigne(): string { return $this->typeLigne; }
    public function setTypeLigne(string $v): static { $this->typeLigne = $v; return $this; }

    public function getActeMedical(): ?ActeMedical { return $this->acteMedical; }
    public function setActeMedical(?ActeMedical $v): static { $this->acteMedical = $v; return $this; }

    public function getProduit(): ?ProduitPharmaceutique { return $this->produit; }
    public function setProduit(?ProduitPharmaceutique $v): static { $this->produit = $v; return $this; }

    public function getDesignation(): string { return $this->designation; }
    public function setDesignation(string $v): static { $this->designation = $v; return $this; }

    public function getQuantite(): int { return $this->quantite; }
    public function setQuantite(int $v): static { $this->quantite = $v; return $this; }

    public function getPrixUnitaire(): float { return (float)$this->prixUnitaire; }
    public function setPrixUnitaire(float $v): static { $this->prixUnitaire = $v; return $this; }

    public function getSousTotal(): float { return (float)$this->sousTotal; }
    public function setSousTotal(float $v): static { $this->sousTotal = $v; return $this; }

    public function calculerSousTotal(): void
    {
        $this->sousTotal = $this->quantite * $this->prixUnitaire;
    }
}
