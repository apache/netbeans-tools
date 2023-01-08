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

class VerificationRequestRepository extends DoctrineEntityRepository {

    public function getEntityRepository() {
        if (null === $this->entityRepository) {
            $this->setEntityRepository($this->getEntityManager()->getRepository('Application\Entity\VerificationRequest'));
        }
        return $this->entityRepository;
    }

    public function getVotesBreakdownForVerification($verificationId) {
        return array(
            \Application\Entity\VerificationRequest::VOTE_GO => $this->getVotedRequestsForVerification($verificationId, \Application\Entity\VerificationRequest::VOTE_GO),
            \Application\Entity\VerificationRequest::VOTE_NOGO => $this->getVotedRequestsForVerification($verificationId, \Application\Entity\VerificationRequest::VOTE_NOGO),
            \Application\Entity\VerificationRequest::VOTE_UNDECIDED => $this->getVotedRequestsForVerification($verificationId, \Application\Entity\VerificationRequest::VOTE_UNDECIDED),
        );
    }

    /**
     * @param int $verifierId
     * @return \Application\Entity\VerificationRequest[]
     */
    public function getVerificationRequestsForVerifier($verifierId) {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $queryBuilder->select('vrq, verification, verifier, nbVersionPluginVersion, pluginVersion, plugin, nbVersion')
        ->from('Application\Entity\VerificationRequest', 'vrq')
        ->join('vrq.verifier', 'verifier')
        ->join('vrq.verification', 'verification')
        ->join('verification.nbVersionPluginVersion', 'nbVersionPluginVersion')
        ->join('nbVersionPluginVersion.pluginVersion', 'pluginVersion')
        ->join('nbVersionPluginVersion.nbVersion', 'nbVersion')
        ->join('pluginVersion.plugin', 'plugin')
        ->where('verifier.id = :userId')
        ->andWhere('verification.status IN (:status)')
        ->orderBy('vrq.created_at', 'DESC')
        ->setParameter('status', array(\Application\Entity\Verification::STATUS_REQUESTED, \Application\Entity\Verification::STATUS_PENDING))
        ->setParameter('userId', $verifierId);
        return $queryBuilder->getQuery()->getResult();    
    }

    public function getVotedRequestsForVerification($verificationId, $vote) {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $queryBuilder->select('vrq')
        ->from('Application\Entity\VerificationRequest', 'vrq')       
        ->where('vrq.verification_id = :vid')
        ->andWhere('vrq.vote = :vote')
        ->setParameter('vid', $verificationId)
        ->setParameter('vote', $vote);
        return $queryBuilder->getQuery()->getResult();  
    }
    
    public function deleteRequestsOfVerification($verificationId) {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $queryBuilder->delete('Application\Entity\VerificationRequest', 'vrq')
        ->where('vrq.verification_id = :vid')
        ->setParameter('vid', $verificationId);
        return $queryBuilder->getQuery()->getResult();  
    }

}
