<?php

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

    public function getPluginsByAuthor($author) {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $queryBuilder->select('p, v, nbvPv, nbv, verif')
        ->from('Application\Entity\Plugin', 'p')
        ->leftJoin('p.versions', 'v')
        ->leftJoin('v.nbVersionsPluginVersions', 'nbvPv')
        ->leftJoin('nbvPv.nbVersion', 'nbv')
        ->leftJoin('nbvPv.verification', 'verif')
        ->where('p.author = :author')->orderBy('p.id', 'DESC')
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
        ->where('(p.name LIKE :name) OR (p.author LIKE :name)')->orderBy('p.id', 'ASC')
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
        ->where('p.status = :status')
        ->setParameter('status', \Application\Entity\Plugin::STATUS_PUBLIC);
        if ($name) {
            $queryBuilder->andWhere('(p.name LIKE :name OR p.artifactid LIKE :name OR p.author LIKE :name OR p.short_description LIKE :name OR p.description LIKE :name
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
}
