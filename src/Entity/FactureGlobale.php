<?php

namespace App\Entity;

use App\Repository\FactureGlobaleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FactureGlobaleRepository::class)]
#[ORM\Table(name: 'facture_globale')]
class FactureGlobale
{
    // ─── Constantes statut ────────────────────────────────────────────────
    const STATUT_PAYE       = 'paye';
    const STATUT_EN_ATTENTE = 'en_attente';
    const STATUT_PARTIEL    = 'partiel';
    const STATUT_ANNULE     = 'annule';

    // ─── Constantes mode paiement ─────────────────────────────────────────
    const MODE_CASH         = 'cash';
    const MODE_MOBILE_MONEY = 'mobile_money';
    const MODE_CHEQUE       = 'cheque';
    const MODE_VIREMENT     = 'virement';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30, unique: true)]
    private string $numero;

    #[ORM\ManyToOne(targetEntity: Patient::class, inversedBy: 'factures')]
    #[ORM\JoinColumn(nullable: false)]
    private Patient $patient;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $caissier;

    #[ORM\ManyToOne(targetEntity: Consultation::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Consultation $consultation = null;

    #[ORM\ManyToOne(targetEntity: Partenaire::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Partenaire $partenaire = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => 0])]
    private float $montantTotal = 0;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => 0])]
    private float $montantActes = 0;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => 0])]
    private float $montantPharmacie = 0;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, options: ['default' => 0])]
    private float $tauxAssurance = 0;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => 0])]
    private float $partAssurance = 0;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => 0])]
    private float $partPatient = 0;

    #[ORM\Column(length: 20, options: ['default' => 'paye'])]
    private string $statut = 'paye'; // paye, en_attente, annule

    #[ORM\Column(length: 20, options: ['default' => 'cash'])]
    private string $modePaiement = 'cash'; // cash, mobile_money, cheque, virement

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\OneToMany(mappedBy: 'factureGlobale', targetEntity: LigneFacture::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $lignes;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->lignes    = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getNumero(): string { return $this->numero; }
    public function setNumero(string $v): static { $this->numero = $v; return $this; }

    public function getPatient(): Patient { return $this->patient; }
    public function setPatient(Patient $v): static { $this->patient = $v; return $this; }

    public function getCaissier(): User { return $this->caissier; }
    public function setCaissier(User $v): static { $this->caissier = $v; return $this; }

    public function getConsultation(): ?Consultation { return $this->consultation; }
    public function setConsultation(?Consultation $v): static { $this->consultation = $v; return $this; }

    public function getPartenaire(): ?Partenaire { return $this->partenaire; }
    public function setPartenaire(?Partenaire $v): static { $this->partenaire = $v; return $this; }

    public function getMontantTotal(): float { return (float)$this->montantTotal; }
    public function setMontantTotal(float $v): static { $this->montantTotal = $v; return $this; }

    public function getMontantActes(): float { return (float)$this->montantActes; }
    public function setMontantActes(float $v): static { $this->montantActes = $v; return $this; }

    public function getMontantPharmacie(): float { return (float)$this->montantPharmacie; }
    public function setMontantPharmacie(float $v): static { $this->montantPharmacie = $v; return $this; }

    public function getTauxAssurance(): float { return (float)$this->tauxAssurance; }
    public function setTauxAssurance(float $v): static { $this->tauxAssurance = $v; return $this; }

    public function getPartAssurance(): float { return (float)$this->partAssurance; }
    public function setPartAssurance(float $v): static { $this->partAssurance = $v; return $this; }

    public function getPartPatient(): float { return (float)$this->partPatient; }
    public function setPartPatient(float $v): static { $this->partPatient = $v; return $this; }

    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $v): static { $this->statut = $v; return $this; }

    public function getModePaiement(): string { return $this->modePaiement; }
    public function setModePaiement(string $v): static { $this->modePaiement = $v; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): static { $this->notes = $v; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $v): static { $this->createdAt = $v; return $this; }

    public function getLignes(): Collection { return $this->lignes; }
    public function addLigne(LigneFacture $l): static
    {
        if (!$this->lignes->contains($l)) {
            $this->lignes->add($l);
            $l->setFactureGlobale($this);
        }
        return $this;
    }
    public function removeLigne(LigneFacture $l): static
    {
        $this->lignes->removeElement($l);
        return $this;
    }

    public function recalculerTotaux(): void
    {
        $totalActes    = 0;
        $totalPharmacie = 0;
        foreach ($this->lignes as $ligne) {
            if ($ligne->getTypeLigne() === 'produit') {
                $totalPharmacie += $ligne->getSousTotal();
            } else {
                $totalActes += $ligne->getSousTotal();
            }
        }
        $total = $totalActes + $totalPharmacie;
        $this->montantActes     = $totalActes;
        $this->montantPharmacie = $totalPharmacie;
        $this->montantTotal     = $total;
        $this->partAssurance    = round($total * ($this->tauxAssurance / 100), 2);
        $this->partPatient      = round($total - $this->partAssurance, 2);
    }
}
