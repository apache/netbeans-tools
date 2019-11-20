<?php
/**
 * Licensed to the Apache Software Foundation (ASF) under one
 * or more contributor license agreements.  See the NOTICE file
 * distributed with this work for additional information
 * regarding copyright ownership.  The ASF licenses this file
 * to you under the Apache License, Version 2.0 (the
 * "License"); you may not use this file except in compliance
 * with the License.  You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

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
