<?php

namespace Application\Controller;

use Zend\Mvc\MvcEvent;

class AuthenticatedController extends BaseController {

    function __construct($config) {
        parent::__construct($config);
    }


    public function onDispatch(MvcEvent $e) {
        if (!$this->isAuthenticated()) {
            return $this->redirect()->toRoute('home', array(
                        'action' => 'index'
            ));
        }
        return parent::onDispatch($e);
    }
}
