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

class PluginRepository extends DoctrineEntityRepository {

    public function getEntityRepository() {
        if (null === $this->entityRepository) {
            $this->setEntityRepository($this->getEntityManager()->getRepository('Application\Entity\Plugin'));
        }
        return $this->entityRepository;
    }

    public function getPluginsByArtifact($art, $grp) {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $queryBuilder->select('p')
        ->from('Application\Entity\Plugin', 'p')
        ->where('p.artifactid = :art')
        ->andWhere('p.groupid = :grp')
        ->setParameter('art', $art)
        ->setParameter('grp', $grp);
            
        return $queryBuilder->getQuery()->getResult();
    }


    public function getTopNDownloadedPublic($n = 5) {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $queryBuilder->select('p')
        ->from('Application\Entity\Plugin', 'p')
        ->where('p.status = :status')
        ->setParameter('status', \Application\Entity\Plugin::STATUS_PUBLIC)
        ->orderBy('p.downloads', 'DESC')
        ->setMaxResults($n);
            
        return $queryBuilder->getQuery()->getResult();
    }

    public function getLatestNPublic($n = 5) {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $queryBuilder->select('p')
        ->from('Application\Entity\Plugin', 'p')
        ->where('p.status = :status')
        ->setParameter('status', \Application\Entity\Plugin::STATUS_PUBLIC)
        ->orderBy('p.last_updated_at', 'DESC')
        ->setMaxResults($n);
            
        return $queryBuilder->getQuery()->getResult();
    }

    public function getPluginsByAuthorId($author) {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $queryBuilder->select('p, v, nbvPv, nbv, verif')
                ->from('Application\Entity\Plugin', 'p')
                ->leftJoin('p.versions', 'v')
                ->leftJoin('v.nbVersionsPluginVersions', 'nbvPv')
                ->leftJoin('nbvPv.nbVersion', 'nbv')
                ->leftJoin('nbvPv.verification', 'verif')
                ->leftJoin('p.authors', 'a')
                ->where('a.id = :author')
                ->orderBy('p.id', 'DESC')
                ->setParameter('author', $author);
        return $queryBuilder->getQuery()->getResult();        
    }

    public function getPluginsByStatus($status) {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $queryBuilder->select('p, v, nbvPv, nbv')
        ->from('Application\Entity\Plugin', 'p')
        ->leftJoin('p.versions', 'v')
        ->leftJoin('v.nbVersionsPluginVersions', 'nbvPv')
        ->leftJoin('nbvPv.nbVersion', 'nbv')     
        ->where('p.status = :status')->orderBy('p.id', 'ASC')
        ->setParameter('status', $status);
        return $queryBuilder->getQuery()->getResult();
    }

    public function getPluginsByName($name) {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $queryBuilder->select('p, v, nbvPv, nbv')
        ->from('Application\Entity\Plugin', 'p')
        ->leftJoin('p.versions', 'v')
        ->leftJoin('v.nbVersionsPluginVersions', 'nbvPv')
        ->leftJoin('nbvPv.nbVersion', 'nbv')
        ->leftJoin('p.authors', 'a')
        ->where('(p.name LIKE :name) OR (a.email LIKE :name)')->orderBy('p.id', 'ASC')
        ->setParameter('name', '%'.$name.'%');
        return $queryBuilder->getQuery()->getResult();
    }

    public function getPublicPluginsByNameQB($name, $cat, $nbv) {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $queryBuilder->select('p, cat')
        ->from('Application\Entity\Plugin', 'p')
        ->leftJoin('p.versions', 'v')
        ->leftJoin('v.nbVersionsPluginVersions', 'nbvPv')
        ->leftJoin('nbvPv.verification', 'verif')
        ->leftJoin('nbvPv.nbVersion', 'nbv')
        ->leftJoin('p.categories', 'cat')
        ->leftJoin('p.authors', 'a')
        ->where('p.status = :status')
        ->setParameter('status', \Application\Entity\Plugin::STATUS_PUBLIC);
        if ($name) {
            $queryBuilder->andWhere('(p.name LIKE :name OR p.artifactid LIKE :name OR a.email LIKE :name OR p.short_description LIKE :name OR p.description LIKE :name
            OR p.license LIKE :name OR nbv.version LIKE :name OR cat.name LIKE :name)')->setParameter('name', '%'.$name.'%');
        }     
        //$queryBuilder->orderBy('p.name', 'ASC');
        if (!empty($cat)) {
            $queryBuilder->andWhere('cat.name = :cat')->setParameter('cat', $cat);
        }
        if (!empty($nbv)) {
            $queryBuilder->andWhere('nbv.version = :nbv')->setParameter('nbv', $nbv);
        }
            
        return $queryBuilder;
    }

    public function getPublicPluginById($id) {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $queryBuilder->select('p, v, nbvPv, nbv, verif, cat')
        ->from('Application\Entity\Plugin', 'p')
        ->leftJoin('p.versions', 'v')
        ->leftJoin('v.nbVersionsPluginVersions', 'nbvPv')
        ->leftJoin('nbvPv.verification', 'verif')
        ->leftJoin('nbvPv.nbVersion', 'nbv')
        ->leftJoin('p.categories', 'cat')
        ->where('p.status = :status')
        ->setParameter('status', \Application\Entity\Plugin::STATUS_PUBLIC)
        ->andWhere('p.id = :id')
        ->setParameter('id', $id);
            
        return $queryBuilder->getQuery()->getOneOrNullResult();
    }

    /**
     * @param string[] $nbVersionIds
     * @param boolean $onlyVerified
     * @return \Application\Entity\Plugin[]
     */
    public function getPluginsByNetBeansVersion($nbVersionIds, $onlyVerified = false) {

        $queryBuilder = $this->getEntityManager()->createQueryBuilder();

        $queryBuilder->select('plugin')
        ->from('Application\Entity\Plugin', 'plugin')
        ->leftJoin('plugin.versions', 'pv')
        ->leftJoin('pv.nbVersionsPluginVersions', 'nbvpv')
        ->leftJoin('nbvpv.nbVersion', 'nbv')
        ->leftJoin('nbvpv.verification', 'v');

        if($nbVersionIds !== null) {
            $queryBuilder->andWhere($queryBuilder->expr()->in('nbv.id', $nbVersionIds));
        }

        if($onlyVerified) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq('v.status', \Application\Entity\Verification::STATUS_GO));
        }

        return $queryBuilder->getQuery()->getResult();
    }
}
