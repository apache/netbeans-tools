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
use Application\Entity\Plugin;
use Application\Pp\MavenDataLoader;
use Application\Pp\Catalog;
use Application\Entity\User;
use Application\Entity\NbVersion;
use Application\Entity\Category;
use HTMLPurifier;
use HTMLPurifier_Config;
use Zend\Http\PhpEnvironment\Response;
use Zend\Mail;
use Zend\Mail\Header\ContentType;

class AdminController extends AuthenticatedController {

    /**
     * @var \Application\Repository\PluginRepository
     */
    private $_pluginRepository;
    /**
     * @var \Application\Repository\PluginVersionRepository
     */
    private $_pluginVersionRepository;
    /**
     * @var \Application\Repository\NbVersionPluginVersionRepository
     */
    private $_nbVersionPluginVersionRepository;
    /**
     * @var \Application\Repository\VerificationRepository
     */
    private $_verificationRepository;
    /**
     * @var \Application\Repository\VerificationRequestRepository
     */
    private $_verificationRequestRepository;
    /**
     * @var \Application\Repository\UserRepository
     */
    private $_userRepository;
    private $_nbVersionRepository;
    private $_categoryRepository;

    public function __construct($pluginRepository, $nbVersionPluginVersionRepo, $verificationRepo, 
                                $verificationRequestRepository, $nbVersionRepository,
                                $pluginVersionRepository, $config, $categoryRepository,
                                $userRepository) {
        parent::__construct($config);
        $this->_pluginRepository = $pluginRepository;
        $this->_pluginVersionRepository = $pluginVersionRepository;
        $this->_nbVersionPluginVersionRepository = $nbVersionPluginVersionRepo;
        $this->_verificationRepository = $verificationRepo;
        $this->_verificationRequestRepository = $verificationRequestRepository;
        $this->_nbVersionRepository = $nbVersionRepository;
        $this->_categoryRepository = $categoryRepository;
        $this->_userRepository = $userRepository;
    } 

    public function deletePendingAction() {
        $this->_checkAdminUser();
        $pId = $this->params()->fromQuery('id');
        if (!empty($pId)) {
            $plugin = $this->_pluginRepository->find($pId);
            if ($plugin) {
                $this->_pluginRepository->remove($plugin);
                $this->flashMessenger()->setNamespace('success')->addMessage('Plugin '.$plugin->getName().' removed');                
            }        
        }
        return $this->redirect()->toRoute('admin', array(
            'action' => 'approve'
        ));
    }

    public function approveAction() {
        $this->_checkAdminUser();
        $pId = $this->params()->fromQuery('id');
        if (!empty($pId)) {
            $plugin = $this->_pluginRepository->find($pId);
            if ($plugin) {
                $plugin->setStatus(Plugin::STATUS_PUBLIC);
                $plugin->setApprovedAt(new \DateTime('now'));
                $this->_pluginRepository->persist($plugin);
                $this->flashMessenger()->setNamespace('success')->addMessage('Plugin '.$plugin->getName().' approved');
                return $this->redirect()->toRoute('admin', array(
                    'action' => 'approve'
                ));
            }        
        }
        return new ViewModel([
            'plugins' => $this->_pluginRepository->getPluginsByStatus(Plugin::STATUS_PRIVATE),
        ]);
    }

