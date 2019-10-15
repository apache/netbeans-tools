<?php

namespace Application\Factory;

use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\ServiceManager\FactoryInterface;
use Application\Repository\PluginRepository;
use Application\Repository\PluginVersionRepository;
use Application\Repository\CategoryRepository;
use Application\Controller\PluginController;
use Application\Repository\VerifierRepository;

class PluginControllerFactory implements FactoryInterface
{
    public function createService(ServiceLocatorInterface $serviceLocator) {
        $em = $serviceLocator->getServiceLocator()->get('Doctrine\ORM\EntityManager');

        $repository = new PluginRepository();
        $repository->setEntityManager($em);
        
        $pvRepository = new PluginVersionRepository();
        $pvRepository->setEntityManager($em);

        $categRepository = new CategoryRepository();
        $categRepository->setEntityManager($em);

        $config = $serviceLocator->getServiceLocator()->get('config');

        $verifierRepository = new VerifierRepository();
        $verifierRepository->setEntityManager($em);

        return new PluginController($repository, $pvRepository, $categRepository, $config, $verifierRepository);
    }
}
