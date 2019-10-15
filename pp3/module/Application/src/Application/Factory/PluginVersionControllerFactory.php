<?php

namespace Application\Factory;

use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\ServiceManager\FactoryInterface;
use Application\Repository\PluginRepository;
use Application\Repository\PluginVersionRepository;
use Application\Repository\NbVersionRepository;
use Application\Repository\NbVersionPluginVersionRepository;
use Application\Controller\PluginVersionController;
use Application\Repository\VerificationRepository;

class pluginVersionControllerFactory implements FactoryInterface
{
    public function createService(ServiceLocatorInterface $serviceLocator) {
        $em = $serviceLocator->getServiceLocator()->get('Doctrine\ORM\EntityManager');
        
        $repository = new PluginRepository();
        $repository->setEntityManager($em);
        $pvRepository = new PluginVersionRepository();
        $pvRepository->setEntityManager($em);
        $nbvRepository = new NbVersionRepository();
        $nbvRepository->setEntityManager($em);        
        $nbVersionPluginVersionRepo = new NbVersionPluginVersionRepository();
        $nbVersionPluginVersionRepo->setEntityManager($em);
        $config = $serviceLocator->getServiceLocator()->get('config');
        $vrepository = new VerificationRepository();
        $vrepository->setEntityManager($em);


        return new PluginVersionController($repository, $pvRepository, $nbvRepository, $nbVersionPluginVersionRepo, $config, $vrepository);
    }
}
