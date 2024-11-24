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
 *   https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

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
        ->join('pv.plugin', 'p', 'WITH', 'p.id = :pid')
        ->join('nbvpv.verification', 'v', 'WITH', sprintf('v.status = %d', \Application\Entity\Verification::STATUS_GO))
        ->setParameter('pid', $pluginId)
        ->groupBy('nbv.id');

        return $queryBuilder->getQuery()->getResult();
    }

    public function getVerificationPendingNbVersionIdsForPlugin($pluginId) {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $queryBuilder->select('nbv.id, nbv.version')
        ->from('Application\Entity\NbVersion', 'nbv')
        ->join('nbv.nbVersionsPluginVersions', 'nbvpv')
        ->join('nbvpv.pluginVersion', 'pv')
        ->join('pv.plugin', 'p', 'WITH', 'p.id = :pid')
        ->join('nbvpv.verification', 'v', 'WITH', sprintf('v.status = %d or v.status = %d', \Application\Entity\Verification::STATUS_REQUESTED, \Application\Entity\Verification::STATUS_PENDING))
        ->setParameter('pid', $pluginId)
        ->groupBy('nbv.id');

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * @return \Application\Entity\Base\NbVersion[]
     */
    public function getNbVersionCatalogToBeRebuild() {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $queryBuilder->select('nbv')
        ->from('Application\Entity\NbVersion', 'nbv')
        ->where('nbv.catalog_rebuild is null OR nbv.catalog_rebuild < nbv.catalog_rebuild_requested');
        return $queryBuilder->getQuery()->getResult();
    }
}
