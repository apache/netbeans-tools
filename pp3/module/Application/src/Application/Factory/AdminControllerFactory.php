<?php

namespace Application\Factory;

use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\ServiceManager\FactoryInterface;
use Application\Controller\AdminController;
use Application\Repository\PluginRepository;
use Application\Repository\PluginVersionRepository;
use Application\Repository\NbVersionPluginVersionRepository;
use Application\Repository\VerificationRepository;
use Application\Repository\VerifierRepository;
use Application\Repository\VerificationRequestRepository;
use Application\Repository\NbVersionRepository;
use Application\Repository\CategoryRepository;

class AdminControllerFactory implements FactoryInterface
{
    public function createService(ServiceLocatorInterface $serviceLocator) {
        $em = $serviceLocator->getServiceLocator()->get('Doctrine\ORM\EntityManager');

        $repository = new NbVersionPluginVersionRepository();
        $repository->setEntityManager($em);
        
        $vrepository = new VerificationRepository();
        $vrepository->setEntityManager($em);
       
        $verifierRepository = new VerifierRepository();
        $verifierRepository->setEntityManager($em);

        $verificationRequestRepository = new VerificationRequestRepository();
        $verificationRequestRepository->setEntityManager($em);

        $pluginRepository = new PluginRepository();
        $pluginRepository->setEntityManager($em);
        
        $nbVersionRepository = new NbVersionRepository();
        $nbVersionRepository->setEntityManager($em);
        
        $pluginVersionRepository = new PluginVersionRepository();
        $pluginVersionRepository->setEntityManager($em);

        $categoryRepository = new CategoryRepository();
        $categoryRepository->setEntityManager($em);
        
        $config = $serviceLocator->getServiceLocator()->get('config');

        return new AdminController($pluginRepository, $repository, $vrepository, 
                                    $verifierRepository, $verificationRequestRepository, 
                                    $nbVersionRepository, $pluginVersionRepository, $config, $categoryRepository);
    }
}
