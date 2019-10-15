<?php


namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Session\Container;

class LoginController extends AbstractActionController {

    private $_config;
    private $_session;
    private $_verifierRepository;

    public function __construct($config, $verifierRepository) {
        $this->_config = $config;
        $this->_session = new Container();
        $this->_verifierRepository = $verifierRepository;
    }
    
    public function onGoogleSignInAjaxAction() {
        $response = $this->getResponse();
        // user info passed from Google auth
        $req = $this->request;        
        if($_SESSION['sessionUserId']) {
            $response->setContent('');
        } else {
            if ($req->isPost()) {
                $name = $this->params()->fromPost('name');
                $email= $this->params()->fromPost('email');
                $_SESSION['sessionUserId'] = $email;
                $_SESSION['sessionUserEmail'] = $email;        
                $this->checkVerifier($email);
                $this->checkAdmin($email);
                $response->setContent('reload');
            }
        }
       return $response;
    }

    public function onGoogleSignOutAjaxAction() {
        $_SESSION['sessionUserId'] = null;
        $_SESSION['sessionUserEmail'] = null;
        $_SESSION['isVerifier'] = null;
        $_SESSION['isAdmin'] = null;
        die();
    }

    private function checkVerifier($userId) {
        if (!empty($userId)) {
            $verifier = $this->_verifierRepository->findOneBy('user_id', $userId);
            if ($verifier) {
                $_SESSION['isVerifier'] = true;
            }
        }
    }

    private function checkAdmin($userId) {
        $admins = $this->_config['pp3']['admin'];
        $_SESSION['isAdmin'] = in_array($userId, $admins);
    }
}