    public function indexAction() {
        $this->_checkAdminUser();
        $plugins = null;
        $search = null;
        $req = $this->request;        
        if ($this->params()->fromQuery('search')) {
            $search = $this->params()->fromQuery('search');
            $plugins = $this->_pluginRepository->getPluginsByName($search);
        }
        $pId = $this->params()->fromQuery('id');
        $act = $this->params()->fromQuery('act');
        $q = $this->params()->fromQuery('q');
        if (!empty($pId)) {
            $plugin = $this->_pluginRepository->find($pId);
            if ($plugin) {
                $plugins = array($plugin);
            }
            if ($act) {
                switch ($act) {
                    case 'publish':
                        $plugin->setStatus(Plugin::STATUS_PUBLIC);
                        $plugin->setApprovedAt(new \DateTime('now'));
                        $this->_pluginRepository->persist($plugin);
                        $this->flashMessenger()->setNamespace('success')->addMessage('Plugin '.$plugin->getName().' approved.');            
                        break;
                    case 'hide':
                        $plugin->setStatus(Plugin::STATUS_PRIVATE);
                        $this->_pluginRepository->persist($plugin);
                        $this->flashMessenger()->setNamespace('success')->addMessage('Plugin '.$plugin->getName().' made hidden.');            
                        break;
                    case 'sync':
                        $plugin->setDataLoader(new MavenDataLoader());
                        try {
                            $plugin->reloadData();
                            $this->_pluginRepository->persist($plugin);            
                            $this->flashMessenger()->setNamespace('success')->addMessage('Plugin data reloaded from maven-metadata.xml file.');            
                        } catch (\Exception $e) {
                            // maven-metadata.xml not found
                            $this->flashMessenger()->setNamespace('error')->addMessage('Sorry, maven-metadata.xml file is missing.');                
                        }
                        break;
                    case 'delete':
                        $this->_pluginRepository->remove($plugin);
                        $this->rebuildAllCatalogs();
                        $this->flashMessenger()->setNamespace('success')->addMessage('Plugin '.$plugin->getName().' deleted.');
                        return $this->redirect()->toRoute('admin', array(
                            'action' => 'index'
                        ));
                        break;
                }
            }
        }
        return new ViewModel([
            'plugins' => $plugins,
            'search' => $search,
        ]);
    }
    
    public function catalogAction() {
        $this->_checkAdminUser();
        $version = $this->params()->fromQuery('version');
        $experimental = $this->params()->fromQuery('experimental');
        if (!empty($version)) {        
            $nbv = $this->_nbVersionRepository->findOneBy('version', $version);
            if ($nbv) {
                $items = $experimental ? $this->_pluginVersionRepository->getNonVerifiedVersionsByNbVersion($version) : $this->_pluginVersionRepository->getVerifiedVersionsByNbVersion($version);
                if (count($items)) {                
                    $catalog = new Catalog(
                            $this->_pluginVersionRepository,
                            $version,
                            $items,
                            $experimental,
                            $this->_config['pp3']['dtdPath'],
                            $this->getDownloadBaseUrl(),
                            $this->_config['pp3']['catalogSavepath']);
                    try {
                        $errors = array();
                        $catalog->storeXml(true, $errors);
                        if(count($errors) == 0) {
                            $this->flashMessenger()->setNamespace('success')->addMessage('Catalog for NB '.$version.' published. Found '.count($items).' plugins.');
                        } else {
                            $message = 'Catalog for NB '.$version.' published. Found '.count($items).' plugins, not all plugins were valid:<ul style="margin-top: 1ex">';
                            foreach($errors as $pluginVersionId => $errorList) {
                                $plugin = $this->_pluginVersionRepository->find($pluginVersionId)->getPlugin();
                                $message .= '<li>';
                                $message .= htmlentities($plugin->getName() . ' (ID: ' . $plugin->getId() . ')', ENT_COMPAT | ENT_HTML401, 'UTF-8');
                                $message .= "<ul>";
                                foreach($errorList as $errorEntry) {
                                    $message .= '<li>';
                                    $message .= htmlentities(sprintf("%s (Code: %d)", $errorEntry->message, $errorEntry->code), ENT_COMPAT | ENT_HTML401, 'UTF-8');
                                    $message .= '</li>';
                                }
                                $message .= "</ul>";
                                $message .= '</li>';
                            }
                            $message .= '</ul>';
                            $this->flashMessenger()->setNamespace('warning')->addMessage($message);
                        }
                    } catch (\Exception $e){
                        $this->flashMessenger()->setNamespace('error')->addMessage($e->getMessage());                        
                    }                
                } else {
                    $this->flashMessenger()->setNamespace('info')->addMessage('No plugins foud for version NB '.$version);
                }
                return $this->redirect()->toRoute('admin', array(
                    'action' => 'catalog'
                ));
            }    
        }

        return new ViewModel([
            'catalUrlPath' => $this->_config['pp3']['catalogUrlPath'],
            'nbVersions' => $this->_nbVersionRepository->findAll(),            
        ]);
    }

