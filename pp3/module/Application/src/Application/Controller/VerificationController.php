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
use Application\Entity\Verification;
use Zend\Mail;
use HTMLPurifier;
use HTMLPurifier_Config;
use \Application\Entity\VerificationRequest;

class VerificationController extends AuthenticatedController {

    /**
     * @var \Application\Repository\NbVersionPluginVersionRepository
     */
    private $_nbVersionPluginVersionRepository;
    /**
     * @var \Application\Repository\VerificationRepository
     */
    private $_verificationRepository;
    /**
     * @var \Application\Repository\UserRepository
     */
    private $_userRepository;
    /**
     * @var \Application\Repository\VerificationRequestRepository
     */
    private $_verificationRequestRepository;
    /**
     * @var \Application\Repository\PluginVersionRepository
     */
    private $_pluginVersionRepository;
    /**
     * @var \Application\Repository\NbVersionRepository
     */
    private $_nbVersionRepository;

    public function __construct($nbVersionPluginVersionRepo, $verificationRepo,
                                $userRepository, $verificationRequestRepository, $config,
                                $pluginVersionRepository, $nbVersionRepository) {
        parent::__construct($config);
        $this->_nbVersionPluginVersionRepository = $nbVersionPluginVersionRepo;
        $this->_verificationRepository = $verificationRepo;
        $this->_userRepository = $userRepository;
        $this->_verificationRequestRepository = $verificationRequestRepository;
        $this->_pluginVersionRepository = $pluginVersionRepository;
        $this->_nbVersionRepository = $nbVersionRepository;
    }

    public function listAction() {
        if(!$this->_checkVerifierUser()) {
            return;
        }
        /**
         * @var \Application\Entity\VerificationRequest[]
         */
        $verificationRequests = [];
        foreach($this->_verificationRequestRepository->getVerificationRequestsForVerifier($this->getAuthenticatedUserId()) as $vr) {
            $verificationRequests[$vr->getVerification()->getId()] = $vr;
        }
        return new ViewModel([
            'verificationRequests' => $verificationRequests,
            'pendingVerifications' => $this->_verificationRepository->getPendingVerifications(),
            'isAdmin' => $this->isAdmin()
        ]);
    }

    public function voteGoAction() {
        if(!$this->_checkVerifierUser()) {
            return;
        }
        return $this->_handleVote(\Application\Entity\VerificationRequest::VOTE_GO);
    }

    public function voteNoGoAction() {
        if(!$this->_checkVerifierUser()) {
            return;
        }
        return $this->_handleVote(\Application\Entity\VerificationRequest::VOTE_NOGO);
    }

    public function voteUndecidedAction() {
        if(!$this->_checkVerifierUser()) {
            return;
        }
        return $this->_handleVote(\Application\Entity\VerificationRequest::VOTE_UNDECIDED);
    }

