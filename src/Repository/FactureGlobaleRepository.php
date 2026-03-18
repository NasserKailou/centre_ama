<?php

namespace App\Repository;

use App\Entity\FactureGlobale;
use App\Entity\Partenaire;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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

    // ─── QueryBuilder filtré (remplace getFilteredQueryBuilder) ───────────
    public function getFilteredQueryBuilder(
        ?string $search = null,
        ?string $date   = null,
        ?string $statut = null
    ): QueryBuilder {
        $qb = $this->createQueryBuilder('f')
            ->leftJoin('f.patient', 'p')
            ->leftJoin('f.caissier', 'c')
            ->addSelect('p', 'c')
            ->orderBy('f.createdAt', 'DESC');

        if ($search) {
            $qb->andWhere('p.nom LIKE :s OR p.prenom LIKE :s OR f.numero LIKE :s')
               ->setParameter('s', '%' . $search . '%');
        }
        if ($date) {
            $qb->andWhere('f.createdAt >= :debut AND f.createdAt <= :fin')
               ->setParameter('debut', $date . ' 00:00:00')
               ->setParameter('fin',   $date . ' 23:59:59');
        }
        if ($statut) {
            $qb->andWhere('f.statut = :statut')->setParameter('statut', $statut);
        }

        return $qb;
    }

    // ─── sumByPeriod ───────────────────────────────────────────────────────
    public function sumByPeriod(\DateTime $debut, \DateTime $fin, ?string $statut = null): float
    {
        $qb = $this->createQueryBuilder('f')
            ->select('SUM(f.montantTotal) as total')
            ->where('f.createdAt >= :debut AND f.createdAt <= :fin')
            ->setParameter('debut', $debut->format('Y-m-d H:i:s'))
            ->setParameter('fin',   $fin->format('Y-m-d H:i:s'));
        if ($statut) {
            $qb->andWhere('f.statut = :statut')->setParameter('statut', $statut);
        }
        return (float)($qb->getQuery()->getSingleScalarResult() ?? 0);
    }

    // ─── countByPeriod ────────────────────────────────────────────────────
    public function countByPeriod(\DateTime $debut, \DateTime $fin): int
    {
        return (int)$this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.createdAt >= :debut AND f.createdAt <= :fin')
            ->setParameter('debut', $debut->format('Y-m-d H:i:s'))
            ->setParameter('fin',   $fin->format('Y-m-d H:i:s'))
            ->getQuery()->getSingleScalarResult();
    }

    // ─── countByPeriodStatut ──────────────────────────────────────────────
    public function countByPeriodStatut(\DateTime $debut, \DateTime $fin, string $statut): int
    {
        return (int)$this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.createdAt >= :debut AND f.createdAt <= :fin')
            ->andWhere('f.statut = :statut')
            ->setParameter('debut',  $debut->format('Y-m-d H:i:s'))
            ->setParameter('fin',    $fin->format('Y-m-d H:i:s'))
            ->setParameter('statut', $statut)
            ->getQuery()->getSingleScalarResult();
    }

    // ─── findForSituationJournaliere ──────────────────────────────────────
    public function findForSituationJournaliere(
        \DateTime $debut,
        \DateTime $fin,
        ?User $caissier = null
    ): array {
        $qb = $this->createQueryBuilder('f')
            ->leftJoin('f.patient', 'p')
            ->leftJoin('f.caissier', 'c')
            ->addSelect('p', 'c')
            ->where('f.createdAt >= :debut AND f.createdAt <= :fin')
            ->setParameter('debut', $debut->format('Y-m-d H:i:s'))
            ->setParameter('fin',   $fin->format('Y-m-d H:i:s'))
            ->orderBy('f.createdAt', 'DESC');

        if ($caissier) {
            $qb->andWhere('f.caissier = :caissier')->setParameter('caissier', $caissier);
        }
        return $qb->getQuery()->getResult();
    }

    // ─── getStatsJournalieres ─────────────────────────────────────────────
    public function getStatsJournalieres(
        \DateTime $debut,
        \DateTime $fin,
        ?User $caissier = null
    ): array {
        $qb = $this->createQueryBuilder('f')
            ->select(
                'COUNT(f.id) as nbFactures',
                'SUM(CASE WHEN f.statut = :paye THEN 1 ELSE 0 END) as nbPayees',
                'SUM(CASE WHEN f.statut = :paye THEN f.montantTotal ELSE 0 END) as totalEncaisse',
                'SUM(CASE WHEN f.statut = :paye THEN f.partAssurance ELSE 0 END) as totalAssurance',
                'SUM(CASE WHEN f.statut = :paye THEN f.partPatient ELSE 0 END) as totalPatient'
            )
            ->where('f.createdAt >= :debut AND f.createdAt <= :fin')
            ->setParameter('debut', $debut->format('Y-m-d H:i:s'))
            ->setParameter('fin',   $fin->format('Y-m-d H:i:s'))
            ->setParameter('paye',  'paye');

        if ($caissier) {
            $qb->andWhere('f.caissier = :caissier')->setParameter('caissier', $caissier);
        }

        $row = $qb->getQuery()->getSingleResult();
        return [
            'nb_factures'    => (int)($row['nbFactures']    ?? 0),
            'nb_payees'      => (int)($row['nbPayees']      ?? 0),
            'total_encaisse' => (float)($row['totalEncaisse'] ?? 0),
            'total_assurance'=> (float)($row['totalAssurance'] ?? 0),
            'total_patient'  => (float)($row['totalPatient']  ?? 0),
        ];
    }

    // ─── findByPartenairePeriode ───────────────────────────────────────────
    public function findByPartenairePeriode(
        Partenaire $partenaire,
        \DateTime  $debut,
        \DateTime  $fin
    ): array {
        return $this->createQueryBuilder('f')
            ->leftJoin('f.patient', 'p')->addSelect('p')
            ->where('f.partenaire = :p')
            ->andWhere('f.createdAt >= :debut AND f.createdAt <= :fin')
            ->setParameter('p',     $partenaire)
            ->setParameter('debut', $debut->format('Y-m-d H:i:s'))
            ->setParameter('fin',   $fin->format('Y-m-d H:i:s'))
            ->orderBy('f.createdAt', 'DESC')
            ->getQuery()->getResult();
    }

    // ─── getStatsPartenaire ────────────────────────────────────────────────
    public function getStatsPartenaire(
        Partenaire $partenaire,
        \DateTime  $debut,
        \DateTime  $fin
    ): array {
        $row = $this->createQueryBuilder('f')
            ->select(
                'COUNT(DISTINCT f.patient) as nbPatients',
                'COUNT(f.id) as nbFactures',
                'SUM(f.montantTotal) as montantBrut',
                'SUM(f.partAssurance) as partAssurance',
                'SUM(f.partPatient) as restePatient'
            )
            ->where('f.partenaire = :p')
            ->andWhere('f.createdAt >= :debut AND f.createdAt <= :fin')
            ->setParameter('p',     $partenaire)
            ->setParameter('debut', $debut->format('Y-m-d H:i:s'))
            ->setParameter('fin',   $fin->format('Y-m-d H:i:s'))
            ->getQuery()->getSingleResult();

        return [
            'nbPatients'    => (int)($row['nbPatients']    ?? 0),
            'nbFactures'    => (int)($row['nbFactures']    ?? 0),
            'montantBrut'   => (float)($row['montantBrut']    ?? 0),
            'partAssurance' => (float)($row['partAssurance']  ?? 0),
            'restePatient'  => (float)($row['restePatient']   ?? 0),
        ];
    }

    // ─── findByDate ────────────────────────────────────────────────────────
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

    // ─── sumRecettesByDate ────────────────────────────────────────────────
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

    // ─── countByDate ──────────────────────────────────────────────────────
    public function countByDate(\DateTime $date): int
    {
        return (int)$this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.createdAt >= :debut AND f.createdAt <= :fin')
            ->setParameter('debut', $date->format('Y-m-d 00:00:00'))
            ->setParameter('fin',   $date->format('Y-m-d 23:59:59'))
            ->getQuery()->getSingleScalarResult();
    }

    // ─── countImpayees ────────────────────────────────────────────────────
    public function countImpayees(): int
    {
        return (int)$this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.statut = :statut')
            ->setParameter('statut', 'en_attente')
            ->getQuery()->getSingleScalarResult();
    }

    // ─── getRevenuParMedecin ──────────────────────────────────────────────
    public function getRevenuParMedecin(int $medecinId, \DateTime $debut, \DateTime $fin): array
    {
        // Utilise DQL pour éviter les problèmes de paramètres nommés MariaDB
        $row = $this->createQueryBuilder('f')
            ->select(
                'COALESCE(SUM(f.montantTotal), 0)  AS total',
                'COALESCE(SUM(f.partAssurance), 0) AS assurance',
                'COALESCE(SUM(f.partPatient), 0)   AS patient'
            )
            ->join('f.consultation', 'co')
            ->where('co.medecin = :mid')
            ->andWhere('f.createdAt >= :debut AND f.createdAt <= :fin')
            ->andWhere('f.statut = :statut')
            ->setParameter('mid',    $medecinId)
            ->setParameter('debut',  $debut->format('Y-m-d 00:00:00'))
            ->setParameter('fin',    $fin->format('Y-m-d 23:59:59'))
            ->setParameter('statut', 'paye')
            ->getQuery()->getSingleResult();

        return [
            'total'     => (float)($row['total']     ?? 0),
            'assurance' => (float)($row['assurance']  ?? 0),
            'patient'   => (float)($row['patient']    ?? 0),
        ];
    }

    // ─── getGlobalStats ───────────────────────────────────────────────────
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

        // Compte consultations via DQL
        $nbConsultations = (int)$this->getEntityManager()
            ->createQuery('SELECT COUNT(c.id) FROM App\Entity\Consultation c WHERE c.dateHeure >= :debut AND c.dateHeure <= :fin')
            ->setParameter('debut', $debut->format('Y-m-d 00:00:00'))
            ->setParameter('fin',   $fin->format('Y-m-d 23:59:59'))
            ->getSingleScalarResult();

        $result['nbConsultations'] = $nbConsultations;
        return $result;
    }

    // ─── getActesRepartition ──────────────────────────────────────────────
    public function getActesRepartition(?\DateTime $debut = null, ?\DateTime $fin = null): array
    {
        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select(
                'am.designation as designation',
                'COUNT(lf.id) as count',
                'SUM(lf.sousTotal) as montant'
            )
            ->from('App\Entity\LigneFacture', 'lf')
            ->join('lf.factureGlobale', 'f')
            ->leftJoin('lf.acteMedical', 'am')
            ->where("lf.typeLigne = 'acte'")
            ->groupBy('lf.acteMedical')
            ->orderBy('count', 'DESC')
            ->setMaxResults(10);

        if ($debut && $fin) {
            $qb->andWhere('f.createdAt >= :debut AND f.createdAt <= :fin')
               ->setParameter('debut', $debut->format('Y-m-d 00:00:00'))
               ->setParameter('fin',   $fin->format('Y-m-d 23:59:59'));
        }

        return $qb->getQuery()->getArrayResult();
    }

    // ─── getTopActes ──────────────────────────────────────────────────────
    public function getTopActes(\DateTime $debut, \DateTime $fin, int $limit = 10): array
    {
        return $this->getActesRepartition($debut, $fin);
    }

    // ─── getVentesPharmacieParProduit ─────────────────────────────────────
    public function getVentesPharmacieParProduit(\DateTime $debut, \DateTime $fin): array
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select(
                'pp.designation as designation',
                'pp.categorie',
                'SUM(lf.quantite) as qteVendue',
                'pp.prixVente as prixUnitaire',
                'SUM(lf.sousTotal) as total'
            )
            ->from('App\Entity\LigneFacture', 'lf')
            ->join('lf.factureGlobale', 'f')
            ->leftJoin('lf.produit', 'pp')
            ->where("lf.typeLigne = 'produit'")
            ->andWhere('f.createdAt >= :debut AND f.createdAt <= :fin')
            ->setParameter('debut', $debut->format('Y-m-d 00:00:00'))
            ->setParameter('fin',   $fin->format('Y-m-d 23:59:59'))
            ->groupBy('lf.produit')
            ->orderBy('total', 'DESC')
            ->getQuery()->getArrayResult();
    }

    // ─── generateNumeroFacture ────────────────────────────────────────────
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

    // ─── getCreancesTotales ────────────────────────────────────────────────
    public function getCreancesTotales(): float
    {
        return (float)($this->createQueryBuilder('f')
            ->select('SUM(f.partAssurance)')
            ->where('f.statut = :statut')
            ->andWhere('f.partenaire IS NOT NULL')
            ->setParameter('statut', 'paye')
            ->getQuery()->getSingleScalarResult() ?? 0);
    }

    // ─── countFacturesEnAttente ────────────────────────────────────────────
    public function countFacturesEnAttente(): int
    {
        return (int)$this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.statut = :statut')
            ->setParameter('statut', 'en_attente')
            ->getQuery()->getSingleScalarResult();
    }

    // ─── countPatientsAssures ──────────────────────────────────────────────
    public function countPatientsAssures(): int
    {
        return (int)$this->createQueryBuilder('f')
            ->select('COUNT(DISTINCT f.patient)')
            ->where('f.partenaire IS NOT NULL')
            ->getQuery()->getSingleScalarResult();
    }
}
