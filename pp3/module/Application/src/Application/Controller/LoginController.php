<?php

namespace Application\Controller;

use Zend\Session\Container;
use Application\Entity\User;
use Zend\View\Model\ViewModel;

class LoginController extends BaseController {

    /**
     * @var \Application\Repository\UserRepository
     */
    private $_userRepository;

    public function __construct($config, $userRepository) {
        parent::__construct($config);
        $this->_session = new Container();
        $this->_userRepository = $userRepository;
    }

    public function indexAction() {
        $providers = array();
        foreach($this->_config['loginConfig'] as $loginConfig) {
            $iconUrl = $loginConfig['icon'];
            if(strpos($iconUrl, '/') === 0) {
                $iconUrl = $this->url()->fromRoute("home", array(), array('force_canonical'=>true)) . substr($iconUrl, 1);
            }
            $loginUrl = $this->url()->fromRoute('login', array('action' => 'login-start'), array('force_canonical'=>true));
            $loginUrl .= '?';
            $loginUrl .= http_build_query(array('id' => $loginConfig['id']));
            $providers[] = array(
                'id' => $loginConfig['id'],
                'name' => $loginConfig['name'],
                'iconUrl' => $iconUrl,
                'loginUrl' => $loginUrl
            );
        }
        return new ViewModel([
            'providers' => $providers
        ]);
    }

    private function findLoginConfig(string $id) {
        if(! $id) {
            return null;
        }
        foreach($this->_config['loginConfig'] as $loginConfig) {
            if($loginConfig['id'] === $id) {
                return $loginConfig;
            }
        }
        return null;
    }

    public function loginStartAction() {
        $loginConfig = $this->findLoginConfig($this->params()->fromQuery('id'));
        if (!$loginConfig) {
            $response = $this->getResponse();
            $response->setStatusCode(404);
            $response->setContent("Unknown authentication provider");
            return $response;
        }

        $stateBytes = random_bytes(64);
        $state = bin2hex($stateBytes);
        $_SESSION['oauthState'] = $state;
        $_SESSION['oauthConfig'] = $loginConfig['id'];
        $scopeData = self::scopesFromType($loginConfig['type']);
        $queryData = array(
            'client_id' => $loginConfig['clientId'],
            'state' => $state,
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUrl()
        );
        if($scopeData) {
            $queryData['scope'] = $scopeData;
        }
        $url = self::authenticationUrlFromType($loginConfig['type']);
        $url .= '?';
        $url .= http_build_query($queryData);
        return $this->redirect()->toUrl($url);
    }

