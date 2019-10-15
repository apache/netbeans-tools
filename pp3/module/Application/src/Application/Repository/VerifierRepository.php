<?php

namespace Application\Repository;

class VerifierRepository extends DoctrineEntityRepository {

    public function getEntityRepository() {
        if (null === $this->entityRepository) {
            $this->setEntityRepository($this->getEntityManager()->getRepository('Application\Entity\Verifier'));
        }
        return $this->entityRepository;
    }

    public function findOneBy($column, $value) {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $queryBuilder->select('verifier')
        ->from('Application\Entity\Verifier', 'verifier')       
        ->where('verifier.'.$column.' = :value')
        ->setParameter('value', $value);    

        return $queryBuilder->getQuery()->getOneOrNullResult();    
    }
}
