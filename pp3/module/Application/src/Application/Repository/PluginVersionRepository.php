<?php

namespace Application\Repository;

class PluginVersionRepository extends DoctrineEntityRepository {

    public function getEntityRepository() {
        if (null === $this->entityRepository) {
            $this->setEntityRepository($this->getEntityManager()->getRepository('Application\Entity\PluginVersion'));
        }
        return $this->entityRepository;
    }

    public function getVerifiedVersionsByNbVersion($nbVersion) {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $queryBuilder->select('pluginVersion, plugin')
        ->from('Application\Entity\PluginVersion', 'pluginVersion')
        ->join('pluginVersion.plugin', 'plugin')
        ->join('pluginVersion.nbVersionsPluginVersions', 'nbVersionsPluginVersions')
        ->join('nbVersionsPluginVersions.nbVersion', 'nbVersion')
        ->join('nbVersionsPluginVersions.verification', 'verification')
        ->where('nbVersion.version = :nbversion')
        ->andWhere('verification.status = :verifstatus')
        ->andWhere('plugin.status = :pluginstatus')
        ->orderBy('pluginVersion.id', 'ASC')
        ->setParameter('nbversion', $nbVersion)
        ->setParameter('verifstatus', \Application\Entity\Verification::STATUS_GO)
        ->setParameter('pluginstatus', \Application\Entity\Plugin::STATUS_PUBLIC);
        
        return $queryBuilder->getQuery()->getResult();    
    }

    public function getNonVerifiedVersionsByNbVersion($nbVersion) {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $queryBuilder->select('pluginVersion, plugin')
        ->from('Application\Entity\PluginVersion', 'pluginVersion')
        ->join('pluginVersion.plugin', 'plugin')
        ->join('pluginVersion.nbVersionsPluginVersions', 'nbVersionsPluginVersions')
        ->join('nbVersionsPluginVersions.nbVersion', 'nbVersion')
        ->where('nbVersion.version = :nbversion')
        ->andWhere('plugin.status = :pluginstatus')
        ->orderBy('pluginVersion.id', 'ASC')
        ->setParameter('nbversion', $nbVersion)
        ->setParameter('pluginstatus', \Application\Entity\Plugin::STATUS_PUBLIC);
        
        return $queryBuilder->getQuery()->getResult();    
    }

}
