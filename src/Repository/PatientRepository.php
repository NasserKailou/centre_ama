<?php

namespace App\Repository;

use App\Entity\Patient;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Patient>
 */
class PatientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Patient::class);
    }

    public function search(string $query): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.nom LIKE :q OR p.prenom LIKE :q OR p.telephone LIKE :q OR p.numeroDossier LIKE :q')
            ->setParameter('q', '%' . $query . '%')
            ->orderBy('p.nom', 'ASC')
            ->setMaxResults(15)
            ->getQuery()->getResult();
    }

    public function searchByPhone(string $phonePrefix): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.telephone LIKE :prefix')
            ->setParameter('prefix', $phonePrefix . '%')
            ->orderBy('p.nom', 'ASC')
            ->setMaxResults(20)
            ->getQuery()->getResult();
    }

    public function findWithFilters(
        ?string $search,
        ?string $groupe,
        int $page = 1,
        int $limit = 20
    ): array {
        $qb = $this->createQueryBuilder('p')
            ->orderBy('p.createdAt', 'DESC');

        if ($search) {
            $qb->where('p.nom LIKE :s OR p.prenom LIKE :s OR p.telephone LIKE :s OR p.numeroDossier LIKE :s')
               ->setParameter('s', '%' . $search . '%');
        }
        if ($groupe) {
            $qb->andWhere('p.groupeSanguin = :g')->setParameter('g', $groupe);
        }

        return $qb->setFirstResult(($page - 1) * $limit)
                  ->setMaxResults($limit)
                  ->getQuery()->getResult();
    }

    public function countAll(): int
    {
        return (int)$this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->getQuery()->getSingleScalarResult();
    }

    public function countThisMonth(): int
    {
        $debut = new \DateTime('first day of this month 00:00:00');
        $fin   = new \DateTime('last day of this month 23:59:59');
        return (int)$this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.createdAt >= :debut AND p.createdAt <= :fin')
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()->getSingleScalarResult();
    }

    public function generateNumeroDossier(): string
    {
        $year   = date('Y');
        $count  = $this->countAll();
        return 'DOS-' . $year . '-' . str_pad($count + 1, 5, '0', STR_PAD_LEFT);
    }
}
