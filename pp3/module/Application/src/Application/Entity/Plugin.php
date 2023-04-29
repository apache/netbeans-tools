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

namespace Application\Entity;

use Doctrine\ORM\Mapping as ORM;
use Application\Entity\PluginVersion;
use Application\Pp\MavenDataLoader;

/**
 * @ORM\Entity
 * @ORM\Table(name="plugin")
 */
class Plugin extends Base\Plugin {   
    
    const STATUS_PRIVATE = 1;
    const STATUS_PUBLIC = 2;

    /**
     * @var MavenDataLoader
     */
    private $_dataLoader;

    public function setDataLoader($dl) {
        $this->_dataLoader = $dl;
    }
    
    public function reloadData() {
        $data = $this->_dataLoader->getData($this);
        if ($this->_validateData($data) && $this->artifactid == $data['artifactId']) {
            if (!empty($data['versioning'])) {
                $this->updateVersions($data['versioning']);
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
                $this->updateVersions($versioning);
                if ($this->getReleaseVersion()) {
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
            }
            return true;
        } else {
            return false;
        }
    }

    private function updateVersions($versioning) {
        if (!empty($versioning['latest'])) {
            $this->setLatestVersion($versioning['latest']);
        }
        if (!empty($versioning['release'])) {
            $this->setReleaseVersion($versioning['release']);
        }
        if (!empty($versioning['versions'])) {
            if (!is_array($versioning['versions']['version'])) {
                $versions = array($versioning['versions']['version']);
            } else {
                $versions = $versioning['versions']['version'];
            }
            $incomingVersions = array_flip($versions);
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
                // Fetch SHA1 sums from Maven central to make verified downloads possible
                // SHA1 is currently the best algorithm maven central supports
                $sha1 = file_get_contents($v->getUrl() . ".sha1");
                if ($sha1) {
                    $v->addDigest("SHA-1", $sha1);
                    $this->addVersion($v);
                }
            }
        }
    }

    public function removeAuthor($user) {
        $this->authors->removeElement($user);
    }

    /**
     * @param User $user
     * @return boolean true if author was added, false if it already existed
     */
    public function addAuthor($user) {
        foreach($this->authors as $existingUser) {
            if($user->getId() == $existingUser->getId()) {
                return false;
            }
        }
        $this->authors[] = $user;
        return true;
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

    public function incrementDownloadCounter() {
        $this->downloads++;
    }

    public function isPublic() {
        return $this->status === self::STATUS_PUBLIC;
    }

    public function isOwnedBy($userId) {
        foreach($this->getAuthors() as $author) {
            if($author->getId() == $userId) {
                return true;
            }
        }
        return false;
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
