<?php

namespace Application\Controller;

use Application\Controller\BaseController;
use Zend\View\Model\ViewModel;
use Application\Entity\Verification;
use Application\Pp\Catalog;
use Zend\Session\Container;
use Zend\Mail;
use HTMLPurifier;
use HTMLPurifier_Config;

class VerificationController extends BaseController {

    private $_nbVersionPluginVersionRepository;
    private $_verificationRepository;
    private $_verifierRepository;
    private $_verificationRequestRepository;
    private $_pluginVersionRepository;

    public function __construct($nbVersionPluginVersionRepo, $verificationRepo, 
                                $verifierRepository, $verificationRequestRepository, $config,
                                $pluginVersionRepository) {
        parent::__construct($config);
        $this->_nbVersionPluginVersionRepository = $nbVersionPluginVersionRepo;
        $this->_verificationRepository = $verificationRepo;
        $this->_verifierRepository = $verifierRepository;
        $this->_verificationRequestRepository = $verificationRequestRepository;
        $this->_pluginVersionRepository = $pluginVersionRepository;

        $session = new Container();
        $this->_sessionUserId = $session->userId ? $session->userId : ANONUSER;
    } 

    public function listAction() {
        return new ViewModel([
            'verificationRequests' => $this->_verificationRequestRepository->getVerificationRequestsForVerifier($this->_sessionUserId),
            'isAdmin' => $this->_isAdmin,
        ]);
    }

    public function voteGoAction() {
        $this->_handleVote(\Application\Entity\VerificationRequest::VOTE_GO);
    }

    public function voteNoGoAction() {
        $this->_handleVote(\Application\Entity\VerificationRequest::VOTE_NOGO);
    }

    public function voteUndecidedAction() {
        $this->_handleVote(\Application\Entity\VerificationRequest::VOTE_UNDECIDED);
    }

    private function _handleVote($vote) {
        $reqId = $this->params()->fromQuery('id');
        $bailOut = false;
        if (empty($reqId)) {
            $bailOut = true;
        }
        $req = $this->_verificationRequestRepository->find($reqId);
        if (!$req || $req->getVerifier()->getUserId() !== $this->_sessionUserId) {
            $bailOut = true;
        }
        if ($bailOut) {
            return $this->redirect()->toRoute('verification', array(
                'action' => 'list'
            ));
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
            $version = $verification->getNbVersionPluginVersion()->getNbVersion()->getVersion();
            $items = $this->_pluginVersionRepository->getVerifiedVersionsByNbVersion($version);
            $link = $_SERVER["REQUEST_SCHEME"].'://'.$_SERVER["HTTP_HOST"].$this->url()->fromRoute('catalogue', array('action' => 'download')).'?id=';
            $catalog = new Catalog($version, $items, false, $this->_config['pp3']['dtdPath'], $link);            
            try {
                $xml = $catalog->asXml(true);
                $catalog->storeXml($this->_config['pp3']['catalogSavepath'], $xml);
            } catch (\Exception $e){
                $this->flashMessenger()->setNamespace('error')->addMessage($e->getMessage());                        
            }    
            $this->_sendGoNotification($req->getVerification(), $comment);
        }
        $this->flashMessenger()->setNamespace('success')->addMessage('Vote cast');
        return $this->redirect()->toRoute('verification', array(
            'action' => 'list'
        ));
    }

    public function voteMasterGoAction() {
        $this->_handleMasterVote(\Application\Entity\Verification::STATUS_GO);
    }

    public function voteMasterNoGoAction() {
        $this->_handleMasterVote(\Application\Entity\Verification::STATUS_NOGO);
    }

    private function _handleMasterVote($vote) {
        $verId = $this->params()->fromQuery('id');
        $bailOut = false;
        if (empty($verId) || !$this->_isAdmin) {
            $bailOut = true;
        }
        $ver = $this->_verificationRepository->find($verId);
        if (!$ver) {
            $bailOut = true;
        }
        if ($bailOut) {
            return $this->redirect()->toRoute('verification', array(
                'action' => 'list'
            ));
        }
        $ver->setStatus($vote);
        $comment = $this->params()->fromPost('comment');
        if (!empty($comment)) {
            $config = HTMLPurifier_Config::createDefault();
            $purifier = new HTMLPurifier($config);
            $comment = $purifier->purify($comment);
            $this->_sendNoGoNotification($ver, $comment);
        }
        $this->_verificationRepository->persist($ver);
        // delete related requests 
        $this->_verificationRequestRepository->deleteRequestsOfVerification($ver->getId());        
        $this->flashMessenger()->setNamespace('success')->addMessage('Master vote cast');
        return $this->redirect()->toRoute('verification', array(
            'action' => 'list'
        ));
    }

