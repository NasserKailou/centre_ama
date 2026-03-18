<?php

namespace App\Entity;

use App\Repository\PartenaireRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PartenaireRepository::class)]
#[ORM\Table(name: 'partenaire')]
class Partenaire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200)]
    private string $nom;

    #[ORM\Column(length: 50, options: ['default' => 'assurance'])]
    private string $type = 'assurance'; // assurance, mutuelle, etat, entreprise, autre

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $adresse = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $contact = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $numeroContrat = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, options: ['default' => 80])]
    private float $tauxPriseEnCharge = 80;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private bool $actif = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getNom(): string { return $this->nom; }
    public function setNom(string $v): static { $this->nom = $v; return $this; }

    public function getType(): string { return $this->type; }
    public function setType(string $v): static { $this->type = $v; return $this; }

    public function getAdresse(): ?string { return $this->adresse; }
    public function setAdresse(?string $v): static { $this->adresse = $v; return $this; }

    public function getTelephone(): ?string { return $this->telephone; }
    public function setTelephone(?string $v): static { $this->telephone = $v; return $this; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $v): static { $this->email = $v; return $this; }

    public function getContact(): ?string { return $this->contact; }
    public function setContact(?string $v): static { $this->contact = $v; return $this; }

    public function getNumeroContrat(): ?string { return $this->numeroContrat; }
    public function setNumeroContrat(?string $v): static { $this->numeroContrat = $v; return $this; }

    public function getTauxPriseEnCharge(): float { return (float)$this->tauxPriseEnCharge; }
    public function setTauxPriseEnCharge(float $v): static { $this->tauxPriseEnCharge = $v; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): static { $this->description = $v; return $this; }

    public function isActif(): bool { return $this->actif; }
    public function setActif(bool $v): static { $this->actif = $v; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function __toString(): string { return $this->nom; }
}
