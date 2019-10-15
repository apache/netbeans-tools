<?php

namespace Application\Factory;

use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\ServiceManager\FactoryInterface;
use Application\Repository\PluginRepository;
use Application\Controller\IndexController;
use Application\Repository\NbVersionRepository;
use Application\Repository\CategoryRepository;
use Application\Repository\PluginVersionRepository;

class IndexControllerFactory implements FactoryInterface
{
    public function createService(ServiceLocatorInterface $serviceLocator) {
        $em = $serviceLocator->getServiceLocator()->get('Doctrine\ORM\EntityManager');
        $repository = new PluginRepository();
        $repository->setEntityManager($em);
        $config = $serviceLocator->getServiceLocator()->get('config');
        $paginator = $serviceLocator->getServiceLocator()->get('paginator');

        $nbVersionRepository = new NbVersionRepository();
        $nbVersionRepository->setEntityManager($em);

        $categoryRepository = new CategoryRepository();
        $categoryRepository->setEntityManager($em);

        $pvRepository = new PluginVersionRepository();
        $pvRepository->setEntityManager($em);

        return new IndexController($repository, $config, $paginator, $nbVersionRepository, $categoryRepository, $pvRepository);
    }
}
