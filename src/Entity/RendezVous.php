<?php

namespace App\Entity;

use App\Repository\RendezVousRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RendezVousRepository::class)]
#[ORM\Table(name: 'rendez_vous')]
class RendezVous
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Patient::class, inversedBy: 'rendezVous')]
    #[ORM\JoinColumn(nullable: false)]
    private Patient $patient;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $medecin;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $dateHeure;

    #[ORM\Column(type: 'text')]
    private string $motif;

    #[ORM\Column(length: 20, options: ['default' => 'planifie'])]
    private string $statut = 'planifie';

    #[ORM\Column(nullable: true)]
    private ?int $duree = 30;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getPatient(): Patient { return $this->patient; }
    public function setPatient(Patient $v): static { $this->patient = $v; return $this; }

    public function getMedecin(): User { return $this->medecin; }
    public function setMedecin(User $v): static { $this->medecin = $v; return $this; }

    public function getDateHeure(): \DateTimeInterface { return $this->dateHeure; }
    public function setDateHeure(\DateTimeInterface $v): static { $this->dateHeure = $v; return $this; }

    public function getMotif(): string { return $this->motif; }
    public function setMotif(string $v): static { $this->motif = $v; return $this; }

    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $v): static { $this->statut = $v; return $this; }

    public function getDuree(): ?int { return $this->duree; }
    public function setDuree(?int $v): static { $this->duree = $v; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): static { $this->notes = $v; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $v): static { $this->createdAt = $v; return $this; }
}
