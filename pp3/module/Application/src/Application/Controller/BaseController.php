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

use Zend\Mvc\Controller\AbstractActionController;
use Zend\Session\Container;
use Zend\Mvc\MvcEvent;

define('ANONUSER', 'Anonymous');

class BaseController extends AbstractActionController {

    protected $_session;
    protected $_config;
    protected $_isAdmin;
    
    public function __construct($config) {
        $this->_config = $config;
        $this->_session = new Container();
    }
    
    public function onDispatch(MvcEvent $e) {
        $idp = null;
        $idpProviderId = array_key_exists('sessionIdpProviderId', $_SESSION) ? $_SESSION['sessionIdpProviderId'] : false;
        if($idpProviderId) {
            foreach($this->_config['loginConfig'] as $lc) {
                if($lc['id'] === $idpProviderId) {
                    $idp = $lc['name'];
                    break;
                }
            }
        }

        $this->layout()->setVariable('sessionUserId', array_key_exists('sessionUserId', $_SESSION) ? $_SESSION['sessionUserId'] : null);
        $this->layout()->setVariable('sessionUserName', array_key_exists('sessionUserName', $_SESSION) ? $_SESSION['sessionUserName'] : null);
        $this->layout()->setVariable('sessionIdp', $idp);
        $this->layout()->setVariable('sessionUserEmail', array_key_exists('sessionUserEmail', $_SESSION) ? $_SESSION['sessionUserEmail'] : false);
        $this->layout()->setVariable('isAdmin', $this->isAdmin());
        $this->layout()->setVariable('isAuthenticated', $this->isAuthenticated());
        $this->layout()->setVariable('isVerifier', $this->isVerifier());
        return parent::onDispatch($e);
    }

    protected function isAdmin() {
        return array_key_exists('isAdmin', $_SESSION) && $_SESSION['isAdmin'];
    }

    protected function isAuthenticated() {
        return !empty($_SESSION['sessionUserId']);
    }

    protected function isVerifier() {
        return array_key_exists('isVerifier', $_SESSION) && $_SESSION['isVerifier'];
    }

    protected function getAuthenticatedUserId() {
        return empty($_SESSION['sessionUserId']) ? false : $_SESSION['sessionUserId'];
    }
}