    public function searchUserByEmailAction() {
        $this->_checkAdminUser();
        /* @var $response Response */
        $response = $this->getResponse();
        $response->getHeaders()->addHeaderLine("Content-Type", "application/json");
        $email = $this->params()->fromQuery('email');
        $result = array();
        if(! empty($email)) {
            foreach($this->_userRepository->findByEmail($email) as $user) {
                $result[] = array(
                    'id' => $user->getId(),
                    'name' => $user->getName(),
                    'email' => $user->getEmail(),
                    'idpProviderId' => $user->getIdpProviderId(),
                    'verifier' => !!$user->isVerifier(),
                    'admin' => !!$user->isAdmin()
                );
            }
        }
        $response->setContent(json_encode($result));
        return $response;
    }

    public function verifiersAction() {
        $this->_checkAdminUser();
        $removeVerifierStatusId = $this->params()->fromPost('removeVerifierStatusId');
        if (!empty($removeVerifierStatusId)) {
            /* @var $verifier User */
            $verifier = $this->_userRepository->find($removeVerifierStatusId);
            if ($verifier) {
                $verifier->setVerifier(false);
                $this->_userRepository->persist($verifier);
                $this->flashMessenger()->setNamespace('success')->addMessage('Verifier '.$verifier->getName().' removed.');
                return $this->redirect()->toRoute('admin', array(
                    'action' => 'verifiers'
                ));
            }
        }
        $addVerifierStatusId = $this->params()->fromPost('addVerifierStatusId');
        if (!empty($addVerifierStatusId)) {
            /* @var $verifier User */
            $verifier = $this->_userRepository->find($addVerifierStatusId);
            if ($verifier) {
                $verifier->setVerifier(true);
                $this->_userRepository->persist($verifier);
                $this->flashMessenger()->setNamespace('success')->addMessage('Verifier '.$verifier->getName().' added.');
                return $this->redirect()->toRoute('admin', array(
                    'action' => 'verifiers'
                ));
            }
        }
        return new ViewModel([
            'verifiers' => $this->_userRepository->findVerifier()
        ]);
    }

    public function categoriesAction() {
        $this->_checkAdminUser();
        $req = $this->request;   
        $id = $this->params()->fromQuery('id');
        if (!empty($id)) {
            $cat = $this->_categoryRepository->find($id);
            if ($cat) {
                $this->_categoryRepository->remove($cat);
                $this->flashMessenger()->setNamespace('success')->addMessage('Category '.$cat->getName().' deleted.');
                return $this->redirect()->toRoute('admin', array(
                    'action' => 'categories'
                ));
            }
        }
        if ($req->isPost() && $this->params()->fromPost('name')) {
            $cat = new Category();
            $cat->setName($this->params()->fromPost('name'));
            $this->_categoryRepository->persist($cat);
            $this->flashMessenger()->setNamespace('success')->addMessage('Category '.$cat->getName().' added.');
                return $this->redirect()->toRoute('admin', array(
                    'action' => 'categories'
                ));
        }
        return new ViewModel([
            'categories' => $this->_categoryRepository->getAllCategoriesSortByName()
        ]);
    }

    public function nbVersionsAction() {
        $this->_checkAdminUser();
        $req = $this->request;   
        $id = $this->params()->fromQuery('id');
        if (!empty($id)) {
            $nbVersion = $this->_nbVersionRepository->find($id);
            if ($nbVersion) {
                $this->_nbVersionRepository->remove($nbVersion);
                $this->_nbVersionPluginVersionRepository->removeByNbVersionId($id);
                $this->flashMessenger()->setNamespace('success')->addMessage('NetBeans Version '.$nbVersion->getVersion().' deleted.');
                return $this->redirect()->toRoute('admin', array(
                    'action' => 'nbVersions'
                ));
            }
        }
        if ($req->isPost() && $this->params()->fromPost('version')) {
            $nbVersion = new NbVersion();
            $nbVersion->setVersion($this->params()->fromPost('version'));
            $nbVersion->setVerifiable(NbVersion::VERIFIABLE_YES);
            $this->_nbVersionRepository->persist($nbVersion);
            $this->_symlinkLatestVersion($nbVersion);
            $this->_createEmptyCatalogForNbVersion($nbVersion);
            $this->flashMessenger()->setNamespace('success')->addMessage('NetBeans Version '.$nbVersion->getVersion().' added.');
                return $this->redirect()->toRoute('admin', array(
                    'action' => 'nbVersions'
                ));
        }
        return new ViewModel([
            'nbVersions' => $this->_nbVersionRepository->findAll()
        ]);
    }

