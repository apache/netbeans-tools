<?php

namespace Application\Entity;

use Doctrine\ORM\Mapping as ORM;
use Application\Entity\PluginVersion;

/**
 * @ORM\Entity
 * @ORM\Table(name="plugin")
 */
class Plugin extends Base\Plugin {   
    
    const STATUS_PRIVATE = 1;
    const STATUS_PUBLIC = 2;

    private $_dataLoader;
    public $tmpVersions;
    
    public function setDataLoader($dl) {
        $this->_dataLoader = $dl;
    }
    
    public function reloadData() {
        $data = $this->_dataLoader->getData($this);
        if ($this->_validateData($data) && $this->artifactid == $data['artifactId']) {
            if (!empty($data['versioning'])) {
                $versioning = $data['versioning'];
                if (!empty($versioning['latest'])) {
                    $this->setLatestVersion($versioning['latest']);
                }
                if (!empty($versioning['release'])) {
                    $this->setReleaseVersion($versioning['release']);
                }
                if (!empty($versioning['versions']['version'])) {
                    $incomingVersions = array_flip($versioning['versions']['version']);
                    // check for new versions only               
                    foreach ($this->versions as $registeredVersion) {
                        unset($incomingVersions[$registeredVersion->getVersion()]);
                    }
                    $incomingVersions = array_flip($incomingVersions);
                    foreach ($incomingVersions as $vers) {
                        $v = new PluginVersion();
                        $v->setVersion($vers);
                        $v->setPlugin($this);
                        $v->setupUrl();
                        $this->addVersion($v);
                    }
                }
            }
            return true;
        }
    }

    public function loadData() {
        $data = $this->_dataLoader->getData($this);
        if ($this->_validateData($data)) {
            $this->setArtifactId($data['artifactId']);
            $this->setGroupId($data['groupId']);
            if (!empty($data['versioning'])) {
                $versioning = $data['versioning'];
                if (!empty($versioning['latest'])) {
                    $this->setLatestVersion($versioning['latest']);
                }
                if (!empty($versioning['release'])) {
                    $this->setReleaseVersion($versioning['release']);
                    // load additional info from release
                    $releaseData = $this->_dataLoader->getReleaseData($this);
                    if(!empty($releaseData['name'])) {
                        $this->setName($releaseData['name']);
                    }
                    if(!empty($releaseData['description'])) {
                        $desc = preg_replace('/\s\s+/', ' ', trim($releaseData['description']));
                        $this->setShortDescription($desc);
                        $this->setDescription($desc);
                    }
                    if(!empty($releaseData['url'])) {
                        $this->setHomepage($releaseData['url']);
                    }
                    if(!empty($releaseData['licenses']) && !empty($releaseData['licenses']['license'])) {
                        $this->setLicense($releaseData['licenses']['license']['name']);
                    }
                }
                if (!empty($versioning['versions'])) {
                    // handle some issues with serialization of xml into array
                    if (!is_array($versioning['versions']['version'])) {
                        $this->tmpVersions = array($versioning['versions']['version']);     
                    } else {
                        $this->tmpVersions = $versioning['versions']['version'];                    
                    }
                }
            }
            return true;
        } else {
            return false;
        }
    }

    public function addVersion($version) {
        $this->versions[] = $version;
    }

    public function addCategory($category) {
        $this->categories[] = $category;
    }

    public function removeCategories() {
        $this->categories = [];
    }

    private function _validateData($data) {
        if (
            empty($data['artifactId']) || empty($data['groupId'])
        ) {
            return false;
        }
        return true;
    }

    public function getStatusIconClass() {
        if ($this->status === self::STATUS_PRIVATE) {
            return 'fa-eye-slash color-red';
        } elseif ($this->status === self::STATUS_PUBLIC) {
            return 'fa-eye color-green';
        }
    }

    public function getStatusTitle() {
        if ($this->status === self::STATUS_PRIVATE) {
            return 'Waiting for approval';
        } elseif ($this->status === self::STATUS_PUBLIC) {
            return 'Approved and published on '.($this->getApprovedAt() ? $this->getApprovedAt()->format('Y-m-d') : 'N/A');
        }
    }

    public function getAuthorName() {
        $split = explode('@', $this->getAuthor());
        return $split[0];
    }

    public function incrementDownloadCounter() {
        $this->downloads++;
    }

    public function isPublic() {
        return $this->status === self::STATUS_PUBLIC;
    }

    public function isOwnedBy($userId) {
        return $this->author == $userId;
    }

    public function setUrl($url) {
        $this->url = $this->sanitizePluginUrl($url);
    }

    private function sanitizePluginUrl($url) {
        if (!preg_match('/.*maven-metadata\.xml$/i', $url, $match)) {
            return trim($url,'/').'/maven-metadata.xml';
        }
        return $url;
    }

}
