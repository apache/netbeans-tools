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
 *   https://www.apache.org/licenses/LICENSE-2.0
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
    private $_verificationRepository;

    public function __construct($pluginRepo, $config, PaginatorInterface $paginator, $nbVersionRepository, $categoryRepository, $pvRepo, $verificationRepository) {
        parent::__construct($config);
        $this->_pluginRepository = $pluginRepo;
        $this->_paginator = $paginator;
        $this->_nbVersionRepository = $nbVersionRepository;
        $this->_categoryRepository = $categoryRepository;
        $this->_pluginVersionRepository = $pvRepo;
        $this->_verificationRepository = $verificationRepository;
    }

    public function indexAction() {   

        $pageLimit = 10;

        $page = $this->params()->fromQuery('page');
        $cat = $this->params()->fromQuery('cat');
        $nbv = $this->params()->fromQuery('nbv');
        $search = $this->params()->fromQuery('search');

        if ($page !== null) {
            $_SESSION["IndexController"]["page"] = $page;
        } else if (isset($_SESSION["IndexController"]["page"])) {
            $page = $_SESSION["IndexController"]["page"];
        }
        if ($cat !== null) {
            $_SESSION["IndexController"]["cat"] = $cat;
        } else if (isset($_SESSION["IndexController"]["cat"])) {
            $cat = $_SESSION["IndexController"]["cat"];
        }
        if ($nbv !== null) {
            $_SESSION["IndexController"]["nbv"] = $nbv;
        } else if (isset($_SESSION["IndexController"]["nbv"])) {
            $nbv = $_SESSION["IndexController"]["nbv"];
        }
        if ($search !== null) {
            $_SESSION["IndexController"]["search"] = $search;
        } else if (isset($_SESSION["IndexController"]["search"])) {
            $search = $_SESSION["IndexController"]["search"];
        }

        $qb = $this->_pluginRepository->getPublicPluginsByNameQB($search, $cat, $nbv);
        $adapter = new DoctrineAdapter(new ORMPaginator($qb->getQuery(), true));
        $paginator = new Paginator($adapter);
        $paginator->setDefaultItemCountPerPage($pageLimit);
        $paginator->setCurrentPageNumber($page);

        return new ViewModel([
            'paginator' => $paginator,
            'search' => $search,
            'cat' => $cat,
            'nbv' => $nbv,
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
        }

        return new ViewModel([
            'plugin' => $plugin
        ]);
    }

    public function downloadAction() {
        $pId = $this->params()->fromRoute('pathParam');
        $pluginVersion = false;
        if (!empty($pId)) {
            $pluginVersion = $this->_pluginVersionRepository->find($pId);
        };
        if ($pluginVersion && $pluginVersion->getPlugin()->isPublic()) {
            $plugin = $pluginVersion->getPlugin();
            $plugin->incrementDownloadCounter();
            $this->_pluginRepository->persist($plugin);
            return  $this->redirect()->toUrl($pluginVersion->getUrl());
        } else {
            $this->getResponse()->setStatusCode(404);
            return new ViewModel(['message' => 'Plugin was not found']);
        }
    }

    public function verificationLogAction() {
        $vId = $this->params()->fromQuery('vId');
        if ($vId) {
            $verification = $this->_verificationRepository->find($vId);
        }
        $result = new ViewModel([
            'verification' => $verification,
        ]);
        $result->setTerminal(true);
        return $result;
    }

}
