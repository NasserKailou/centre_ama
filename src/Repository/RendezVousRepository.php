<?php

namespace App\Repository;

use App\Entity\RendezVous;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RendezVous>
 */
class RendezVousRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RendezVous::class);
    }

    public function findByFilters(
        \DateTime $dateDebut,
        \DateTime $dateFin,
        ?int $medecinId = null,
        ?string $statut = null
    ): array {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.patient', 'p')
            ->leftJoin('r.medecin', 'm')
            ->addSelect('p', 'm')
            ->where('r.dateHeure >= :debut')
            ->andWhere('r.dateHeure <= :fin')
            ->setParameter('debut', $dateDebut->format('Y-m-d 00:00:00'))
            ->setParameter('fin',   $dateFin->format('Y-m-d 23:59:59'))
            ->orderBy('r.dateHeure', 'ASC');

        if ($medecinId) {
            $qb->andWhere('m.id = :mid')->setParameter('mid', $medecinId);
        }
        if ($statut) {
            $qb->andWhere('r.statut = :statut')->setParameter('statut', $statut);
        }

        return $qb->getQuery()->getResult();
    }

    public function findByMedecinAndDate(User $medecin, \DateTime $date): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.patient', 'p')->addSelect('p')
            ->where('r.medecin = :med')
            ->andWhere('r.dateHeure >= :debut')
            ->andWhere('r.dateHeure <= :fin')
            ->andWhere('r.statut != :annule')
            ->setParameter('med',    $medecin)
            ->setParameter('debut',  $date->format('Y-m-d 00:00:00'))
            ->setParameter('fin',    $date->format('Y-m-d 23:59:59'))
            ->setParameter('annule', 'annule')
            ->orderBy('r.dateHeure', 'ASC')
            ->getQuery()->getResult();
    }

    public function findUpcoming(int $limit = 10): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.patient', 'p')->addSelect('p')
            ->leftJoin('r.medecin', 'm')->addSelect('m')
            ->where('r.dateHeure >= :now')
            ->andWhere('r.statut IN (:statuts)')
            ->setParameter('now',    new \DateTime())
            ->setParameter('statuts', ['planifie', 'confirme'])
            ->orderBy('r.dateHeure', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    public function countByDate(\DateTime $date): int
    {
        return (int)$this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.dateHeure >= :debut')
            ->andWhere('r.dateHeure <= :fin')
            ->setParameter('debut', $date->format('Y-m-d 00:00:00'))
            ->setParameter('fin',   $date->format('Y-m-d 23:59:59'))
            ->getQuery()->getSingleScalarResult();
    }

    public function findByPatient(int $patientId): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.medecin', 'm')->addSelect('m')
            ->where('r.patient = :pid')
            ->setParameter('pid', $patientId)
            ->orderBy('r.dateHeure', 'DESC')
            ->getQuery()->getResult();
    }
}
