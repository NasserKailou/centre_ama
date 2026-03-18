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

    public function getBilanPeriode(\DateTime $debut, \DateTime $fin): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql  = "
            SELECT pa.nom, pa.type,
                   COUNT(DISTINCT f.patient_id) as nbPatients,
                   COALESCE(SUM(f.montant_total), 0)  as montantBrut,
                   COALESCE(SUM(f.part_assurance), 0) as partAssurance,
                   COALESCE(SUM(f.part_patient), 0)   as restePatient,
                   'en_attente' as statut
            FROM partenaire pa
            LEFT JOIN facture_globale f ON f.partenaire_id = pa.id
                AND f.created_at >= :debut
                AND f.created_at <= :fin
            WHERE pa.actif = 1
            GROUP BY pa.id
            ORDER BY partAssurance DESC
        ";
        $stmt = $conn->prepare($sql);
        return $stmt->executeQuery([
            'debut' => $debut->format('Y-m-d 00:00:00'),
            'fin'   => $fin->format('Y-m-d 23:59:59'),
        ])->fetchAllAssociative();
    }

    public function getCreancesParPartenaire(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql  = "
            SELECT pa.id, pa.nom,
                   COALESCE(SUM(f.part_assurance), 0) as creances
            FROM partenaire pa
            LEFT JOIN facture_globale f ON f.partenaire_id = pa.id
                AND f.statut = 'paye'
                AND f.statut_assurance != 'rembourse'
            WHERE pa.actif = 1
            GROUP BY pa.id
        ";
        return $conn->executeQuery($sql)->fetchAllAssociative();
    }
}
