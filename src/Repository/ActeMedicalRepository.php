<?php

namespace App\Repository;

use App\Entity\ActeMedical;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActeMedical>
 */
class ActeMedicalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActeMedical::class);
    }

    /**
     * Ancienne méthode — utilise 'categorie' (ex-'type') pour filtrer.
     * Conservée pour rétrocompatibilité.
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.categorie = :categorie')
            ->andWhere('a.actif = true')
            ->setParameter('categorie', $type)
            ->orderBy('a.designation', 'ASC')   // 'libelle' → 'designation'
            ->getQuery()->getResult();
    }

    /**
     * Recherche par catégorie (terme correct).
     */
    public function findByCategorie(string $categorie): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.categorie = :cat')
            ->andWhere('a.actif = true')
            ->setParameter('cat', $categorie)
            ->orderBy('a.designation', 'ASC')
            ->getQuery()->getResult();
    }

    /**
     * Retourne tous les actes actifs, filtrés par catégories multiples.
     * Ex : findByCategories(['examen', 'soin'])
     */
    public function findByCategories(array $categories): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.categorie IN (:cats)')
            ->andWhere('a.actif = true')
            ->setParameter('cats', $categories)
            ->orderBy('a.designation', 'ASC')
            ->getQuery()->getResult();
    }

    /**
     * Recherche fulltext sur designation / code.
     */
    public function search(string $query): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.designation LIKE :q OR a.code LIKE :q')
            ->andWhere('a.actif = true')
            ->setParameter('q', '%' . $query . '%')
            ->orderBy('a.designation', 'ASC')
            ->setMaxResults(20)
            ->getQuery()->getResult();
    }
}
