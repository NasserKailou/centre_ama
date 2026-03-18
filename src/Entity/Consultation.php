<?php

namespace App\Entity;

use App\Repository\ConsultationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ConsultationRepository::class)]
#[ORM\Table(name: 'consultation')]
class Consultation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Patient::class, inversedBy: 'consultations')]
    #[ORM\JoinColumn(nullable: false)]
    private Patient $patient;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $medecin;

    #[ORM\ManyToOne(targetEntity: RendezVous::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?RendezVous $rendezVous = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $dateHeure;

    #[ORM\Column(type: 'text')]
    private string $motif;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $anamnese = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $examenClinique = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $diagnostic = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $traitement = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $observations = null;

    // Constantes vitales
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $tension = null;

    #[ORM\Column(nullable: true)]
    private ?float $temperature = null;

    #[ORM\Column(nullable: true)]
    private ?float $poids = null;

    #[ORM\Column(nullable: true)]
    private ?float $taille = null;

    #[ORM\Column(nullable: true)]
    private ?int $frequenceCardiaque = null;

    #[ORM\Column(length: 20, options: ['default' => 'planifiee'])]
    private string $statut = 'planifiee';

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\OneToMany(mappedBy: 'consultation', targetEntity: PrescriptionExamen::class, cascade: ['persist', 'remove'])]
    private Collection $examens;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->examens   = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getPatient(): Patient { return $this->patient; }
    public function setPatient(Patient $v): static { $this->patient = $v; return $this; }

    public function getMedecin(): User { return $this->medecin; }
    public function setMedecin(User $v): static { $this->medecin = $v; return $this; }

    public function getRendezVous(): ?RendezVous { return $this->rendezVous; }
    public function setRendezVous(?RendezVous $v): static { $this->rendezVous = $v; return $this; }

    public function getDateHeure(): \DateTimeInterface { return $this->dateHeure; }
    public function setDateHeure(\DateTimeInterface $v): static { $this->dateHeure = $v; return $this; }

    public function getMotif(): string { return $this->motif; }
    public function setMotif(string $v): static { $this->motif = $v; return $this; }

    public function getAnamnese(): ?string { return $this->anamnese; }
    public function setAnamnese(?string $v): static { $this->anamnese = $v; return $this; }

    public function getExamenClinique(): ?string { return $this->examenClinique; }
    public function setExamenClinique(?string $v): static { $this->examenClinique = $v; return $this; }

    public function getDiagnostic(): ?string { return $this->diagnostic; }
    public function setDiagnostic(?string $v): static { $this->diagnostic = $v; return $this; }

    public function getTraitement(): ?string { return $this->traitement; }
    public function setTraitement(?string $v): static { $this->traitement = $v; return $this; }

    public function getObservations(): ?string { return $this->observations; }
    public function setObservations(?string $v): static { $this->observations = $v; return $this; }

    public function getTension(): ?string { return $this->tension; }
    public function setTension(?string $v): static { $this->tension = $v; return $this; }

    public function getTemperature(): ?float { return $this->temperature; }
    public function setTemperature(?float $v): static { $this->temperature = $v; return $this; }

    public function getPoids(): ?float { return $this->poids; }
    public function setPoids(?float $v): static { $this->poids = $v; return $this; }

    public function getTaille(): ?float { return $this->taille; }
    public function setTaille(?float $v): static { $this->taille = $v; return $this; }

    public function getFrequenceCardiaque(): ?int { return $this->frequenceCardiaque; }
    public function setFrequenceCardiaque(?int $v): static { $this->frequenceCardiaque = $v; return $this; }

    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $v): static { $this->statut = $v; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }

    public function getExamens(): Collection { return $this->examens; }
    public function addExamen(PrescriptionExamen $e): static
    {
        if (!$this->examens->contains($e)) {
            $this->examens->add($e);
            $e->setConsultation($this);
        }
        return $this;
    }
    public function removeExamen(PrescriptionExamen $e): static
    {
        $this->examens->removeElement($e);
        return $this;
    }

    public function getImc(): ?float
    {
        if ($this->poids && $this->taille && $this->taille > 0) {
            $tailleM = $this->taille / 100;
            return round($this->poids / ($tailleM * $tailleM), 1);
        }
        return null;
    }
}
