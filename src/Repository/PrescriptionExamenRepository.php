<?php

namespace App\Repository;

use App\Entity\PrescriptionExamen;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PrescriptionExamen>
 */
class PrescriptionExamenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PrescriptionExamen::class);
    }

    public function findByMedecinAndStatut(User $medecin, string $statut): array
    {
        return $this->createQueryBuilder('pe')
            ->leftJoin('pe.consultation', 'c')
            ->leftJoin('c.patient', 'p')
            ->addSelect('c', 'p')
            ->where('c.medecin = :med')
            ->andWhere('pe.statut = :statut')
            ->setParameter('med',    $medecin)
            ->setParameter('statut', $statut)
            ->orderBy('pe.createdAt', 'ASC')
            ->getQuery()->getResult();
    }

    public function findByConsultation(int $consultationId): array
    {
        return $this->createQueryBuilder('pe')
            ->where('pe.consultation = :cid')
            ->setParameter('cid', $consultationId)
            ->orderBy('pe.id', 'ASC')
            ->getQuery()->getResult();
    }

    public function findPending(): array
    {
        return $this->createQueryBuilder('pe')
            ->leftJoin('pe.consultation', 'c')
            ->leftJoin('c.patient', 'p')
            ->leftJoin('c.medecin', 'm')
            ->addSelect('c', 'p', 'm')
            ->where('pe.statut = :statut')
            ->setParameter('statut', 'prescrit')
            ->orderBy('pe.createdAt', 'ASC')
            ->getQuery()->getResult();
    }
}
