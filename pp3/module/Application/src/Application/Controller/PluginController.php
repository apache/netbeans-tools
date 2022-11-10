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
use Zend\Session\Container;
use Application\Pp\MavenDataLoader;
use Application\Entity\Plugin;
use Zend\Mail;
use HTMLPurifier;
use HTMLPurifier_Config;

define('PLUGIN_SESSION_NAMESPACE', 'pp3_plugin_session');

class PluginController extends AuthenticatedController {

    /**
     * @var \Application\Repository\PluginRepository
     */
    private $_pluginRepository;
    /**
     * @var \Application\Repository\PluginVersionRepository
     */
    private $_pluginVersionRepository;
    /**
     * @var \Application\Repository\CategoryRepository
     */
    private $_categoryRepository;
    /**
     * @var \Application\Repository\NbVersionRepository
     */
    private $_nbVersionRepository;
    /**
     * @var \Application\Repository\UserRepository
     */
    private $_userRepository;

    public function __construct($pluginRepo, $pvRepo, $categRepository, $config, $nbVersionRepository, $userRepository) {
        parent::__construct($config);
        $this->_pluginRepository = $pluginRepo;
        $this->_pluginVersionRepository = $pvRepo;
        $this->_categoryRepository = $categRepository;       
        $this->_nbVersionRepository = $nbVersionRepository;
        $this->_userRepository = $userRepository;
    }

    public function syncAction() {
        $pId = $this->params()->fromQuery('id');
        $plugin = $this->_pluginRepository->find($pId);        
        $plugin->setDataLoader(new MavenDataLoader());
        if (!$plugin->isOwnedBy($this->getAuthenticatedUserId())) {
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

    private function handleImgUpload($validatedData, $imgFolder) {
        if(! $validatedData['image']) {
            return false;
        }
        $tmp_name = $validatedData['image']['tmp_name'];
        // basename() may prevent filesystem traversal attacks;
        // further validation/sanitation of the filename may be appropriate
        $name = $validatedData['image']['name'];
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
                $this->params()->fromPost('name'),
                $this->params()->fromPost('license'),
                $this->params()->fromPost('description'),
                $this->params()->fromPost('short_description'),
                $this->params()->fromPost('category'),
                $this->params()->fromPost('homepage'),
                array_key_exists('image-file', $_FILES) ? $_FILES['image-file'] : false
            );
            if ($validatedData) {
                $user = $this->_userRepository->find($this->getAuthenticatedUserId());
                $plugin->addAuthor($user);
                $plugin->setName($validatedData['name']);
                $plugin->setLicense($validatedData['license']);
                $plugin->setDescription($validatedData['description']);
                $plugin->setShortDescription($validatedData['short_description']);
                $plugin->setHomepage($validatedData['homepage']);
                $plugin->setAddedAt(new \DateTime('now'));
                $plugin->setLastUpdatedAt(new \DateTime('now'));
                // categ
                $plugin->removeCategories();
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

                $imageDir = $this->_config['pp3']['catalogSavepath'] . '/plugins/' . $plugin->getId();

                $oldImage = $plugin->getImage();

                // save image
                $im = $this->handleImgUpload($validatedData, $imageDir);
                if ($im) {
                    $plugin->setImage($im);
                }

                if($this->params()->fromPost('image-file-delete') == 'true') {
                    $plugin->setImage(null);
                }

                if($oldImage && $oldImage != $plugin->getImage()) {
                    unlink($imageDir . '/' . $oldImage);
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
            'imageTypes' => $this->_config['imageTypes'],
            'editMode' => false
        ]);
    }

    public function listAction() {
        $plugins = $this->_pluginRepository->getPluginsByAuthorId($this->getAuthenticatedUserId());
        return new ViewModel(array(
            'plugins' => $plugins,
        ));
    }

    public function editAction() {
        $pId = $this->params()->fromQuery('id');
        $plugin = $this->_pluginRepository->find($pId);        
        if (!$plugin || empty($pId) || !$plugin->isOwnedBy($this->getAuthenticatedUserId())) {
            return $this->redirect()->toRoute('plugin', array(
                'action' => 'list'
            ));
        }
        $req = $this->request;
        if ($req->isPost()) {
            $queryString = "";
            if ($this->params()->fromPost('addUserByEMail')) {
                $queryString='&activeTab=authors';
                $users = $this->_userRepository->findByEmail($this->params()->fromPost('addUserByEMail'));
                if (count($users) > 0) {
                    $added = 0;
                    foreach ($users as $user) {
                        if($plugin->addAuthor($user)) {
                            $added++;
                        }
                    }
                    if ($added > 0) {
                        $this->flashMessenger()->setNamespace('success')->addMessage('Authors updated.');
                        $this->_pluginRepository->persist($plugin);
                    } else {
                        $this->flashMessenger()->setNamespace('warning')->addMessage('Cannot add the same user twice.');
                    }
                } else {
                    $this->flashMessenger()->setNamespace('error')->addMessage('No user found with specified address.');
                }
            } else if ($this->params()->fromPost('removeAuthor')) {
                $queryString='&activeTab=authors';
                $user = $this->_userRepository->find($this->params()->fromPost('removeAuthor'));
                if(! $user) {
                    $this->flashMessenger()->setNamespace('error')->addMessage('No user found with specified id.');
                } else {
                    $plugin->removeAuthor($user);
                    if (count($plugin->getAuthors()) > 0) {
                        $this->_pluginRepository->persist($plugin);
                        $this->flashMessenger()->setNamespace('success')->addMessage('Author was removed.');
                    } else {
                        $this->flashMessenger()->setNamespace('error')->addMessage('Last author can not be removed.');
                    }
                }
            } else {
                $validatedData = $this->_validateAndCleanPluginData(
                        $this->params()->fromPost('name'),
                        $this->params()->fromPost('license'),
                        $this->params()->fromPost('description'),
                        $this->params()->fromPost('short_description'),
                        $this->params()->fromPost('category'),
                        $this->params()->fromPost('homepage'),
                        array_key_exists('image-file', $_FILES) ? $_FILES['image-file'] : false
                );
                if ($validatedData) {
                    $plugin->setName($validatedData['name']);
                    $plugin->setLicense($validatedData['license']);
                    $plugin->setDescription($validatedData['description']);
                    $plugin->setShortDescription($validatedData['short_description']);
                    $plugin->setHomepage($validatedData['homepage']);
                    $plugin->setLastUpdatedAt(new \DateTime('now'));

                    $imageDir = $this->_config['pp3']['catalogSavepath'] . '/plugins/' . $plugin->getId();

                    $oldImage = $plugin->getImage();

                    // save image
                    $im = $this->handleImgUpload($validatedData, $imageDir);
                    if ($im) {
                        $plugin->setImage($im);
                    }

                    if($this->params()->fromPost('image-file-delete') == 'true') {
                        $plugin->setImage(null);
                    }

                    if($oldImage && $oldImage != $plugin->getImage()) {
                        unlink($imageDir . '/' . $oldImage);
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
                            $plugin->addCategory($cat2);
                        }
                    }

                    $this->_pluginRepository->persist($plugin);

                    $this->flashMessenger()->setNamespace('success')->addMessage('Plugin updated.');
                } else {
                    $this->flashMessenger()->setNamespace('error')->addMessage('Missing required data.');
                }
            }
            return $this->redirect()->toUrl('./edit?id='.$plugin->getId() . $queryString);
        }

        $activeTab = $this->params()->fromQuery("activeTab");

        if(!($activeTab == 'settings' || $activeTab == 'authors')) {
            $activeTab = 'settings';
        }

        return new ViewModel(array(
            'plugin' => $plugin,
            'categories' => $this->_categoryRepository->getAllCategoriesSortByName(),
            'activeTab' => $activeTab,
            'imageTypes' => $this->_config['imageTypes'],
            'editMode' => true
        ));
    }

