<?php

namespace Application\Controller;

use Application\Controller\BaseController;
use Zend\View\Model\ViewModel;
use Zend\Session\Container;
use Application\Pp\MavenDataLoader;
use Application\Entity\Plugin;
use Application\Entity\PluginVersion;
use Zend\Mail;
use HTMLPurifier;
use HTMLPurifier_Config;

define('PLUGIN_SESSION_NAMESPACE', 'pp3_plugin_session');

class PluginController extends BaseController {

    private $_pluginRepository;
    private $_pluginVersionRepository;
    private $_categoryRepository;
    private $_verifierRepository;

    public function __construct($pluginRepo, $pvRepo, $categRepository, $config, $verifierRepository) {
        parent::__construct($config);
        $this->_pluginRepository = $pluginRepo;
        $this->_pluginVersionRepository = $pvRepo;
        $this->_categoryRepository = $categRepository;       
        $this->_verifierRepository = $verifierRepository;
    }

    public function syncAction() {
        $pId = $this->params()->fromQuery('id');
        $plugin = $this->_pluginRepository->find($pId);        
        $plugin->setDataLoader(new MavenDataLoader());
        if (!$plugin->isOwnedBy($this->_sessionUserId)) {
            return $this->redirect()->toRoute('plugin', array(
                'action' => 'list'
            ));
        }
        try {
            $plugin->reloadData();
            $this->_pluginRepository->persist($plugin);            
            $this->flashMessenger()->setNamespace('success')->addMessage('Plugin data reloaded from maven-metadata.xml file.');            
        } catch (\Exception $e) {
            // maven-metadata.xml not found
            $this->flashMessenger()->setNamespace('error')->addMessage('Sorry, maven-metadata.xml file is missing.');                
        }
        return $this->redirect()->toRoute('plugin', array(
            'action' => 'list'
        ));        
    }

    private function handleImgUpload($imgFolder) {
        $tmp_name = $_FILES["image-file"]["tmp_name"];
        // basename() may prevent filesystem traversal attacks;
        // further validation/sanitation of the filename may be appropriate
        $name = basename($_FILES["image-file"]["name"]);
        if(!file_exists($imgFolder)) {
            mkdir($imgFolder, 0777, true);
        }
        if(move_uploaded_file($tmp_name, $imgFolder.'/'.$name)) {
            return $name; 
        }        
    }

    public function indexAction() {
        $req = $this->request;
        $plugin = new Plugin();
        if ($req->isPost()) {
            $config = HTMLPurifier_Config::createDefault();
            $purifier = new HTMLPurifier($config);
            $groupId = $purifier->purify($this->params()->fromPost('groupid'));
            $artifactId = $purifier->purify($this->params()->fromPost('artifactid'));
            
            if (!empty($groupId) && !empty($artifactId)) {
                $url = $this->_config['pp3']['mavenRepoUrl'].str_replace('.','/', $groupId).'/'.$artifactId.'/maven-metadata.xml';
                $plugin->setUrl($url);
                $plugin->setArtifactId($artifactId);
                $plugin->setGroupId($groupId);
                $plugin->setStatus(Plugin::STATUS_PRIVATE);
                $plugin->setAuthor($this->_sessionUserId);
                $plugin->setDataLoader(new MavenDataLoader());
                try {
                    if ($plugin->loadData()) {
                        $existingPlugins = $this->_pluginRepository->getPluginsByArtifact($plugin->getArtifactId(), $plugin->getGroupId());
                        if (count($existingPlugins) == 0) {
                            // ok, proceed to confirmation
                            $session = new Container(PLUGIN_SESSION_NAMESPACE);
                            $session->plugin_registered = $plugin;
                            return $this->redirect()->toRoute('plugin', array(
                                'action' => 'confirm'
                            ));
                        } else {
                            $this->flashMessenger()->setNamespace('error')->addMessage('Same plugin already registered.');
                        }
                    } else {
                        // empty data from maven-metadata.xml
                        $this->flashMessenger()->setNamespace('error')->addMessage('maven-metadata.xml file is missing data.');
                    }
                } catch (\Exception $e) {
                    // maven-metadata.xml not found
                    $this->flashMessenger()->setNamespace('error')->addMessage($e->getMessage());                
                }
            } else {
                $this->flashMessenger()->setNamespace('error')->addMessage('Missing groupId or atrifactId');                
            }
        }
        return new ViewModel([
            'plugin' => $plugin
        ]);
    }

