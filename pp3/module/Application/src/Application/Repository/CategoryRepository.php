<?php

namespace Application\Repository;

class CategoryRepository extends DoctrineEntityRepository {

    public function getEntityRepository() {
        if (null === $this->entityRepository) {
            $this->setEntityRepository($this->getEntityManager()->getRepository('Application\Entity\Category'));
        }
        return $this->entityRepository;
    }

    public function getAllCategoriesSortByName() {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $queryBuilder->select('category')
        ->from('Application\Entity\Category', 'category')       
        ->orderBy('category.name', 'ASC');  
        return $queryBuilder->getQuery()->getResult();  
    }

}