    public function createAction() {
        $bailOut = false;
        $nbvPvId = $this->params()->fromQuery('nbvPvId');
        if (empty($nbvPvId)) {
            $bailOut = true;
        }
        $nbVersionPluginVersion = $this->_nbVersionPluginVersionRepository->find($nbvPvId);
        if (!$nbVersionPluginVersion) {
            $bailOut = true;
        }
        $plugin = $nbVersionPluginVersion->getPluginVersion()->getPlugin();
        if (!$plugin->isOwnedBy($this->_sessionUserId)) {
            $bailOut = true;
        }
        if ($bailOut) {
            return $this->redirect()->toRoute('plugin', array(
                'action' => 'list'
            ));
        }
        // create verification
        $verification = new Verification();
        $verification->setStatus(Verification::STATUS_REQUESTED);
        $verification->setCreatedAt(new \DateTime('now'));
        $this->_verificationRepository->persist($verification);
        // join it to nbVersionPluginVersion
        $nbVersionPluginVersion->setVerification($verification);
        $this->_nbVersionPluginVersionRepository->persist($nbVersionPluginVersion);
        // generate requests for all verifiers
        $verifiers = $this->_verifierRepository->findAll();
        $verification->createRequests($verifiers, $plugin);
        $this->_verificationRepository->persist($verification);
        $this->flashMessenger()->setNamespace('success')->addMessage('Verification Requested.');
        return $this->redirect()->toUrl('../plugin-version/edit?id='.$nbVersionPluginVersion->getPluginVersion()->getId());         
    }

    private function _sendNoGoNotification($verification, $comment) {
        $plugin = $verification->getNbVersionPluginVersion()->getPluginVersion()->getPlugin();
        $nbVersion = $verification->getNbVersionPluginVersion()->getNbVersion()->getVersion();
        $pluginVersion = $verification->getNbVersionPluginVersion()->getPluginVersion()->getVersion();
        $mail = new Mail\Message();
        $mail->addTo($plugin->getAuthor());
        $mail->setFrom('webmaster@netbeans.apache.org', 'NetBeans webmaster');
        $mail->setSubject('Verification of your '.$plugin->getName().' is complete');
        $mail->setBody('Hello plugin owner,

this is to inform you that publishing of your plugin on the Plugin Portal Update Center was NOT approved. Please read comments from plugin verifiers below, if any.

Plugin: '.$plugin->getName().'
NetBeans version: '.$nbVersion.'
Verification status: NOGO
Comments: '.$comment.'

If your plugin was verified successfully, it should be already available on the Plugin Portal Update Center by now:

http://netbeans-vm.apache.org/pluginportal/data/'.$nbVersion.'/catalog.xml.gz

Thanks for your contribution!
NetBeans development team

P.S.: This is an automatic email. DO NOT REPLY to this email.');
        $transport = new Mail\Transport\Sendmail();
        $transport->send($mail);
        // die(var_dump($mail->getBody()));
    }

    private function _sendGoNotification($verification, $comment) {
        $plugin = $verification->getNbVersionPluginVersion()->getPluginVersion()->getPlugin();
        $nbVersion = $verification->getNbVersionPluginVersion()->getNbVersion()->getVersion();
        $pluginVersion = $verification->getNbVersionPluginVersion()->getPluginVersion()->getVersion();
        $mail = new Mail\Message();
        $mail->addTo($plugin->getAuthor());
        $mail->setFrom('webmaster@netbeans.apache.org', 'NetBeans webmaster');
        $mail->setSubject('Verification of your '.$plugin->getName().' is complete');
        $mail->setBody('Hello plugin owner,

this is to inform you that publishing of your plugin on the Plugin Portal Update Center was approved.

Plugin: '.$plugin->getName().'
NetBeans version: '.$nbVersion.'
Verification status: GO

Your plugin should be already available on the Plugin Portal Update Center by now:

http://netbeans-vm.apache.org/pluginportal/data/'.$nbVersion.'/catalog.xml.gz

Thanks for your contribution!
NetBeans development team

P.S.: This is an automatic email. DO NOT REPLY to this email.');
        $transport = new Mail\Transport\Sendmail();
        $transport->send($mail);
        // die(var_dump($mail->getBody()));
    }
}