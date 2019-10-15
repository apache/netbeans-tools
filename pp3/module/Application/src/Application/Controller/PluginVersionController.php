<?php

namespace Application\Controller;

use Application\Controller\BaseController;
use Zend\View\Model\ViewModel;
use Zend\Session\Container;
use Application\Pp\MavenDataLoader;
use Application\Entity\Plugin;
use Application\Entity\PluginVersion;
use Application\Entity\NbVersionPluginVersion;
use HTMLPurifier;
use HTMLPurifier_Config;

define('PLUGIN_SESSION_NAMESPACE', 'pp3_plugin_session');

class PluginVersionController extends BaseController {

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
        $pluginVersion = $this->_pluginVersionRepository->find($pvId);        
        if (!$pluginVersion || empty($pvId) || !$pluginVersion->getPlugin()->isOwnedBy($this->_sessionUserId)) {
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
        
        return new ViewModel([
            'pluginVersion' => $pluginVersion,
            'nbVersions' => $this->_nbVersionRepository->findAll(),
            'verifiedNbVersionIds' => $verifiedNbVersionIds,
        ]);
    }

    public function deleteAction() {
        $pId = $this->params()->fromQuery('id');
        $pluginVersion = $this->_pluginVersionRepository->find($pId);        
        if (!$pluginVersion || empty($pId) || !$pluginVersion->getPlugin()->isOwnedBy($this->_sessionUserId)) {
            return $this->redirect()->toRoute('plugin', array(
                'action' => 'list'
            ));
        };        
        $this->flashMessenger()->setNamespace('success')->addMessage('Plugin version '.$pluginVersion->getVersion().' deleted');
        $this->_pluginVersionRepository->remove($pluginVersion);
        return $this->redirect()->toRoute('plugin', array(
            'action' => 'list'
        ));
    }
}
