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

use Zend\View\Model\ViewModel;
use Application\Entity\NbVersionPluginVersion;
use Application\Pp\Catalog;
use HTMLPurifier;
use HTMLPurifier_Config;

define('PLUGIN_SESSION_NAMESPACE', 'pp3_plugin_session');

class PluginVersionController extends AuthenticatedController {

    public function __construct($pluginRepo, $pvRepo, $nbvRepo, $nbVersionPluginVersionRepo, $config, $verificationRepo) {
        parent::__construct($config);
        $this->_pluginRepository = $pluginRepo;
        $this->_pluginVersionRepository = $pvRepo;
        $this->_nbVersionRepository = $nbvRepo;
        $this->_nbVersionPluginVersionRepository = $nbVersionPluginVersionRepo;
        $this->_verificationRepository = $verificationRepo;
    }

    public function editAction() {
        $pvId = $this->params()->fromQuery('id');
        $search = $this->params()->fromQuery('search');
        $pluginVersion = $this->_pluginVersionRepository->find($pvId);        
        if ((!$pluginVersion || empty($pvId) || !$pluginVersion->getPlugin()->isOwnedBy($this->getAuthenticatedUserId())) && !$this->isAdmin()) {
            return $this->redirect()->toRoute('plugin', array(
                'action' => 'list'
            ));
        }        
        $req = $this->request;
        if ($req->isPost()) {
            $relnotes = $this->params()->fromPost('relnotes');
            if (!empty($relnotes)) {
                $config = HTMLPurifier_Config::createDefault();
                $purifier = new HTMLPurifier($config);
                $pluginVersion->setRelnotes($purifier->purify($relnotes));
                $this->_pluginVersionRepository->persist($pluginVersion);
                $showFlash = true;
            }
            // handle nb versions assignmnent
            $selectedNbVersionIds = $this->params()->fromPost('nbVersion_ids');
            if (!empty($selectedNbVersionIds)) {
                $assignedNbVersions = array();
                foreach($pluginVersion->getNbVersionsPluginVersions() as $nbvPv) {
                    // remove if deselected
                    if (!in_array($nbvPv->getNbVersionId(), $selectedNbVersionIds)) {
                        $this->_nbVersionPluginVersionRepository->remove($nbvPv);
                        $showFlash = true;
                    } else {
                        $assignedNbVersions[] = $nbvPv->getNbVersionId();
                    }
                }                                
                // and add newly selected
                foreach($selectedNbVersionIds as $selectedNbVersionId) {
                    if (!in_array($selectedNbVersionId, $assignedNbVersions)) {
                        $newNbvpv = new NbVersionPluginVersion();
                        $newNbvpv->setNbVersion($this->_nbVersionRepository->find($selectedNbVersionId));
                        $newNbvpv->setPluginVersion($pluginVersion);
                        $pluginVersion->addNbVersion($newNbvpv);
                        $showFlash = true;
                    }
                }
                $this->_pluginVersionRepository->persist($pluginVersion);
            } else {
                // remove all
                foreach($pluginVersion->getNbVersionsPluginVersions() as $nbvPv) {                
                    $this->_nbVersionPluginVersionRepository->remove($nbvPv);
                    $showFlash = true;
                }
            }

            $this->rebuildAllCatalogs();
            
            if ($showFlash) {
                $this->flashMessenger()->setNamespace('success')->addMessage('Plugin version updated');
                return $this->redirect()->toUrl('./edit?id='.$pluginVersion->getId());                
            }
        }
        
        // droping verification
        $verificationId = $this->params()->fromQuery('verifId');
        if ($verificationId) {
            $verification = $this->_verificationRepository->find($verificationId);
            if ($verification && $verification->getNbVersionPluginVersion()->getPluginVersion()->getId() == $pluginVersion->getId()) {
                $this->_verificationRepository->remove($verification);
                $nbvPv = $verification->getNbVersionPluginVersion();
                $nbvPv->setVerification(null);
                $this->_nbVersionPluginVersionRepository->persist($nbvPv);
                $this->flashMessenger()->setNamespace('success')->addMessage('Verification dropped');
                return $this->redirect()->toUrl('./edit?id='.$pluginVersion->getId());
            }
        }
        
        
        $verifiedNbVersions = $this->_nbVersionRepository->getVerifiedNbVersionIdsForPlugin($pluginVersion->getPlugin()->getId());
        $verifiedNbVersionIds = array();
        foreach($verifiedNbVersions as $v) {
            $verifiedNbVersionIds[]=$v['id'];
        }

        if ($this->isAdmin() && !empty($search)) {
            $backUrl = $this->url()->fromRoute('admin', array('action' => 'index'), array('query' => array('search' => $search)));
        } else {
            $backUrl = $this->url()->fromRoute('plugin', array('action' => 'list'));
        }
        
        return new ViewModel([
            'pluginVersion' => $pluginVersion,
            'nbVersions' => $this->_nbVersionRepository->findAll(),
            'verifiedNbVersionIds' => $verifiedNbVersionIds,
            'return' => $backUrl, 
        ]);
    }

    public function deleteAction() {
        $pId = $this->params()->fromQuery('id');
        $pluginVersion = $this->_pluginVersionRepository->find($pId);        
        if ((!$pluginVersion || empty($pId) || !$pluginVersion->getPlugin()->isOwnedBy($this->getAuthenticatedUserId())) && !$this->isAdmin()) {
            return $this->redirect()->toRoute('plugin', array(
                'action' => 'list'
            ));
        };        
        $this->flashMessenger()->setNamespace('success')->addMessage('Plugin version '.$pluginVersion->getVersion().' deleted');
        $this->_pluginVersionRepository->remove($pluginVersion);
        $this->rebuildAllCatalogs();
        return $this->redirect()->toRoute('plugin', array(
            'action' => 'list'
        ));
    }

    private function rebuildAllCatalogs() {
        $versions = $this->_nbVersionRepository->getEntityRepository()->findAll();
        foreach ($versions as $v) {
            $version = $v->getVersion();
            $itemsVerified = $this->_pluginVersionRepository->getVerifiedVersionsByNbVersion($version);
            $itemsExperimental = $this->_pluginVersionRepository->getNonVerifiedVersionsByNbVersion($version);
            $catalog = new Catalog($this->_pluginVersionRepository, $version, $itemsVerified, false, $this->_config['pp3']['dtdPath'], $this->_getCatalogLink());
            try {
                $xml = $catalog->asXml(true);
                $catalog->storeXml($this->_config['pp3']['catalogSavepath'], $xml);
            } catch (\Exception $e) { }                 
            
            $catalog = new Catalog($this->_pluginVersionRepository, $version, $itemsExperimental, true, $this->_config['pp3']['dtdPath'], $this->_getCatalogLink());
            try {
                $xml = $catalog->asXml(true);
                $catalog->storeXml($this->_config['pp3']['catalogSavepath'], $xml);
            } catch (\Exception $e) { }                 
            
        }
    }

    private function _getCatalogLink() {
        return $_SERVER["REQUEST_SCHEME"].'://'.$_SERVER["HTTP_HOST"].$this->url()->fromRoute('catalogue', array('action' => 'download')).'?id=';
    }
}
