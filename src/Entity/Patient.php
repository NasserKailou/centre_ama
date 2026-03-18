<?php

namespace App\Entity;

use App\Repository\PatientRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PatientRepository::class)]
#[ORM\Table(name: 'patient')]
class Patient
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30, unique: true)]
    private string $numeroDossier;

    #[ORM\Column(length: 100)]
    private string $nom;

    #[ORM\Column(length: 100)]
    private string $prenom;

    #[ORM\Column(length: 20)]
    private string $telephone;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateNaissance = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $sexe = null;

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $groupeSanguin = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $adresse = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $contactUrgence = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $allergies = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $antecedentsMedicaux = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $antecedentsChirurgicaux = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $antecedentsFamiliaux = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $profession = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $assurance = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $numeroAssurance = null;

    #[ORM\ManyToOne(targetEntity: Partenaire::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Partenaire $partenaire = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToMany(mappedBy: 'patient', targetEntity: Consultation::class, cascade: ['persist'])]
    #[ORM\OrderBy(['dateHeure' => 'DESC'])]
    private Collection $consultations;

    #[ORM\OneToMany(mappedBy: 'patient', targetEntity: RendezVous::class, cascade: ['persist'])]
    private Collection $rendezVous;

    #[ORM\OneToMany(mappedBy: 'patient', targetEntity: FactureGlobale::class, cascade: ['persist'])]
    private Collection $factures;

    public function __construct()
    {
        $this->createdAt    = new \DateTimeImmutable();
        $this->consultations= new ArrayCollection();
        $this->rendezVous   = new ArrayCollection();
        $this->factures     = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getNumeroDossier(): string { return $this->numeroDossier; }
    public function setNumeroDossier(string $v): static { $this->numeroDossier = $v; return $this; }

    public function getNom(): string { return $this->nom; }
    public function setNom(string $v): static { $this->nom = $v; return $this; }

    public function getPrenom(): string { return $this->prenom; }
    public function setPrenom(string $v): static { $this->prenom = $v; return $this; }

    public function getTelephone(): string { return $this->telephone; }
    public function setTelephone(string $v): static { $this->telephone = $v; return $this; }

    public function getDateNaissance(): ?\DateTimeInterface { return $this->dateNaissance; }
    public function setDateNaissance(?\DateTimeInterface $v): static { $this->dateNaissance = $v; return $this; }

    public function getSexe(): ?string { return $this->sexe; }
    public function setSexe(?string $v): static { $this->sexe = $v; return $this; }

    public function getGroupeSanguin(): ?string { return $this->groupeSanguin; }
    public function setGroupeSanguin(?string $v): static { $this->groupeSanguin = $v; return $this; }

    public function getAdresse(): ?string { return $this->adresse; }
    public function setAdresse(?string $v): static { $this->adresse = $v; return $this; }

    public function getContactUrgence(): ?string { return $this->contactUrgence; }
    public function setContactUrgence(?string $v): static { $this->contactUrgence = $v; return $this; }

    public function getAllergies(): ?string { return $this->allergies; }
    public function setAllergies(?string $v): static { $this->allergies = $v; return $this; }

    public function getAntecedentsMedicaux(): ?string { return $this->antecedentsMedicaux; }
    public function setAntecedentsMedicaux(?string $v): static { $this->antecedentsMedicaux = $v; return $this; }

    public function getAntecedentsChirurgicaux(): ?string { return $this->antecedentsChirurgicaux; }
    public function setAntecedentsChirurgicaux(?string $v): static { $this->antecedentsChirurgicaux = $v; return $this; }

    public function getAntecedentsFamiliaux(): ?string { return $this->antecedentsFamiliaux; }
    public function setAntecedentsFamiliaux(?string $v): static { $this->antecedentsFamiliaux = $v; return $this; }

    public function getProfession(): ?string { return $this->profession; }
    public function setProfession(?string $v): static { $this->profession = $v; return $this; }

    public function getAssurance(): ?string { return $this->assurance; }
    public function setAssurance(?string $v): static { $this->assurance = $v; return $this; }

    public function getNumeroAssurance(): ?string { return $this->numeroAssurance; }
    public function setNumeroAssurance(?string $v): static { $this->numeroAssurance = $v; return $this; }

    public function getPartenaire(): ?Partenaire { return $this->partenaire; }
    public function setPartenaire(?Partenaire $v): static { $this->partenaire = $v; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getConsultations(): Collection { return $this->consultations; }
    public function getRendezVous(): Collection { return $this->rendezVous; }
    public function getFactures(): Collection { return $this->factures; }

    public function getNomComplet(): string { return $this->prenom . ' ' . $this->nom; }

    public function getAge(): ?int
    {
        if (!$this->dateNaissance) return null;
        return (new \DateTime())->diff($this->dateNaissance)->y;
    }

    public function __toString(): string { return $this->getNomComplet(); }
}
