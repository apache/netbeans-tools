<?php

namespace Application\Repository;

class VerificationRepository extends DoctrineEntityRepository {

    public function getEntityRepository() {
        if (null === $this->entityRepository) {
            $this->setEntityRepository($this->getEntityManager()->getRepository('Application\Entity\Verification'));
        }
        return $this->entityRepository;
    }

}
