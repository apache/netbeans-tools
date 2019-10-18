<?php


namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Session\Container;
// use \Google\Apiclient\Client;

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
                $client = new \Google_Client(['client_id' => $this->_config['pp3']['googleClientId']]);  // Specify the CLIENT_ID of the app that accesses the backend
                $idToken = $this->params()->fromPost('idtoken');
                $payload = $client->verifyIdToken($idToken);
                if ($payload) {
                    $email = $payload['email'];
                    $_SESSION['sessionUserId'] = $email;
                    $_SESSION['sessionUserEmail'] = $email;        
                    $this->checkVerifier($email);
                    $this->checkAdmin($email);
                    $response->setContent('reload');
                } else {
                    // Invalid ID token
                    $response->setContent('Failure');
                }
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