    public function confirmAction() {
        $session = new Container(PLUGIN_SESSION_NAMESPACE);
        $plugin = $session->plugin_registered; 
        if (!$plugin) {            
            return $this->redirect()->toRoute('plugin', array(
                'action' => 'index'
            ));
        }
        $req = $this->request;
        if ($req->isPost()) {
            $validatedData = $this->_validateAndCleanPluginData(
                $this->params()->fromPost('author'),
                $this->params()->fromPost('name'),
                $this->params()->fromPost('license'),
                $this->params()->fromPost('description'),
                $this->params()->fromPost('short_description'),
                $this->params()->fromPost('category'),
                $this->params()->fromPost('homepage')
            );
            if ($validatedData) {
                $plugin->setAuthor($validatedData['author']);
                $plugin->setName($validatedData['name']);
                $plugin->setLicense($validatedData['license']);
                $plugin->setDescription($validatedData['description']);
                $plugin->setShortDescription($validatedData['short_description']);
                $plugin->setHomepage($validatedData['homepage']);
                $plugin->setAddedAt(new \DateTime('now'));
                $plugin->setLastUpdatedAt(new \DateTime('now'));
                // save also versions
                if (!empty($plugin->tmpVersions)) {
                    foreach($plugin->tmpVersions as $vers) {
                        try {
                            $v = new PluginVersion();
                            $v->setVersion($vers);
                            $v->setPlugin($plugin);
                            $v->setupUrl();
                            $plugin->addVersion($v);
                        } catch (\Exception $e) {
                            $this->flashMessenger()->setNamespace('error')->addMessage($e->getMessage());                
                        }
                    }
                }
                // save image
                $im = $this->handleImgUpload($this->_config['pp3']['catalogSavepath'].'/plugins/'.$plugin->getId());
                if ($im) {                    
                    $plugin->setImage($im);
                }

                // categ
                $plugin->removeCategories();
                $this->_pluginRepository->persist($plugin);
                $cat = $this->_categoryRepository->find($validatedData['category']);
                if ($cat) {
                    $plugin->addCategory($cat);
                }
                $cat2 = $this->params()->fromPost('category2');
                if ($cat2 && (!$cat || ($cat2 != $cat->getId()))) {
                    $cat2 = $this->_categoryRepository->find($this->params()->fromPost('category2'));
                    if ($cat2) {
                        //die(var_dump($cat2, $plugin->getCategories()[1], $validatedData['category']));
                        $plugin->addCategory($cat2);
                    }
                }

                $this->_pluginRepository->persist($plugin);
                $this->flashMessenger()->setNamespace('success')->addMessage('Plugin registered.');
                $session->getManager()->getStorage()->clear(PLUGIN_SESSION_NAMESPACE);

                // send mail to admins asking for approval
                $this->_sendApprovalEmail($plugin);

                return $this->redirect()->toRoute('plugin', array(
                    'action' => 'list'
                ));
            } else {
                $this->flashMessenger()->setNamespace('error')->addMessage('Missing required data.');
            }            
        }
        return new ViewModel([
            'plugin' => $plugin,
            'categories' => $this->_categoryRepository->getAllCategoriesSortByName(),
        ]);
    }

    public function listAction() {
        $plugins = $this->_pluginRepository->getPluginsByAuthor($this->_sessionUserId);
        return new ViewModel(array(
            'plugins' => $plugins,
        ));
    }

