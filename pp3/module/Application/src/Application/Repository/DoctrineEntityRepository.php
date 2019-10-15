<?php
namespace Application\Repository;

use Zend\EventManager\EventManagerAwareInterface;
use Zend\EventManager\EventManagerInterface;
use Zend\ServiceManager\ServiceManagerAwareInterface;
use Zend\ServiceManager\ServiceManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;

class DoctrineEntityRepository implements
    ServiceManagerAwareInterface,
    EventManagerAwareInterface
{
    protected $serviceManager;
    protected $eventManager;
    protected $entityManager;
    protected $entityRepository;


    /**
     * Returns all Entities
     *
     * @return EntityRepository
     */
    public function findAll()
    {
//        $this->getEventManager()->trigger(__FUNCTION__ . '.pre', $this, array('entities' => $entities));
        $entities = $this->getEntityRepository()->findAll();
//        $this->getEventManager()->trigger(__FUNCTION__ . '.post', $this, array('entities' => $entities));
        return $entities;
    }    

    public function find($id) {
        return $this->getEntityRepository()->find($id);
    }

    public function flush() {
        $this->getEntityManager()->flush();
    }
    

    public function remove($entity) {
        $this->getEntityManager()->remove($entity);
        $this->getEntityManager()->flush();
    }

    public function findByQuery(\Closure $query)
    {
        $queryBuilder = $this->getEntityRepository()->createQueryBuilder('entity');
        $currentQuery = call_user_func($query, $queryBuilder);
       // \Zend\Debug\Debug::dump($currentQuery->getQuery());
        return $currentQuery->getQuery()->getResult();
    }

    /**
     * Persists and Entity into the Repository
     *
     * @param Entity $entity
     * @return Entity
     */
    public function persist($entity)
    {
//        $this->getEventManager()->trigger(__FUNCTION__ . '.pre', $this, array('entity'=>$entity));
        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();
//        $this->getEventManager()->trigger(__FUNCTION__ . '.post', $this, array('entity'=>$entity));

        return $entity;
    }

    /**
     * Merge and Entity into the Repository
     *
     * @param Entity $entity
     * @return Entity
     */
    public function merge($entity)
    {
        $this->getEntityManager()->merge($entity);
        $this->getEntityManager()->flush();

        return $entity;
    }

    /**
     * @param \Doctrine\ORM\EntityRepository $entityRepository
     * @return \Haushaltportal\Service\DoctrineEntityRepository
     */
    public function setEntityRepository(EntityRepository $entityRepository)
    {
        $this->entityRepository = $entityRepository;
        return $this;
    }

    /**
     * @param EntityManager $entityManager
     * @return \Haushaltportal\Service\DoctrineEntityRepository
     */
    public function setEntityManager(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
        return $this;
    }

    /**
     * @return EntityManager
     */
    public function getEntityManager()
    {
        return $this->entityManager;
    }

    /**
     * Inject an EventManager instance
     *
     * @param  EventManagerInterface $eventManager
     * @return \Haushaltportal\Service\DoctrineEntityRepository
     */
    public function setEventManager(EventManagerInterface $eventManager)
    {
        $this->eventManager = $eventManager;
        return $this;
    }

    /**
     * Retrieve the event manager
     * Lazy-loads an EventManager instance if none registered.
     *
     * @return EventManagerInterface
     */
    public function getEventManager()
    {
        return $this->eventManager;
    }

    /**
     * Set service manager
     *
     * @param ServiceManager $serviceManager
     * @return \Haushaltportal\Service\DoctrineEntityRepository
     */
    public function setServiceManager(ServiceManager $serviceManager)
    {
        $this->serviceManager = $serviceManager;
        return $this;
    }

    /**
     * Get service manager
     *
     * @return ServiceManager
     */
    public function getServiceManager()
    {
        return $this->serviceManager;
    }
}