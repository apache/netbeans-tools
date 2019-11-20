<?php
/**
 * Licensed to the Apache Software Foundation (ASF) under one
 * or more contributor license agreements.  See the NOTICE file
 * distributed with this work for additional information
 * regarding copyright ownership.  The ASF licenses this file
 * to you under the Apache License, Version 2.0 (the
 * "License"); you may not use this file except in compliance
 * with the License.  You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

namespace Application\Controller;

use Knp\Component\Pager\PaginatorInterface;
use Zend\View\Model\ViewModel;
use DoctrineORMModule\Paginator\Adapter\DoctrinePaginator as DoctrineAdapter;
use Doctrine\ORM\Tools\Pagination\Paginator as ORMPaginator;
use Zend\Paginator\Paginator;

class IndexController extends BaseController {

    private $_pluginRepository;
    private $_paginator;
    private $_categoryRepository;
    private $_nbVersionRepository;

    public function __construct($pluginRepo, $config, PaginatorInterface $paginator, $nbVersionRepository, $categoryRepository, $pvRepo) {
        parent::__construct($config);
        $this->_pluginRepository = $pluginRepo;
        $this->_paginator = $paginator;
        $this->_nbVersionRepository = $nbVersionRepository;
        $this->_categoryRepository = $categoryRepository;
        $this->_pluginVersionRepository = $pvRepo;
    }

    public function indexAction() {   

        $pageLimit = 10;

        $page = $this->params()->fromQuery('page') ? $this->params()->fromQuery('page') : 1;
        $cat = $this->params()->fromQuery('cat');
        $nbv = $this->params()->fromQuery('nbv');
        $qb = $this->_pluginRepository->getPublicPluginsByNameQB($this->params()->fromQuery('search'), $cat, $nbv);
        $adapter = new DoctrineAdapter(new ORMPaginator($qb->getQuery(), true));        
        $paginator = new Paginator($adapter);
        $paginator->setDefaultItemCountPerPage($pageLimit);        
        $paginator->setCurrentPageNumber($page);

        return new ViewModel([
            'paginator' => $paginator,
            'search' => $this->params()->fromQuery('search'),
            'categories' => $this->_categoryRepository->getAllCategoriesSortByName(),
            'versions' => $this->_nbVersionRepository->findAll(),
            'best' => $this->_pluginRepository->getTopNDownloadedPublic(5),
            'latest' => $this->_pluginRepository->getLatestNPublic(5),
        ]);
    }
    
    public function catalogueAction() {
        $pId = $this->params()->fromQuery('id');
        if (!empty($pId)) {
            $plugin = $this->_pluginRepository->getPublicPluginById($pId);                    
        };   

        return new ViewModel([
            'plugin' => $plugin
        ]);
    }

    public function downloadAction() {
        $pId = $this->params()->fromQuery('id');
        if (!empty($pId)) {
            $pluginVersion = $this->_pluginVersionRepository->find($pId);                    
        };   
        if ($pluginVersion->getPlugin()->isPublic()) {
            $plugin = $pluginVersion->getPlugin();
            $plugin->incrementDownloadCounter();
            $this->_pluginRepository->persist($plugin);
            return  $this->redirect()->toUrl($pluginVersion->getUrl());
            die($pluginVersion->getUrl());
        } else {
            die('No plugin found.');
        }
       
    }
}