    public function emailSendingAction() {
        $this->_checkAdminUser();
        $req = $this->request;
        $successMessage = false;

        $onlyVerified = false;
        $nbVersionId = [];
        $subject = '[NetBeans PluginPortal] <SUBJECT>';
        $emailText = '
<html>
<head><title></title></head>
<body>
<p>Dear %1$s,</p>

<p>we have some news for you.</p>

<p>&lt;Your message&gt;</p>

<p>
Your plugins:<br />
%3$s
</p>

<p>Direct link to the plugin portal:<br />
<a href="%2$s">%2$s</a>
</p>

<p>
Best wishes<br />
NetBeans development team
</p>

<p>P.S.: Please contact dev@netbeans.apache.org for questions.</p>
</body>
</html>';

        if ($req->isPost() && ($this->params()->fromPost('sendEmail') || $this->params()->fromPost('sendPreview'))) {
            $successMessage = '';
            $onlyVerified = $this->params()->fromPost('onlyVerified');
            $nbVersionId = $this->params()->fromPost('nbVersionId');
            $subject = $this->params()->fromPost('subject');
            $emailText = $this->params()->fromPost('emailText');

            $users = [];

            $plugins = $this->_pluginRepository
                    ->getPluginsByNetBeansVersion($nbVersionId, $onlyVerified);

            foreach ($plugins as $plugin) {
                foreach ($plugin->getAuthors() as $author) {
                    $email = $author->getEmail();
                    if (!array_key_exists($email, $users)) {
                        $users[$email] = [
                            'email' => $email,
                            'name' => $author->getName(),
                            'plugins' => []
                        ];
                    }
                    if (!in_array($plugin->getName(), $users[$email]['plugins'])) {
                        $users[$email]['plugins'][] = $plugin->getName();
                    }
                }
            }

            $successMessage .= sprintf("Generating E-Mails for %d plugins and %d users<br>\n",
                    count($plugins),
                    count($users));

            if ($this->params()->fromPost('sendPreview')) {
                $successMessage .= "Only sending preview email!<br>\n";

                /**
                 * @var \Application\Entity\User
                 */
                $user = $this->_userRepository->find($this->getAuthenticatedUserId());
                $users = [$user->getEmail() => [
                    'email' => $user->getEmail(),
                    'name' => $user->getName(),
                    'plugins' => ['Demo plugin 1', 'Demo plugin 2']
                ]];
            }

            $transport = new Mail\Transport\Sendmail();

            $emailCount = 0;
            foreach (array_values(($users)) as $entry) {
                $emailCount++;
                $list = "<ul>";
                foreach($entry['plugins'] as $pluginName) {
                    $list .= sprintf("<li>%s</li>", htmlspecialchars($pluginName));
                }
                $list .= "</ul>";

                $mail = new Mail\Message();
                $mail->setFrom('noreply@netbeans.apache.org', 'NetBeans webmaster');
                $mail->setSubject($subject);
                $mail->getHeaders()->addHeader(ContentType::fromString('Content-Type: text/html; charset=utf-8'));
                $mail->setBody(sprintf($emailText,
                                htmlspecialchars($entry['name']),
                                htmlspecialchars($this->getHomeUrl()),
                                $list
                ));
                $mail->addTo($entry['email']);
                $transport->send($mail);
            }

            $successMessage .= "E-Mails were successfully sent: " . $emailCount;
        }
        return new ViewModel([
            'onlyVerified' => $onlyVerified,
            'nbVersionId' => $nbVersionId === null ? [] : $nbVersionId,
            'subject' => $subject,
            'emailText' => $emailText,
            'successMessage' => $successMessage,
            'nbVersions' => $this->_nbVersionRepository->findAll()
        ]);
    }

