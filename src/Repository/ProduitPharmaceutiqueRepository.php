<?php

namespace App\Repository;

use App\Entity\ProduitPharmaceutique;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
}
