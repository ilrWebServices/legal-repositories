<?php

namespace App\Repository;

use App\Entity\ConsentDecree;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ConsentDecree|null find($id, $lockMode = null, $lockVersion = null)
 * @method ConsentDecree|null findOneBy(array $criteria, array $orderBy = null)
 * @method ConsentDecree[]    findAll()
 * @method ConsentDecree[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ConsentDecreeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConsentDecree::class);
    }

    // /**
    //  * @return ConsentDecree[] Returns an array of ConsentDecree objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('l.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?ConsentDecree
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
