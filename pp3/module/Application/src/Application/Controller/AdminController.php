<?php

namespace Application\Controller;

use Application\Controller\BaseController;
use Zend\View\Model\ViewModel;
use Application\Entity\Plugin;
use Zend\Session\Container;
use Application\Pp\MavenDataLoader;
use Application\Pp\Catalog;
use Application\Entity\Verifier;
use Application\Entity\NbVersion;
use Application\Entity\Category;
use Zend\Mvc\MvcEvent;
use HTMLPurifier;
use HTMLPurifier_Config;
use Zend\Http\Response;

class AdminController extends BaseController {

    private $_pluginRepository;
    private $_pluginVersionRepository;
    private $_nbVersionPluginVersionRepository;
    private $_verificationRepository;
    private $_verifierRepository;
    private $_verificationRequestRepository;
    private $_nbVersionRepository;
    private $_categoryRepository;

    public function __construct($pluginRepository, $nbVersionPluginVersionRepo, $verificationRepo, 
                                $verifierRepository, $verificationRequestRepository, $nbVersionRepository,
                                $pluginVersionRepository, $config, $categoryRepository) {
        parent::__construct($config);
        $this->_pluginRepository = $pluginRepository;
        $this->_pluginVersionRepository = $pluginVersionRepository;
        $this->_nbVersionPluginVersionRepository = $nbVersionPluginVersionRepo;
        $this->_verificationRepository = $verificationRepo;
        $this->_verifierRepository = $verifierRepository;
        $this->_verificationRequestRepository = $verificationRequestRepository;
        $this->_nbVersionRepository = $nbVersionRepository;
        $this->_categoryRepository = $categoryRepository;
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
                    $catalog = new Catalog($version, $items, $experimental, $this->_config['pp3']['dtdPath'], $this->_getCatalogLink());
                    try {
                        $xml = $catalog->asXml(true);
                        $catalog->storeXml($this->_config['pp3']['catalogSavepath'], $xml);
                        $this->flashMessenger()->setNamespace('success')->addMessage('Catalog for NB '.$version.' published. Found '.count($items).' plugins.');
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

    public function verifiersAction() {
        $this->_checkAdminUser();
        $req = $this->request;   
        $id = $this->params()->fromQuery('id');
        if (!empty($id)) {
            $verifier = $this->_verifierRepository->find($id);
            if ($verifier) {
                $this->_verifierRepository->remove($verifier);$this->flashMessenger()->setNamespace('success')->addMessage('Verifier '.$verifier->getUserId().' deleted.');
                return $this->redirect()->toRoute('admin', array(
                    'action' => 'verifiers'
                ));
            }
        }
        if ($req->isPost() && $this->params()->fromPost('userId')) {
            $verifier = new Verifier();
            $verifier->setUserId($this->params()->fromPost('userId'));
            $this->_verifierRepository->persist($verifier);
            $this->flashMessenger()->setNamespace('success')->addMessage('Verifier '.$verifier->getUserId().' added.');
                return $this->redirect()->toRoute('admin', array(
                    'action' => 'verifiers'
                ));
        }
        return new ViewModel([
            'verifiers' => $this->_verifierRepository->findAll()
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
        ]);;
    }

    private function _createEmptyCatalogForNbVersion($nbVersion) {
        $catalog = new Catalog($nbVersion->getVersion(), array(), false, $this->_config['pp3']['dtdPath'], $this->_getCatalogLink());
        $catalogExp = new Catalog($nbVersion->getVersion(), array(), true, $this->_config['pp3']['dtdPath'], $this->_getCatalogLink());
        try {
            $xml = $catalog->asXml(true);
            $xmlExp = $catalogExp->asXml(true);
            $catalog->storeXml($this->_config['pp3']['catalogSavepath'], $xml);
            $catalogExp->storeXml($this->_config['pp3']['catalogSavepath'], $xmlExp);
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

    private function _getCatalogLink() {
        return $_SERVER["REQUEST_SCHEME"].'://'.$_SERVER["HTTP_HOST"].$this->url()->fromRoute('catalogue', array('action' => 'download')).'?id=';
    }

    private function _checkAdminUser() {
        if (!$this->_isAdmin) {
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
            $validatedData = $this->_validateAndCleanPluginData(
                $this->params()->fromPost('author'),
                $this->params()->fromPost('name'),
                $this->params()->fromPost('license'),
                $this->params()->fromPost('description'),
                $this->params()->fromPost('short_description'),
                $this->params()->fromPost('category')
            );
            if ($validatedData) {
                $plugin->setAuthor($validatedData['author']);
                $plugin->setName($validatedData['name']);
                $plugin->setLicense($validatedData['license']);
                $plugin->setDescription($validatedData['description']);
                $plugin->setShortDescription($validatedData['short_description']);
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
                $this->flashMessenger()->setNamespace('error')->addMessage('Missing required data or Author email not in correct format.');
            }
            return $this->redirect()->toUrl('./edit?id='.$plugin->getId());      
        }

        return new ViewModel(array(
            'plugin' => $plugin,
            'categories' => $this->_categoryRepository->getAllCategoriesSortByName(),
        ));
    }

    private function _validateAndCleanPluginData($author, $name, $license, $description, $shortDescription, $category) {
        if (empty($author) || empty($name) || empty($license) || empty($category) || empty($shortDescription)
            || !filter_var($author, FILTER_VALIDATE_EMAIL)) {
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
            'category' => $category
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
}
