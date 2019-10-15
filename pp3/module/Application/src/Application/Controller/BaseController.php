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
    protected $_sessionUserId;
    
    public function __construct($config) {
        $this->_config = $config;
        $this->_session = new Container();
    }
    
    public function onDispatch(MvcEvent $e) {
        if (!$this->isAuthenticated()) {
            return $this->redirect()->toRoute('home', array(
                'action' => 'index'
            ));
        }
        $this->_sessionUserId = $_SESSION['sessionUserId'];
        $this->layout()->setVariable('sessionUserId', $this->_sessionUserId);
        $this->isAdmin();
        return parent::onDispatch($e);
    }

    private function isAuthenticated() {
        return !empty($_SESSION['sessionUserId']);
    }
    
    private function isAdmin() {
        $this->_isAdmin = $_SESSION['isAdmin'];        
        $this->layout()->setVariable('isAdmin', $this->_isAdmin);
    }
}