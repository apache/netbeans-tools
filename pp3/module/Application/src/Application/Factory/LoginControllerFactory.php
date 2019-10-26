<?php

namespace Application\Factory;

use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\ServiceManager\FactoryInterface;
use Application\Controller\LoginController;
use Application\Repository\UserRepository;

class LoginControllerFactory implements FactoryInterface
{
    public function createService(ServiceLocatorInterface $serviceLocator)
    {

        $config = $serviceLocator->getServiceLocator()->get('config');
        $em = $serviceLocator->getServiceLocator()->get('Doctrine\ORM\EntityManager');
        $userRepository = new UserRepository();
        $userRepository->setEntityManager($em);

        return new LoginController($config, $userRepository);
    }
}
