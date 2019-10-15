<?php

namespace Application\Repository;

class NbVersionPluginVersionRepository extends DoctrineEntityRepository {

    public function getEntityRepository() {
        if (null === $this->entityRepository) {
            $this->setEntityRepository($this->getEntityManager()->getRepository('Application\Entity\NbVersionPluginVersion'));
        }
        return $this->entityRepository;
    }    

    public function removeByNbVersionId($id) {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $queryBuilder->delete('Application\Entity\NbVersionPluginVersion', 'nbvpv')     
        ->where('nbvpv.nb_version_id = :id')
        ->setParameter('id', $id)
        ->getQuery()->execute(); ;  
    }
}
