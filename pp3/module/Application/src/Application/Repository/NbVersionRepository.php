<?php

namespace Application\Repository;

class NbVersionRepository extends DoctrineEntityRepository {

    public function getEntityRepository() {
        if (null === $this->entityRepository) {
            $this->setEntityRepository($this->getEntityManager()->getRepository('Application\Entity\NbVersion'));
        }
        return $this->entityRepository;
    }    

    public function findOneBy($column, $value) {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $queryBuilder->select('nbVersion')
        ->from('Application\Entity\NbVersion', 'nbVersion')       
        ->where('nbVersion.'.$column.' = :value')
        ->setParameter('value', $value);    

        return $queryBuilder->getQuery()->getOneOrNullResult();    
    }

    public function getVerifiedNbVersionIdsForPlugin($pluginId) {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $queryBuilder->select('nbv.id, nbv.version')
        ->from('Application\Entity\NbVersion', 'nbv')
        ->join('nbv.nbVersionsPluginVersions', 'nbvpv')
        ->join('nbvpv.pluginVersion', 'pv')
        ->join('pv.plugin p WITH p.id = :pid')
        ->join('nbvpv.verification v WITH v.status >= 0')
        ->setParameter('pid', $pluginId)
        ->groupBy('nbv.id');

        return $queryBuilder->getQuery()->getResult();
        // return $queryBuilder->getQuery()->getSQL();
    }
}