    private function _createEmptyCatalogForNbVersion($nbVersion) {
        $catalog = new Catalog(
                $this->_pluginVersionRepository,
                $nbVersion->getVersion(),
                array(),
                false,
                $this->_config['pp3']['dtdPath'],
                $this->getDownloadBaseUrl(),
                $this->_config['pp3']['catalogSavepath']);
        $catalogExp = new Catalog(
                $this->_pluginVersionRepository,
                $nbVersion->getVersion(),
                array(),
                true,
                $this->_config['pp3']['dtdPath'],
                $this->getDownloadBaseUrl(),
                $this->_config['pp3']['catalogSavepath']);
        try {
            $errors = [];
            $catalog->storeXml(true, $errors);
            $catalogExp->storeXml(true, $errors);
        } catch (\Exception $e){
            $this->flashMessenger()->setNamespace('error')->addMessage($e->getMessage());                        
        }         
    }

    private function _symlinkLatestVersion($nbVersion) {
        $link = $this->_config['pp3']['catalogSavepath'].'/latest';
        $target = $this->_config['pp3']['catalogSavepath'].'/'.$nbVersion->getVersion();        
        if (is_link($link)) {
            unlink($link);
        }
        symlink($target, $link);
    }

    private function rebuildAllCatalogs() {
        $versions = $this->_nbVersionRepository->getEntityRepository()->findAll();
        foreach ($versions as $v) {
            $version = $v->getVersion();
            $itemsVerified = $this->_pluginVersionRepository->getVerifiedVersionsByNbVersion($version);
            $itemsExperimental = $this->_pluginVersionRepository->getNonVerifiedVersionsByNbVersion($version);
            $catalog = new Catalog(
                    $this->_pluginVersionRepository,
                    $version,
                    $itemsVerified,
                    false,
                    $this->_config['pp3']['dtdPath'],
                    $this->getDownloadBaseUrl(),
                    $this->_config['pp3']['catalogSavepath']);
            try {
                $errors = [];
                $catalog->storeXml(true, $errors);
            } catch (\Exception $e) { }

            $catalog = new Catalog(
                    $this->_pluginVersionRepository,
                    $version,
                    $itemsExperimental,
                    true,
                    $this->_config['pp3']['dtdPath'],
                    $this->getDownloadBaseUrl(),
                    $this->_config['pp3']['catalogSavepath']);
            try {
                $errors = [];
                $catalog->storeXml(true, $errors);
            } catch (\Exception $e) { }                 

        }
    }

    private function _checkAdminUser() {
        if (!$this->isAdmin()) {
            return $this->redirect()->toRoute('plugin', array(
                'action' => 'index'
            ));
        }
    }

    public function editAction() {
        $this->_checkAdminUser();
        $pId = $this->params()->fromQuery('id');
        $plugin = $this->_pluginRepository->find($pId);
        if (!$plugin || empty($pId)) {
            return $this->redirect()->toRoute('admin');
        }
        $req = $this->request;
        if ($req->isPost()) {
            $queryString = "";
            if ($this->params()->fromPost('addUserByEMail')) {
                $queryString = '&activeTab=authors';
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
                $queryString = '&activeTab=authors';
                $user = $this->_userRepository->find($this->params()->fromPost('removeAuthor'));
                if (!$user) {
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

                    // category
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
            return $this->redirect()->toUrl('./edit?id=' . $plugin->getId() . $queryString);
        }

        $activeTab = $this->params()->fromQuery("activeTab");

        if (!($activeTab == 'settings' || $activeTab == 'authors')) {
            $activeTab = 'settings';
        }

        return new ViewModel(array(
            'plugin' => $plugin,
            'categories' => $this->_categoryRepository->getAllCategoriesSortByName(),
            'activeTab' => $activeTab,
            'imageTypes' => $this->_config['imageTypes']
        ));
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

    public function searchAutocompleteAction() {
        $this->_checkAdminUser();        
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
}