    private function _handleVote($vote) {
        $verificationId = $this->params()->fromQuery('id');
        if (empty($verificationId)) {
            return $this->redirect()->toRoute('verification', array(
                'action' => 'list'
            ));
        }
        $verification = $this->_verificationRepository->find($verificationId);
        if (!$verification) {
            return $this->redirect()->toRoute('verification', array(
                'action' => 'list'
            ));
        }
        $req = null;
        foreach($verification->getVerificationRequests() as $verificationRequest) {
            if($verificationRequest->getVerifierId() == $this->getAuthenticatedUserId()) {
                $req = $verificationRequest;
                break;
            }
        }
        if($req == null) {
            $req = new VerificationRequest();
            $req->setCreatedAt(new \DateTime('now'));
            $req->setVerification($verification);
            $req->setVerifier($this->_userRepository->find($this->getAuthenticatedUserId()));
            $req->setVote(VerificationRequest::VOTE_UNDECIDED);
            $verification->addVerificationRequest($req);
        }

        $req->setVote($vote);
        $req->setVotedAt(new \DateTime('now'));
        $comment = $this->params()->fromPost('comment');
        if (!empty($comment)) {
            $config = HTMLPurifier_Config::createDefault();
            $purifier = new HTMLPurifier($config);
            $comment = $purifier->purify($comment);
            $req->setComment($comment);
        }
        $this->_verificationRequestRepository->persist($req);
        $votesBreakdown = $this->_verificationRequestRepository->getVotesBreakdownForVerification($req->getVerification()->getid());
        $verification = $req->getVerification();
        $verification->resolveStatus($votesBreakdown);
        $this->_verificationRepository->persist($verification);
        if ($verification->getStatus() == \Application\Entity\Verification::STATUS_NOGO) {
            $this->_sendNoGoNotification($req->getVerification(), $comment);
        } elseif ($verification->getStatus() == \Application\Entity\Verification::STATUS_GO) {
            $this->_sendGoNotification($req->getVerification(), $comment);
            $nbVersion = $verification->getNbVersionPluginVersion()->getNbVersion();
            $nbVersion->requestCatalogRebuild();
            $this->_nbVersionRepository->persist($nbVersion);
            // Remove all other verifications for this plugin version
            foreach ($verification->getNbVersionPluginVersion()->getPluginVersion()->getPlugin()->getVersions() as $pv) {
                foreach ($pv->getNbVersionsPluginVersions() as $nvpv) {
                    if ($verification->getNbVersionPluginVersion()->getNbVersionId() == $nvpv->getNbVersionId()
                        && $nvpv->getVerification() != null
                        && $nvpv->getVerificationId() != $verification->getId()) {
                        $this->_verificationRepository->remove($nvpv->getVerification());
                        $nvpv->setVerification(null);
                        $this->_nbVersionPluginVersionRepository->persist($nvpv);
                    }
                }
            }
        }
        $this->flashMessenger()->setNamespace('success')->addMessage('Vote cast');
        return $this->redirect()->toRoute('verification', array(
            'action' => 'list'
        ));
    }

    public function voteMasterGoAction() {
        if(!$this->_checkAdminUser()) {
            return;
        }
        return $this->_handleMasterVote(\Application\Entity\Verification::STATUS_GO);
    }

    public function voteMasterNoGoAction() {
        if(!$this->_checkAdminUser()) {
            return;
        }
        return $this->_handleMasterVote(\Application\Entity\Verification::STATUS_NOGO);
    }

    private function _handleMasterVote($vote) {
        $verId = $this->params()->fromQuery('id');
        if (empty($verId) || !$this->isAdmin()) {
            return $this->redirect()->toRoute('verification', array(
                'action' => 'list'
            ));
        }
        $ver = $this->_verificationRepository->find($verId);
        if (!$ver) {
            return $this->redirect()->toRoute('verification', array(
                'action' => 'list'
            ));
        }
        $ver->setStatus($vote);
        $comment = $this->params()->fromPost('comment');
        if (!empty($comment) && $vote == \Application\Entity\Verification::STATUS_NOGO) {
            $config = HTMLPurifier_Config::createDefault();
            $purifier = new HTMLPurifier($config);
            $comment = $purifier->purify($comment);
            $this->_sendNoGoNotification($ver, $comment);
        }
        $this->_verificationRepository->persist($ver);
        // delete related requests
        $this->_verificationRequestRepository->deleteRequestsOfVerification($ver->getId());
        // Remove all other verifications for this plugin version
        foreach ($ver->getNbVersionPluginVersion()->getPluginVersion()->getPlugin()->getVersions() as $pv) {
            foreach ($pv->getNbVersionsPluginVersions() as $nvpv) {
                if ($ver->getNbVersionPluginVersion()->getNbVersionId() == $nvpv->getNbVersionId()
                    && $nvpv->getVerification() != null
                    && $nvpv->getVerificationId() != $ver->getId()) {
                    $this->_verificationRepository->remove($nvpv->getVerification());
                    $nvpv->setVerification(null);
                    $this->_nbVersionPluginVersionRepository->persist($nvpv);
                }
            }
        }
        $this->flashMessenger()->setNamespace('success')->addMessage('Master vote cast');
        return $this->redirect()->toRoute('verification', array(
            'action' => 'list'
        ));
    }

