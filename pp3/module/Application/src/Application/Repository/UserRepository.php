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

use Application\Entity\User;

class UserRepository extends DoctrineEntityRepository {

    public function getEntityRepository() {
        if (null === $this->entityRepository) {
            $this->setEntityRepository($this->getEntityManager()->getRepository('Application\Entity\User'));
        }
        return $this->entityRepository;
    }

    /**
     * @param string $idpProviderId id of the identity provider
     * @param string $idpUserId id of the user in the scope of the provider
     * @return User
     */
    public function findByIdpData($idpProviderId, $idpUserId) {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $queryBuilder->select('user')
                ->from('Application\Entity\User', 'user')
                ->where('user.idpProviderId = :idpProviderId AND user.idpUserId = :idpUserId')
                ->setParameter('idpProviderId', $idpProviderId)
                ->setParameter('idpUserId', $idpUserId);
        return $queryBuilder->getQuery()->getOneOrNullResult();
    }

    /**
     * @return User[] list of users, that have verifier status
     */
    public function findVerifier() {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $queryBuilder->select('user')
                ->from('Application\Entity\User', 'user')
                ->where('user.verifier = true');
        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * @return User[] list of users, that have admin status
     */
    public function findAdmins() {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $queryBuilder->select('user')
                ->from('Application\Entity\User', 'user')
                ->where('user.admin = true');
        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * @return User[] list of users, that are registered with the supplied email
     */
    public function findByEmail($email) {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $queryBuilder->select('user')
                ->from('Application\Entity\User', 'user')
                ->where('lower(user.email) = :email')
                ->setParameter('email', mb_strtolower($email, 'UTF-8'));
        return $queryBuilder->getQuery()->getResult();
    }
}