    public function editAction() {
        $pId = $this->params()->fromQuery('id');
        $plugin = $this->_pluginRepository->find($pId);        
        if (!$plugin || empty($pId) || !$plugin->isOwnedBy($this->_sessionUserId)) {
            return $this->redirect()->toRoute('plugin', array(
                'action' => 'list'
            ));
        }
        $req = $this->request;
        if ($req->isPost()) {
            $validatedData = $this->_validateAndCleanPluginData(
                $this->params()->fromPost('author'),
                $this->params()->fromPost('name'),
                $this->params()->fromPost('license'),
                $this->params()->fromPost('description'),
                $this->params()->fromPost('short_description'),
                $this->params()->fromPost('category'),
                $this->params()->fromPost('homepage')
            );
            if ($validatedData) {
                $plugin->setAuthor($validatedData['author']);
                $plugin->setName($validatedData['name']);
                $plugin->setLicense($validatedData['license']);
                $plugin->setDescription($validatedData['description']);
                $plugin->setShortDescription($validatedData['short_description']);
                $plugin->setHomepage($validatedData['homepage']);
                $plugin->setLastUpdatedAt(new \DateTime('now'));                
                
                // save image
                $im = $this->handleImgUpload($this->_config['pp3']['catalogSavepath'].'/plugins/'.$plugin->getId());
                if ($im) {                    
                    $plugin->setImage($im);
                }

                // categ
                $plugin->removeCategories();
                $this->_pluginRepository->persist($plugin);
                $cat = $this->_categoryRepository->find($validatedData['category']);
                if ($cat) {
                    $plugin->addCategory($cat);
                }
                $cat2 = $this->params()->fromPost('category2');
                if ($cat2 && (!$cat || ($cat2 != $cat->getId()))) {
                    $cat2 = $this->_categoryRepository->find($this->params()->fromPost('category2'));
                    if ($cat2) {
                        //die(var_dump($cat2, $plugin->getCategories()[1], $validatedData['category']));
                        $plugin->addCategory($cat2);
                    }
                }

                $this->_pluginRepository->persist($plugin);

                $this->flashMessenger()->setNamespace('success')->addMessage('Plugin updated.');               
            } else {
                $this->flashMessenger()->setNamespace('error')->addMessage('Missing required data.');
            }
            return $this->redirect()->toUrl('./edit?id='.$plugin->getId());      
        }

        return new ViewModel(array(
            'plugin' => $plugin,
            'categories' => $this->_categoryRepository->getAllCategoriesSortByName(),
        ));
    }

    public function deleteAction() {
        $pId = $this->params()->fromQuery('id');
        $plugin = $this->_pluginRepository->find($pId);    
        if (!$plugin || empty($pId) || !$plugin->isOwnedBy($this->_sessionUserId)) {
            return $this->redirect()->toRoute('plugin', array(
                'action' => 'list'
            ));
        };        
        $this->flashMessenger()->setNamespace('success')->addMessage('Plugin '.$plugin->getName().' deleted.');
        $this->_pluginRepository->remove($plugin);
        return $this->redirect()->toRoute('plugin', array(
            'action' => 'list'
        ));
    }

    private function _validateAndCleanPluginData($author, $name, $license, $description, $shortDescription, $category, $homepage) {
        if (empty($author) || empty($name) || empty($license) || empty($category) || empty($shortDescription)) {
            return false;
        }
        $config = HTMLPurifier_Config::createDefault();
        $purifier = new HTMLPurifier($config);
        return  array(
            'author' => $purifier->purify($author),
            'name' => $purifier->purify($name),
            'license' => $purifier->purify($license),
            'description' => $purifier->purify($description),
            'short_description' => $purifier->purify($shortDescription),
            'category' => $category,
            'homepage' => $purifier->purify($homepage),
        );
    }

    private function _sendApprovalEmail($plugin) {
        $mail = new Mail\Message();
        $link = $_SERVER["REQUEST_SCHEME"].'://'.$_SERVER["HTTP_HOST"].$this->url()->fromRoute('admin', array('action' => 'approve'));
        $mail->setBody('Hello administrator,
somebody published a new Apache NetBeans plugin in the NetBeans Plugin Portal and it is now waiting for approval to become visible for public.

New plugin: '.$plugin->getName().'

It would be great, if you could login to the NetBeans Plugin Portal at your earliest convenience, make sure the plugin can show up in the list of plugins and then approve it.

'.$link.'

Thanks for your help!
NetBeans development team

P.S.: This is an automatic email. DO NOT REPLY to this email. ');

        $mail->setFrom('webmaster@netbeans.apache.org', 'NetBeans webmaster');
        $mail->setSubject('NetBeans plugin waiting for approval: '.$plugin->getName());
        $transport = new Mail\Transport\Sendmail();
        $i=0;
        foreach($this->_verifierRepository->findAll() as $verifier) {
            if ($i == 0) {
                $mail->addTo($verifier->getUserId());
                $i++;
            } else {
                $mail->addBcc($verifier->getUserId());
            }
        }
        $transport->send($mail);
    }


    public function searchAutocompleteAction() {       
        $term = $this->params()->fromQuery('term');
        $a = array();
        $response = $this->getResponse();
        if (!empty($term)) {
            $plugins = $this->_pluginRepository->getPluginsByName($term);
            if (!empty($plugins)) {
                foreach ($plugins as $p) {
                    $a[] = array('label' => $p->getName(), 'value' => $p->getName());
                }
                $response->setContent(json_encode($a));
            }
        }
        return $response;
    }

}