    public function createAction() {
        $nbvPvId = $this->params()->fromQuery('nbvPvId');
        if (empty($nbvPvId)) {
            return $this->redirect()->toRoute('plugin', array(
                    'action' => 'list'
            ));
        }
        /** @var \Application\Entity\NbVersionPluginVersion $nbVersionPluginVersion   */
        $nbVersionPluginVersion = $this->_nbVersionPluginVersionRepository->find($nbvPvId);
        if (!$nbVersionPluginVersion) {
            return $this->redirect()->toRoute('plugin', array(
                    'action' => 'list'
            ));
        }
        $plugin = $nbVersionPluginVersion->getPluginVersion()->getPlugin();
        if (!$plugin->isOwnedBy($this->getAuthenticatedUserId()) && !$this->isAdmin()) {
            return $this->redirect()->toRoute('plugin', array(
                'action' => 'list'
            ));
        }

        $existingVerification = null;
        foreach($nbVersionPluginVersion->getPluginVersion()->getNbVersionsPluginVersions() as $nvpv) {
            if($nvpv->getVerification() != null && $nvpv->getVerification()->getStatus() == Verification::STATUS_GO) {
                $existingVerification = $nvpv->getVerification();
            }
        }

        if ($existingVerification == null) {
            // create verification
            $verification = new Verification();
            $verification->setStatus(Verification::STATUS_REQUESTED);
            $verification->setCreatedAt(new \DateTime('now'));
            $verification->setPluginVersionId($nbvPvId);
            $this->_verificationRepository->persist($verification);
            // join it to nbVersionPluginVersion
            $nbVersionPluginVersion->setVerification($verification);
            $this->_nbVersionPluginVersionRepository->persist($nbVersionPluginVersion);
            $verification->setNbVersionPluginVersion($nbVersionPluginVersion);
            // generate requests for all verifiers
            $verifiers = $this->_userRepository->findVerifier();
            $verification->createRequests($verifiers, $plugin);
            $this->_verificationRepository->persist($verification);
            foreach ($verification->getVerificationRequests() as $req) {
                $req->sendVerificationMail($plugin);
            }
            $this->flashMessenger()->setNamespace('success')->addMessage('Verification Requested.');
        } else {
            // Create a copy of the existing verification
            $verification = new Verification();
            $verification->setStatus($existingVerification->getStatus());
            $verification->setCreatedAt(new \DateTime('now'));
            $verification->setPluginVersionId($nbvPvId);
            $this->_verificationRepository->persist($verification);
            // join it to nbVersionPluginVersion
            $nbVersionPluginVersion->setVerification($verification);
            $this->_nbVersionPluginVersionRepository->persist($nbVersionPluginVersion);
            $verification->setNbVersionPluginVersion($nbVersionPluginVersion);

            foreach ($existingVerification->getVerificationRequests() as $existingRequest) {
                // Only transfer votes with decisions
                if ($existingRequest->getVote() != VerificationRequest::VOTE_UNDECIDED) {
                    $req = new VerificationRequest();
                    $req->setCreatedAt(new \DateTime('now'));
                    $req->setVotedAt($existingRequest->getVotedAt());
                    $req->setVerification($verification);
                    $req->setVerifier($existingRequest->getVerifier());
                    $req->setVote($existingRequest->getVote());
                    $req->setComment($existingRequest->getComment());
                    $req->setCopy(true);
                    $verification->addVerificationRequest($req);
                }
            }

            $this->_verificationRepository->persist($verification);

            // Remove all other verifications for this plugin version
            foreach ($plugin->getVersions() as $pv) {
                foreach ($pv->getNbVersionsPluginVersions() as $nvpv) {
                    if ($verification->getNbVersionPluginVersion()->getNbVersionId() == $nvpv->getNbVersionId()
                        && $nvpv->getVerification() != null
                        && $nvpv->getVerification()->getNbVersionPluginVersion()->getId() != $nbvPvId) {
                        $this->_verificationRepository->remove($nvpv->getVerification());
                        $nvpv->setVerification(null);
                        $this->_nbVersionPluginVersionRepository->persist($nvpv);
                    }
                }
            }

            $this->flashMessenger()->setNamespace('success')->addMessage('Verification done based on previous verification.');

            $this->rebuildAllCatalogs();
        }
        return $this->redirect()->toUrl('../plugin-version/edit?id=' . $nbVersionPluginVersion->getPluginVersion()->getId());
    }

