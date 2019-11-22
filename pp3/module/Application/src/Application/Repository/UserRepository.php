<?php

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
