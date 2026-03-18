<?php
namespace App\Repository;
use App\Entity\ActeMedical;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ActeMedicalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, ActeMedical::class); }

    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.type = :type AND a.actif = true')
            ->setParameter('type', $type)
            ->orderBy('a.libelle', 'ASC')
            ->getQuery()->getResult();
    }
}
