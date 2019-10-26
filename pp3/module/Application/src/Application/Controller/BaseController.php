<?php


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