    public function callbackAction() {
        $response = $this->getResponse();
        $response->getHeaders()->addHeaderLine('Content-Type', 'application/json');

        $parameters = $this->params()->fromQuery();
        $state = $this->params()->fromQuery('state');
        $code = $this->params()->fromQuery('code');

        if((!array_key_exists('oauthState', $_SESSION)) || $_SESSION['oauthState'] != $state) {
            error_log('Invalid / no state was transfered - received: ' . json_encode($parameters));
            $response->setStatusCode(400);
            $response->setContent(json_encode(array('success' => false, 'reason' => 'INVALID_STATE')));
            return $response;
        }

        $loginConfig = $this->findLoginConfig($_SESSION['oauthConfig']);

        if (!$loginConfig) {
            error_log("Login Config was not found for: " . $_SESSION['oauthConfig'] . " received: " . json_encode($parameters));
            $response->setStatusCode(400);
            $response->setContent(json_encode(array('success' => false, 'reason' => 'INVALID_LOGIN_CONFIG')));
            return $response;
        }

        $tokenRequest = self::tokenRequest($code, $loginConfig);
        $queryTokenResult = file_get_contents(self::tokenUrlFromType($loginConfig['type']), false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => ["Content-type: application/json", "Accept: application/json"],
                'content' => json_encode($tokenRequest)
            ]
        ]));

        if(!$queryTokenResult) {
            error_log("Empty response");
            $response->setStatusCode(500);
            $response->setContent(json_encode(array('success' => false, 'reason' => 'INVALID_TOKEN')));
            return $response;
        }

        $tokenData = json_decode($queryTokenResult, true);

        if((! $tokenData) || (! $tokenData['access_token']) || (strtolower($tokenData['token_type']) != 'bearer')) {
            error_log("Failed to decode token data: " . $queryTokenResult);
            $response->setStatusCode(500);
            $response->setContent(json_encode(array('success' => false, 'reason' => 'INVALID_TOKEN')));
            return $response;
        }

        $queryProfileResult = file_get_contents(self::profileUrlFromType($loginConfig['type']), false, stream_context_create([
            'http' => [
                'header' => ['Accept: application/json', 'Authorization: Bearer ' . $tokenData['access_token'], 'User-Agent: Netbeans Plugin Portal'],
                "ignore_errors" => true,
            ]
        ]));

        $userinfo = $this->extractUserInfo($loginConfig['type'], $loginConfig['id'], $queryProfileResult);

        if($userinfo == null) {
            error_log("Failed to parse: " . $queryProfileResult);
            $response->setStatusCode(500);
            $response->setContent(json_encode(array('success' => false, 'reason' => 'INVALID_USERINFO')));
            return $response;
        }

        if(!$userinfo['email']) {
            $emailQueryUrl = self::emailQueryUrl($loginConfig['type']);
            if ($emailQueryUrl) {
                $queryEmailResult = file_get_contents($emailQueryUrl, false, stream_context_create([
                    'http' => [
                        'header' => ['Accept: application/json', 'Authorization: Bearer ' . $tokenData['access_token'], 'User-Agent: Netbeans Plugin Portal'],
                        "ignore_errors" => true,
                    ]
                ]));
                $queryEmail = json_decode($queryEmailResult, true);
                foreach($queryEmail as $emailInfo) {
                    if(array_key_exists('email', $emailInfo) && $emailInfo['email']) {
                        $userinfo['email'] = $emailInfo['email'];
                        break;
                    }
                }
            }

            if (!$userinfo['email']) {
                error_log("Userinfo did not contain email");
                $response->setStatusCode(500);
                $response->setContent(json_encode(array('success' => false, 'reason' => 'NO_EMAIL')));
                return $response;
            }
        }

        $user = $this->_userRepository->findByIdpData($userinfo['providerId'], $userinfo['id']);
        if($user == null) {
            $user = new User();
        }

        $user->setEmail($userinfo['email']);
        $user->setIdpProviderId($userinfo['providerId']);
        $user->setIdpUserId($userinfo['id']);
        $user->setName($userinfo['name']);
        $this->_userRepository->persist($user);

        $_SESSION['sessionUserId'] = $user->getId();
        $_SESSION['sessionUserEmail'] = $user->getEmail();
        $_SESSION['sessionIdpProviderId'] = $user->getIdpProviderId();
        $_SESSION['sessionUserName'] = $user->getName();
        $_SESSION['isVerifier'] = $user->isVerifier();
        $_SESSION['isAdmin'] = $user->isAdmin();

        return $this->redirect()->toRoute("home");
    }

    private function redirectUrl() {
        return $this->url()->fromRoute("login", array("action" => "callback"), array('force_canonical'=>true));
    }

    public function logoutAction() {
        $_SESSION['sessionUserId'] = null;
        $_SESSION['sessionUserEmail'] = null;
        $_SESSION['sessionIdpProviderId'] = null;
        $_SESSION['sessionUserName'] = null;
        $_SESSION['isVerifier'] = null;
        $_SESSION['isAdmin'] = null;
        $response = $this->getResponse();
        return $this->redirect()->toRoute("home");
    }

    private static function authenticationUrlFromType($type) {
        switch ($type) {
            case 'github': return 'https://github.com/login/oauth/authorize';
            case 'google': return 'https://accounts.google.com/o/oauth2/v2/auth';
            case 'amazon': return 'https://www.amazon.com/ap/oa';
        }
    }

    private static function tokenUrlFromType($type) {
        switch ($type) {
            case 'github': return 'https://github.com/login/oauth/access_token';
            case 'google': return 'https://oauth2.googleapis.com/token';
            case 'amazon': return 'https://api.amazon.com/auth/o2/token';
        }
    }

    private static function profileUrlFromType($type) {
        switch ($type) {
            case 'github': return 'https://api.github.com/user';
            case 'google': return 'https://openidconnect.googleapis.com/v1/userinfo';
            case 'amazon': return 'https://api.amazon.com/user/profile';
        }
    }

    private static function emailQueryUrl($type) {
        switch($type) {
            case 'github': return 'https://api.github.com/user/emails';
            default: return false;
        }
    }

    private static function scopesFromType($type) {
        switch ($type) {
            case 'github': return 'user:email';
            case 'google': return 'openid email profile';
            case 'amazon': return 'profile';
        }
    }

    private function tokenRequest( $code, $loginConfig) {
        $data = array('code' => $code, 'client_id' => $loginConfig['clientId'], 'client_secret' => $loginConfig['clientSecret']);
        if($loginConfig['type'] != 'github') {
            $data['redirect_uri'] = $this->redirectUrl();
            $data['grant_type'] = 'authorization_code';
        }
        return $data;
    }

    private function extractUserInfo($type, $providerId, $data) {
        if(! $data) {
            return null;
        }
        $json = json_decode($data, true);
        if(! $json) {
            return null;
        }
        $userinfo = array();
        $userinfo['providerId'] = $providerId;
        if($type == 'github') {
            $userinfo['id'] = "" . $json['id'];
            $userinfo['email'] = "" . $json['email'];
            $userinfo['name'] = "" . $json['name'];
        }  else if ($type == 'google') {
            $userinfo['id'] = "" . $json['sub'];
            $userinfo['email'] = "" . $json['email'];
            $userinfo['name'] = "" . $json['name'];
        } else if ($type == 'amazon') {
            $userinfo['id'] = "" . $json['user_id'];
            $userinfo['email'] = "" . $json['email'];
            $userinfo['name'] = "" . $json['name'];
        }
        return $userinfo;
    }
}
