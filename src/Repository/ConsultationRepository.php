<?php

namespace App\Repository;

use App\Entity\Consultation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Consultation>
 */
class ConsultationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Consultation::class);
    }

    /**
     * QueryBuilder filtré pour KnpPaginator.
     */
    public function getFilteredQueryBuilder(
        ?string $search   = null,
        ?string $date     = null,
        ?string $statut   = null,
        ?User   $medecin  = null
    ): QueryBuilder {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.patient', 'p')
            ->leftJoin('c.medecin', 'm')
            ->addSelect('p', 'm')
            ->orderBy('c.dateHeure', 'DESC');

        // Restriction médecin si pas admin
        if ($medecin && in_array('ROLE_MEDECIN', $medecin->getRoles())
            && !in_array('ROLE_ADMIN', $medecin->getRoles())) {
            $qb->andWhere('c.medecin = :medecin')->setParameter('medecin', $medecin);
        }
        if ($search) {
            $qb->andWhere('p.nom LIKE :s OR p.prenom LIKE :s OR p.telephone LIKE :s')
               ->setParameter('s', '%' . $search . '%');
        }
        if ($date) {
            $qb->andWhere('c.dateHeure >= :d AND c.dateHeure <= :d2')
               ->setParameter('d',  $date . ' 00:00:00')
               ->setParameter('d2', $date . ' 23:59:59');
        }
        if ($statut) {
            $qb->andWhere('c.statut = :statut')->setParameter('statut', $statut);
        }
        return $qb;
    }

    public function findRecentByMedecin(?User $medecin, int $limit = 10): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.patient', 'p')
            ->leftJoin('c.medecin', 'm')
            ->addSelect('p', 'm')
            ->orderBy('c.dateHeure', 'DESC')
            ->setMaxResults($limit);

        if ($medecin && in_array('ROLE_MEDECIN', $medecin->getRoles())) {
            $qb->where('c.medecin = :med')->setParameter('med', $medecin);
        }

        return $qb->getQuery()->getResult();
    }

    public function findByMedecin(User $medecin, int $limit = 10): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.patient', 'p')->addSelect('p')
            ->where('c.medecin = :med')
            ->setParameter('med', $medecin)
            ->orderBy('c.dateHeure', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    public function findByPatient(int $patientId): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.medecin', 'm')->addSelect('m')
            ->where('c.patient = :pid')
            ->setParameter('pid', $patientId)
            ->orderBy('c.dateHeure', 'DESC')
            ->getQuery()->getResult();
    }

    public function countByDate(\DateTime $date): int
    {
        return (int)$this->createQueryBuilder('c')
            ->select('COUNT(DISTINCT c.patient)')
            ->where('c.dateHeure >= :debut')
            ->andWhere('c.dateHeure <= :fin')
            ->setParameter('debut', $date->format('Y-m-d 00:00:00'))
            ->setParameter('fin',   $date->format('Y-m-d 23:59:59'))
            ->getQuery()->getSingleScalarResult();
    }

    public function countByMedecinAndDate(User $medecin, \DateTime $date): int
    {
        return (int)$this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.medecin = :med')
            ->andWhere('c.dateHeure >= :debut')
            ->andWhere('c.dateHeure <= :fin')
            ->setParameter('med',   $medecin)
            ->setParameter('debut', $date->format('Y-m-d 00:00:00'))
            ->setParameter('fin',   $date->format('Y-m-d 23:59:59'))
            ->getQuery()->getSingleScalarResult();
    }

    public function countByMedecinAndPeriode(User $medecin, \DateTime $debut, \DateTime $fin): int
    {
        return (int)$this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.medecin = :med')
            ->andWhere('c.dateHeure >= :debut')
            ->andWhere('c.dateHeure <= :fin')
            ->setParameter('med',   $medecin)
            ->setParameter('debut', $debut->format('Y-m-d 00:00:00'))
            ->setParameter('fin',   $fin->format('Y-m-d 23:59:59'))
            ->getQuery()->getSingleScalarResult();
    }

    public function countPatientsMoisByMedecin(User $medecin): int
    {
        $debut = new \DateTime('first day of this month');
        $fin   = new \DateTime('last day of this month');
        return (int)$this->createQueryBuilder('c')
            ->select('COUNT(DISTINCT c.patient)')
            ->where('c.medecin = :med')
            ->andWhere('c.dateHeure >= :debut')
            ->andWhere('c.dateHeure <= :fin')
            ->setParameter('med',   $medecin)
            ->setParameter('debut', $debut->format('Y-m-d 00:00:00'))
            ->setParameter('fin',   $fin->format('Y-m-d 23:59:59'))
            ->getQuery()->getSingleScalarResult();
    }

    public function getConsultationsParMedecin(): array
    {
        return $this->createQueryBuilder('c')
            ->select('m.nom', 'm.prenom', 'COUNT(c.id) as count')
            ->leftJoin('c.medecin', 'm')
            ->where("c.dateHeure >= :debut")
            ->setParameter('debut', (new \DateTime('-30 days'))->format('Y-m-d'))
            ->groupBy('m.id')
            ->orderBy('count', 'DESC')
            ->getQuery()->getResult();
    }

    public function getConsultationsParMedecinPeriode(\DateTime $debut, \DateTime $fin): array
    {
        return $this->createQueryBuilder('c')
            ->select('m.nom', 'm.prenom', 'COUNT(c.id) as count')
            ->leftJoin('c.medecin', 'm')
            ->where('c.dateHeure >= :debut')
            ->andWhere('c.dateHeure <= :fin')
            ->setParameter('debut', $debut->format('Y-m-d 00:00:00'))
            ->setParameter('fin',   $fin->format('Y-m-d 23:59:59'))
            ->groupBy('m.id')
            ->orderBy('count', 'DESC')
            ->getQuery()->getResult();
    }

    public function countByMedecin(User $medecin): int
    {
        return (int)$this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.medecin = :med')
            ->setParameter('med', $medecin)
            ->getQuery()->getSingleScalarResult();
    }

    public function findWithFilters(
        ?string $search,
        ?string $dateStr,
        ?string $statut,
        ?int $medecinId,
        int $page = 1,
        int $limit = 20
    ): array {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.patient', 'p')
            ->leftJoin('c.medecin', 'm')
            ->addSelect('p', 'm')
            ->orderBy('c.dateHeure', 'DESC');

        if ($search) {
            $qb->andWhere('p.nom LIKE :s OR p.prenom LIKE :s OR p.telephone LIKE :s')
               ->setParameter('s', '%' . $search . '%');
        }
        if ($dateStr) {
            $qb->andWhere('c.dateHeure >= :d AND c.dateHeure <= :d2')
               ->setParameter('d',  $dateStr . ' 00:00:00')
               ->setParameter('d2', $dateStr . ' 23:59:59');
        }
        if ($statut) {
            $qb->andWhere('c.statut = :statut')->setParameter('statut', $statut);
        }
        if ($medecinId) {
            $qb->andWhere('m.id = :mid')->setParameter('mid', $medecinId);
        }

        return $qb->setFirstResult(($page - 1) * $limit)
                  ->setMaxResults($limit)
                  ->getQuery()->getResult();
    }
}
