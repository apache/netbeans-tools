<?php

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
        ->where('verifier.user_id = :userId')
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
