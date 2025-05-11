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

namespace Application\Entity\Base;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

class Plugin {

    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     */
    protected $id;

    /** @ORM\Column(type="string", length=255) */
    protected $name;

    /** @ORM\Column(type="string", length=255) */
    protected $artifactid;

    /** @ORM\Column(type="string", length=255) */
    protected $groupid;

    /** @ORM\Column(type="text") */
    protected $description;

    /** @ORM\Column(type="text") */
    protected $short_description;

    /** @ORM\Column(type="string", length=255) */
    protected $license;

    /**
     * @ORM\ManyToMany(targetEntity="User")
     * @ORM\JoinTable(name="plugin_user")
     */
    protected $authors;

    /** @ORM\Column(type="datetime") */
    protected $added_at;

    /** @ORM\Column(type="datetime") */
    protected $last_updated_at;

    /** @ORM\Column(type="datetime") */
    protected $approved_at;

    /** @ORM\Column(type="string", length=255) */
    protected $url;

    /** @ORM\Column(type="integer") */
    protected $status;

    /** @ORM\Column(type="string", length=255) */
    protected $latest_version;

    /** @ORM\Column(type="string", length=255) */
    protected $release_version;

    /** @ORM\Column(type="string", length=255) */
    protected $image;

    /** @ORM\Column(type="string", length=255) */
    protected $homepage;

    /** @ORM\Column(type="integer") */
    protected $downloads;

    /**
     * @ORM\OneToMany(targetEntity="PluginVersion", mappedBy="plugin", cascade={"persist", "remove"})
     */
    protected $versions;

    /**
    * @ORM\ManyToMany(targetEntity="Category", inversedBy="plugins")
    * @ORM\JoinTable(name="plugin_category")
    */
    protected $categories;

    public function __construct() {
        $this->versions = new ArrayCollection();
        $this->categories = new ArrayCollection();
        $this->authors = new ArrayCollection();
        return $this;
    }

    public function getLatestVersion() {
        return $this->latest_version;
    }

    public function setLatestVersion($ltv) {
        $this->latest_version = $ltv;
    }

    public function getReleaseVersion() {
        return $this->release_version;
    }

    public function setReleaseVersion($rv) {
        $this->release_version = $rv;
    }

    public function getId() {
        return $this->id;
    }

    public function setId($id) {
        $this->id = $id;
    }

    public function getName() {
        return $this->name;
    }

    public function setName($name) {
        $this->name = $name;
    }

    public function getDescription() {
        return $this->description;
    }

    public function setDescription($description) {
        $this->description = $description;
    }

    public function getShortDescription() {
        return $this->short_description;
    }

    public function setShortDescription($description) {
        $this->short_description = $description;
    }

    public function getLicense() {
        return $this->license;
    }

    public function setLicense($license) {
        $this->license = $license;
    }

    /**
     * @return User[]
     */
    public function getAuthors() {
        return $this->authors;
    }

    public function getAddedAt() {
        return $this->added_at;
    }

    public function setAddedAt($added_at) {
        $this->added_at = $added_at;
    }

    public function getLastUpdatedAt() {
        return $this->last_updated_at;
    }

    public function setLastUpdatedAt($lupat) {
        $this->last_updated_at = $lupat;
    }

    public function getUrl() {
        return $this->url;
    }

    public function setUrl($url) {
        $this->url = $url;
    }

    public function getStatus() {
        return $this->status;
    }

    public function setStatus($status) {
        $this->status = $status;
    }

    public function getArtifactId() {
        return $this->artifactid;
    }

    public function setArtifactId($aid) {
        $this->artifactid = $aid;
    }

    public function getGroupId() {
        return $this->groupid;
    }

    public function setGroupId($gid) {
        $this->groupid = $gid;
    }

    /**
     * @return PluginVersion[]
     */
    public function getVersions() {
        return $this->versions;
    }

    public function getCategories() {
        return $this->categories;
    }

    public function setImage($im) {
        $this->image = $im;
    }

    public function getImage() {
        return $this->image;
    }

    public function getHomepage() {
        return $this->homepage;
    }

    public function setHomepage($hp) {
        if (!empty($hp) && !preg_match('/^http:\/\/|^https:\/\//i', $hp)) {
            $hp = 'http://'.$hp;
        }
        $this->homepage = $hp;
    }

    public function getDownloads() {
        return $this->downloads;
    }

    public function setDownloads($dl) {
        $this->downloads = $dl;
    }

    public function getApprovedAt() {
        return $this->approved_at;
    }

    public function setApprovedAt($approved_at) {
        $this->approved_at = $approved_at;
    }

}