    private function _sendNoGoNotification($verification, $comment) {
        $plugin = $verification->getNbVersionPluginVersion()->getPluginVersion()->getPlugin();
        $nbVersion = $verification->getNbVersionPluginVersion()->getNbVersion()->getVersion();
        $pluginVersion = $verification->getNbVersionPluginVersion()->getPluginVersion()->getVersion();
        $mail = new Mail\Message();
        foreach($plugin->getAuthors() as $user) {
            $mail->addTo($user->getEmail());
        }
        $mail->setFrom('webmaster@netbeans.apache.org', 'NetBeans webmaster');
        $mail->setSubject('Verification of your '.$plugin->getName().' is complete');
        $mail->setBody('Hello plugin owner,

this is to inform you that publishing of your plugin on the Plugin Portal Update Center was NOT approved. Please read comments from plugin verifiers below, if any.

Plugin: '.$plugin->getName().'
NetBeans version: '.$nbVersion.'
Verification status: NOGO
Comments: '.$comment.'

Do not give up though. Address the comment(s), upload new binary of your plugin and request the verification again!

Good luck!
NetBeans development team

P.S.: This is an automatic email. DO NOT REPLY to this email.');
        $transport = new Mail\Transport\Sendmail();
        $transport->send($mail);
        // die(var_dump($mail->getBody()));
    }

    private function _sendGoNotification($verification, $comment) {
        /* @var $plugin Application\Entity\Plugin */
        $plugin = $verification->getNbVersionPluginVersion()->getPluginVersion()->getPlugin();
        $nbVersion = $verification->getNbVersionPluginVersion()->getNbVersion()->getVersion();
        $pluginVersion = $verification->getNbVersionPluginVersion()->getPluginVersion()->getVersion();
        $mail = new Mail\Message();
        foreach($plugin->getAuthors() as $user) {
            $mail->addTo($user->getEmail());
        }
        $mail->setFrom('webmaster@netbeans.apache.org', 'NetBeans webmaster');
        $mail->setSubject('Verification of your '.$plugin->getName().' is complete');
        $mail->setBody('Hello plugin owner,

this is to inform you that publishing of your plugin on the Plugin Portal Update Center was approved.

Plugin: '.$plugin->getName().'
NetBeans version: '.$nbVersion.'
Verification status: GO

Your plugin should be already available on the Plugin Portal Update Center by now:

https://plugins.netbeans.apache.org/data/'.$nbVersion.'/catalog.xml.gz

Thanks for your contribution!
NetBeans development team

P.S.: This is an automatic email. DO NOT REPLY to this email.');
        $transport = new Mail\Transport\Sendmail();
        $transport->send($mail);
        // die(var_dump($mail->getBody()));
    }

    private function _checkVerifierUser() {
        if (!$this->isVerifier()) {
            $this->redirect()->toRoute('home', array(
                'action' => 'index'
            ));
            return false;
        }
        return true;
    }

    private function _checkAdminUser() {
        if (!$this->isAdmin()) {
            return $this->redirect()->toRoute('home', array(
                'action' => 'index'
            ));
            return false;
        }
        return true;
    }

    private function rebuildAllCatalogs() {
        $versions = $this->_nbVersionRepository->getEntityRepository()->findAll();
        foreach ($versions as $v) {
            $v->requestCatalogRebuild();
            $this->_nbVersionRepository->persist($v);
        }
    }
}
