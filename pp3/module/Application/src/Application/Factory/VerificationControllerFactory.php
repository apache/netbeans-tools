<?php

namespace Application\Factory;

use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\ServiceManager\FactoryInterface;
use Application\Controller\VerificationController;
use Application\Repository\NbVersionPluginVersionRepository;
use Application\Repository\VerificationRepository;
use Application\Repository\VerifierRepository;
use Application\Repository\VerificationRequestRepository;
use Application\Repository\PluginVersionRepository;

class VerificationControllerFactory implements FactoryInterface
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
        $config = $serviceLocator->getServiceLocator()->get('config');

        $pluginVersionRepository = new PluginVersionRepository();
        $pluginVersionRepository->setEntityManager($em);

        return new VerificationController($repository, $vrepository, $verifierRepository, $verificationRequestRepository, $config, $pluginVersionRepository);
    }
}