    public function deleteAction() {
        $pId = $this->params()->fromQuery('id');
        $plugin = $this->_pluginRepository->find($pId);    
        if (!$plugin || empty($pId) || !$plugin->isOwnedBy($this->getAuthenticatedUserId())) {
            return $this->redirect()->toRoute('plugin', array(
                'action' => 'list'
            ));
        };        
        $this->flashMessenger()->setNamespace('success')->addMessage('Plugin '.$plugin->getName().' deleted.');
        $this->_pluginRepository->remove($plugin);
        $this->rebuildAllCatalogs();
        return $this->redirect()->toRoute('plugin', array(
            'action' => 'list'
        ));
    }

    private function rebuildAllCatalogs() {
        $versions = $this->_nbVersionRepository->getEntityRepository()->findAll();
        foreach ($versions as $v) {
            $v->requestCatalogRebuild();
            $this->_nbVersionRepository->persist($v);
        }
    }

    private function _validateAndCleanPluginData($name, $license, $description, $shortDescription, $category, $homepage, $imageFileData) {
        $fileType = false;
        if($imageFileData && $imageFileData['size'] > 0) {
            $baseName = basename(strtolower($imageFileData['name']));
            $pathInfo = pathinfo($baseName);
            $imageFileNameType = $pathInfo["extension"];
            foreach($this->_config['imageTypes'] as $imageType) {
                if($imageFileNameType == $imageType) {
                    $fileType = $imageType;
                    break;
                }
            }
        }

        if (empty($name) || empty($license) || empty($category) || empty($shortDescription) || ($imageFileData && $imageFileData['size'] > 0 && (!$fileType))) {
            return false;
        }

        $config = HTMLPurifier_Config::createDefault();
        $purifier = new HTMLPurifier($config);
        return  array(
            'name' => $purifier->purify($name),
            'license' => $purifier->purify($license),
            'description' => $purifier->purify($description),
            'short_description' => $purifier->purify($shortDescription),
            'category' => $category,
            'homepage' => $purifier->purify($homepage),
            'image' => $fileType ? ['tmp_name' => $imageFileData['tmp_name'], 'name' => 'image.' . $fileType] : false
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
        foreach($this->_userRepository->findAdmins() as $verifier) {
            $mail->addBcc($verifier->getEmail());
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
