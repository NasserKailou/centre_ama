<?php

namespace App\Entity;

use App\Repository\PrescriptionExamenRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PrescriptionExamenRepository::class)]
#[ORM\Table(name: 'prescription_examen')]
class PrescriptionExamen
{
    // ─── Constantes statut ────────────────────────────────────────────────
    const STATUT_PRESCRIT       = 'prescrit';
    const STATUT_EN_COURS       = 'en_cours';
    const STATUT_RESULTAT_SAISI = 'resultat_saisi';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Consultation::class, inversedBy: 'examens')]
    #[ORM\JoinColumn(nullable: false)]
    private Consultation $consultation;

    /**
     * Lien vers l'acte médical de type examen (nullable pour rétrocompatibilité).
     * Si null, on utilise typeExamen (champ texte libre).
     */
    #[ORM\ManyToOne(targetEntity: ActeMedical::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?ActeMedical $acteMedical = null;

    #[ORM\Column(length: 200)]
    private string $typeExamen = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $instructions = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $resultat = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $valeursReference = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $observations = null;

    #[ORM\Column(length: 20, options: ['default' => 'prescrit'])]
    private string $statut = 'prescrit'; // prescrit, en_cours, resultat_saisi

    #[ORM\Column(nullable: true)]
    private ?\DateTimeInterface $dateResultat = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getConsultation(): Consultation { return $this->consultation; }
    public function setConsultation(Consultation $v): static { $this->consultation = $v; return $this; }

    public function getActeMedical(): ?ActeMedical { return $this->acteMedical; }
    public function setActeMedical(?ActeMedical $v): static
    {
        $this->acteMedical = $v;
        // Synchronise le champ texte si l'acte est fourni
        if ($v) {
            $this->typeExamen = $v->getDesignation();
        }
        return $this;
    }

    public function getTypeExamen(): string { return $this->typeExamen; }
    public function setTypeExamen(string $v): static { $this->typeExamen = $v; return $this; }

    public function getInstructions(): ?string { return $this->instructions; }
    public function setInstructions(?string $v): static { $this->instructions = $v; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): static { $this->notes = $v; return $this; }

    public function getResultat(): ?string { return $this->resultat; }
    public function setResultat(?string $v): static { $this->resultat = $v; return $this; }

    public function getValeursReference(): ?string { return $this->valeursReference; }
    public function setValeursReference(?string $v): static { $this->valeursReference = $v; return $this; }

    public function getObservations(): ?string { return $this->observations; }
    public function setObservations(?string $v): static { $this->observations = $v; return $this; }

    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $v): static { $this->statut = $v; return $this; }

    public function getDateResultat(): ?\DateTimeInterface { return $this->dateResultat; }
    public function setDateResultat(?\DateTimeInterface $v): static { $this->dateResultat = $v; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
