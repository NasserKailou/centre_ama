<?php

namespace App\Repository;

use App\Entity\Partenaire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Partenaire>
 */
class PartenaireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Partenaire::class);
    }

    public function findActifs(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.actif = 1')
            ->orderBy('p.nom', 'ASC')
            ->getQuery()->getResult();
    }

    /**
     * Bilan par période – utilise DQL pour éviter les problèmes
     * de paramètres nommés avec le driver MariaDB/MySQL DBAL.
     */
    public function getBilanPeriode(\DateTime $debut, \DateTime $fin): array
    {
        // On récupère tous les partenaires actifs
        $partenaires = $this->findActifs();
        $result = [];

        foreach ($partenaires as $p) {
            $row = $this->getEntityManager()->createQueryBuilder()
                ->select(
                    'COUNT(DISTINCT f.patient) as nbPatients',
                    'COALESCE(SUM(f.montantTotal), 0)  as montantBrut',
                    'COALESCE(SUM(f.partAssurance), 0) as partAssurance',
                    'COALESCE(SUM(f.partPatient), 0)   as restePatient'
                )
                ->from('App\Entity\FactureGlobale', 'f')
                ->where('f.partenaire = :partenaire')
                ->andWhere('f.createdAt >= :debut AND f.createdAt <= :fin')
                ->setParameter('partenaire', $p)
                ->setParameter('debut', $debut->format('Y-m-d 00:00:00'))
                ->setParameter('fin',   $fin->format('Y-m-d 23:59:59'))
                ->getQuery()->getSingleResult();

            $result[] = [
                'nom'           => $p->getNom(),
                'type'          => $p->getType(),
                'nbPatients'    => (int)($row['nbPatients']    ?? 0),
                'montantBrut'   => (float)($row['montantBrut']    ?? 0),
                'partAssurance' => (float)($row['partAssurance']  ?? 0),
                'restePatient'  => (float)($row['restePatient']   ?? 0),
                'statut'        => 'en_attente',
            ];
        }

        // Tri par partAssurance décroissant
        usort($result, fn($a, $b) => $b['partAssurance'] <=> $a['partAssurance']);
        return $result;
    }

    /**
     * Créances par partenaire (DQL natif).
     */
    public function getCreancesParPartenaire(): array
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select(
                'p.id',
                'p.nom',
                'COALESCE(SUM(f.partAssurance), 0) as creances'
            )
            ->from('App\Entity\Partenaire', 'p')
            ->leftJoin(
                'App\Entity\FactureGlobale',
                'f',
                'WITH',
                'f.partenaire = p AND f.statut = :statut'
            )
            ->where('p.actif = 1')
            ->setParameter('statut', 'paye')
            ->groupBy('p.id')
            ->getQuery()->getArrayResult();
    }

    /**
     * Stats globales pour la page index partenaires.
     */
    public function getStatsGlobales(): array
    {
        $creancesRow = $this->getEntityManager()->createQueryBuilder()
            ->select('COALESCE(SUM(f.partAssurance), 0) as totalCreances')
            ->from('App\Entity\FactureGlobale', 'f')
            ->where('f.partenaire IS NOT NULL')
            ->andWhere('f.statut = :statut')
            ->setParameter('statut', 'paye')
            ->getQuery()->getSingleResult();

        $facturesEnAttente = (int)$this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(f.id)')
            ->from('App\Entity\FactureGlobale', 'f')
            ->where('f.partenaire IS NOT NULL')
            ->andWhere('f.statut = :statut')
            ->setParameter('statut', 'en_attente')
            ->getQuery()->getSingleScalarResult();

        $patientsAssures = (int)$this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(DISTINCT f.patient)')
            ->from('App\Entity\FactureGlobale', 'f')
            ->where('f.partenaire IS NOT NULL')
            ->getQuery()->getSingleScalarResult();

        return [
            'totalCreances'     => (float)($creancesRow['totalCreances'] ?? 0),
            'facturesEnAttente' => $facturesEnAttente,
            'patientsAssures'   => $patientsAssures,
        ];
    }
}
