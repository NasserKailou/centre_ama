<?php

namespace App\Repository;

use App\Entity\FactureGlobale;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FactureGlobale>
 */
class FactureGlobaleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FactureGlobale::class);
    }

    public function findByDate(\DateTime $date): array
    {
        return $this->createQueryBuilder('f')
            ->leftJoin('f.patient', 'p')
            ->leftJoin('f.caissier', 'c')
            ->leftJoin('f.lignes', 'l')
            ->addSelect('p', 'c', 'l')
            ->where('f.createdAt >= :debut')
            ->andWhere('f.createdAt <= :fin')
            ->setParameter('debut', $date->format('Y-m-d 00:00:00'))
            ->setParameter('fin',   $date->format('Y-m-d 23:59:59'))
            ->orderBy('f.createdAt', 'DESC')
            ->getQuery()->getResult();
    }

    public function findByFilters(
        ?\DateTime $dateDebut,
        ?\DateTime $dateFin,
        ?string $statut,
        ?int $patientId,
        int $page = 1,
        int $limit = 20
    ): array {
        $qb = $this->createQueryBuilder('f')
            ->leftJoin('f.patient', 'p')
            ->leftJoin('f.caissier', 'c')
            ->addSelect('p', 'c')
            ->orderBy('f.createdAt', 'DESC');

        if ($dateDebut) {
            $qb->andWhere('f.createdAt >= :debut')
               ->setParameter('debut', $dateDebut->format('Y-m-d 00:00:00'));
        }
        if ($dateFin) {
            $qb->andWhere('f.createdAt <= :fin')
               ->setParameter('fin', $dateFin->format('Y-m-d 23:59:59'));
        }
        if ($statut) {
            $qb->andWhere('f.statut = :statut')->setParameter('statut', $statut);
        }
        if ($patientId) {
            $qb->andWhere('p.id = :pid')->setParameter('pid', $patientId);
        }

        return $qb->setFirstResult(($page - 1) * $limit)
                  ->setMaxResults($limit)
                  ->getQuery()->getResult();
    }

    public function countByDate(\DateTime $date): int
    {
        return (int)$this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.createdAt >= :debut AND f.createdAt <= :fin')
            ->setParameter('debut', $date->format('Y-m-d 00:00:00'))
            ->setParameter('fin',   $date->format('Y-m-d 23:59:59'))
            ->getQuery()->getSingleScalarResult();
    }

    public function sumRecettesByDate(\DateTime $date): float
    {
        $result = $this->createQueryBuilder('f')
            ->select('SUM(f.montantTotal) as total')
            ->where('f.createdAt >= :debut AND f.createdAt <= :fin')
            ->andWhere('f.statut = :statut')
            ->setParameter('debut',  $date->format('Y-m-d 00:00:00'))
            ->setParameter('fin',    $date->format('Y-m-d 23:59:59'))
            ->setParameter('statut', 'paye')
            ->getQuery()->getSingleScalarResult();
        return (float)$result;
    }

    public function countImpayees(): int
    {
        return (int)$this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.statut = :statut')
            ->setParameter('statut', 'en_attente')
            ->getQuery()->getSingleScalarResult();
    }

    public function getRevenuParMedecin(int $medecinId, \DateTime $debut, \DateTime $fin): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql  = "
            SELECT
                COALESCE(SUM(f.montant_total), 0)    AS total,
                COALESCE(SUM(f.part_assurance), 0)   AS assurance,
                COALESCE(SUM(f.part_patient), 0)     AS patient
            FROM facture_globale f
            INNER JOIN consultation co ON co.id = f.consultation_id
            WHERE co.medecin_id = :mid
              AND f.created_at >= :debut
              AND f.created_at <= :fin
              AND f.statut = 'paye'
        ";
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery([
            'mid'   => $medecinId,
            'debut' => $debut->format('Y-m-d 00:00:00'),
            'fin'   => $fin->format('Y-m-d 23:59:59'),
        ]);
        return $result->fetchAssociative() ?: ['total' => 0, 'assurance' => 0, 'patient' => 0];
    }

    public function getGlobalStats(\DateTime $debut, \DateTime $fin): array
    {
        $result = $this->createQueryBuilder('f')
            ->select(
                'COUNT(DISTINCT f.patient) as nbPatients',
                'COUNT(f.id) as nbFactures',
                'SUM(f.montantTotal) as revenuTotal',
                'SUM(f.montantActes) as revenuActes',
                'SUM(f.partAssurance) as partAssurance',
                'SUM(f.partPatient) as partPatient'
            )
            ->where('f.createdAt >= :debut AND f.createdAt <= :fin')
            ->andWhere('f.statut = :statut')
            ->setParameter('debut',  $debut->format('Y-m-d 00:00:00'))
            ->setParameter('fin',    $fin->format('Y-m-d 23:59:59'))
            ->setParameter('statut', 'paye')
            ->getQuery()->getSingleResult();

        // getConsultations est séparé
        $conn = $this->getEntityManager()->getConnection();
        $nb   = $conn->fetchOne(
            "SELECT COUNT(*) FROM consultation WHERE date_heure >= ? AND date_heure <= ?",
            [$debut->format('Y-m-d 00:00:00'), $fin->format('Y-m-d 23:59:59')]
        );
        $result['nbConsultations'] = (int)$nb;
        return $result;
    }

    public function getActesRepartition(?\DateTime $debut = null, ?\DateTime $fin = null): array
    {
        $conn  = $this->getEntityManager()->getConnection();
        $where = '';
        $params = [];
        if ($debut && $fin) {
            $where = "WHERE f.created_at >= ? AND f.created_at <= ?";
            $params = [$debut->format('Y-m-d 00:00:00'), $fin->format('Y-m-d 23:59:59')];
        }
        $sql = "
            SELECT am.designation, COUNT(lf.id) as count, SUM(lf.sous_total) as montant,
                   ROUND(COUNT(lf.id) * 100.0 / (SELECT COUNT(*) FROM ligne_facture), 1) as pourcentage
            FROM ligne_facture lf
            INNER JOIN facture_globale f ON f.id = lf.facture_globale_id
            LEFT JOIN acte_medical am ON am.id = lf.acte_medical_id
            {$where}
            AND lf.type_ligne = 'acte'
            GROUP BY lf.acte_medical_id
            ORDER BY count DESC
            LIMIT 10
        ";
        $stmt = $conn->prepare($sql);
        return $stmt->executeQuery($params)->fetchAllAssociative();
    }

    public function getTopActes(\DateTime $debut, \DateTime $fin, int $limit = 10): array
    {
        return $this->getActesRepartition($debut, $fin);
    }

    public function getVentesPharmacieParProduit(\DateTime $debut, \DateTime $fin): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql  = "
            SELECT pp.designation, pp.categorie,
                   SUM(lf.quantite) as qteVendue,
                   pp.prix_vente as prixUnitaire,
                   SUM(lf.sous_total) as total
            FROM ligne_facture lf
            INNER JOIN facture_globale f ON f.id = lf.facture_globale_id
            LEFT JOIN produit_pharmaceutique pp ON pp.id = lf.produit_id
            WHERE f.created_at >= ? AND f.created_at <= ?
              AND lf.type_ligne = 'produit'
            GROUP BY lf.produit_id
            ORDER BY total DESC
        ";
        $stmt = $conn->prepare($sql);
        return $stmt->executeQuery([
            $debut->format('Y-m-d 00:00:00'),
            $fin->format('Y-m-d 23:59:59'),
        ])->fetchAllAssociative();
    }

    public function generateNumeroFacture(): string
    {
        $prefix = $_ENV['FACTURE_PREFIX'] ?? 'FAC';
        $year   = date('Y');
        $count  = (int)$this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.createdAt >= :debut')
            ->setParameter('debut', "$year-01-01 00:00:00")
            ->getQuery()->getSingleScalarResult();
        return $prefix . '-' . $year . '-' . str_pad($count + 1, 5, '0', STR_PAD_LEFT);
    }
}
