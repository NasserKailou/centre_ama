<?php

namespace App\Repository;

use App\Entity\ProduitPharmaceutique;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProduitPharmaceutique>
 */
class ProduitPharmaceutiqueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProduitPharmaceutique::class);
    }

    public function findActifs(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.actif = 1')
            ->orderBy('p.designation', 'ASC')
            ->getQuery()->getResult();
    }

    public function findStockAlerts(int $limit = 20): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.actif = 1')
            ->andWhere('p.stockDisponible <= p.stockMinimum')
            ->orderBy('p.stockDisponible', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    public function findRuptures(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.actif = 1')
            ->andWhere('p.stockDisponible <= 0')
            ->orderBy('p.designation', 'ASC')
            ->getQuery()->getResult();
    }

    public function countRuptures(): int
    {
        return (int)$this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.actif = 1')
            ->andWhere('p.stockDisponible <= p.stockMinimum')
            ->getQuery()->getSingleScalarResult();
    }

    public function search(string $query): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.actif = 1')
            ->andWhere('p.stockDisponible > 0')
            ->andWhere('p.designation LIKE :q OR p.dci LIKE :q OR p.reference LIKE :q')
            ->setParameter('q', '%' . $query . '%')
            ->orderBy('p.designation', 'ASC')
            ->setMaxResults(20)
            ->getQuery()->getResult();
    }

    public function findByCategorie(string $categorie): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.actif = 1')
            ->andWhere('p.categorie = :cat')
            ->setParameter('cat', $categorie)
            ->orderBy('p.designation', 'ASC')
            ->getQuery()->getResult();
    }

    public function getValeurTotaleStock(): float
    {
        $result = $this->createQueryBuilder('p')
            ->select('SUM(p.stockDisponible * p.prixAchat) as valeur')
            ->where('p.actif = 1')
            ->getQuery()->getSingleScalarResult();
        return (float)$result;
    }

    // ─── getFilteredQueryBuilder ──────────────────────────────────────────
    /**
     * Retourne un QueryBuilder filtré pour la liste paginée des produits.
     * Utilisé par PharmacieController::index() via KnpPaginatorBundle.
     */
    public function getFilteredQueryBuilder(
        ?string $search    = null,
        ?string $statut    = null,
        ?string $categorie = null
    ): QueryBuilder {
        $qb = $this->createQueryBuilder('p')
            ->where('p.actif = 1')
            ->orderBy('p.designation', 'ASC');

        if ($search) {
            $qb->andWhere('p.designation LIKE :s OR p.dci LIKE :s OR p.reference LIKE :s')
               ->setParameter('s', '%' . $search . '%');
        }

        if ($statut === 'rupture') {
            $qb->andWhere('p.stockDisponible <= 0');
        } elseif ($statut === 'alerte') {
            $qb->andWhere('p.stockDisponible > 0')
               ->andWhere('p.stockDisponible <= p.stockMinimum');
        } elseif ($statut === 'disponible') {
            $qb->andWhere('p.stockDisponible > p.stockMinimum');
        }

        if ($categorie) {
            $qb->andWhere('p.categorie = :cat')
               ->setParameter('cat', $categorie);
        }

        return $qb;
    }

    // ─── countAlertes ─────────────────────────────────────────────────────
    /**
     * Compte les produits en alerte (stock > 0 mais <= stock minimum).
     */
    public function countAlertes(): int
    {
        return (int)$this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.actif = 1')
            ->andWhere('p.stockDisponible > 0')
            ->andWhere('p.stockDisponible <= p.stockMinimum')
            ->getQuery()->getSingleScalarResult();
    }
}
