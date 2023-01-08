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

class VerificationRepository extends DoctrineEntityRepository {

    public function getEntityRepository() {
        if (null === $this->entityRepository) {
            $this->setEntityRepository($this->getEntityManager()->getRepository('Application\Entity\Verification'));
        }
        return $this->entityRepository;
    }

    /**
     * @param int $id
     * @return \Application\Entity\Verification
     */
    public function find($id) {
        return parent::find($id);
    }

    /**
     * @return \Application\Entity\Verification[]
     */
    public function getPendingVerifications() {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $queryBuilder->select('verification')
        ->from('Application\Entity\Verification', 'verification')
        ->where('verification.status IN (:status)')
        ->orderBy('verification.created_at', 'DESC')
        ->setParameter('status', array(\Application\Entity\Verification::STATUS_REQUESTED, \Application\Entity\Verification::STATUS_PENDING));
        return $queryBuilder->getQuery()->getResult();
    }
}
