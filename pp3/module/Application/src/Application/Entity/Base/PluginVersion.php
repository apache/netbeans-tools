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
use Application\Entity\Plugin;
use Application\Entity\PluginVersionDigest;
use Application\Entity\NbVersionPluginVersion;
use Doctrine\Common\Collections\ArrayCollection;

class PluginVersion {

    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     */
    protected $id;

    /** @ORM\Column(type="string", length=255) */
    protected $version;
    
    /** @ORM\Column(type="string", length=255) */
    protected $url;
    
    /** @ORM\Column(type="text") */
    protected $relnotes;
    
    /** @ORM\Column(type="integer") */
    protected $plugin_id;

    /** @ORM\Column(type="blob") */
    protected $info_xml;

    /** @ORM\Column(type="integer") */
    protected $artifact_size;

    /**
     *  @ORM\OneToMany(targetEntity="NbVersionPluginVersion", mappedBy="pluginVersion", cascade={"persist", "remove"})     
     */
    protected $nbVersionsPluginVersions;
    
    /**
     * @ORM\ManyToOne(targetEntity="Plugin", inversedBy="versions")
     * @ORM\JoinColumn(name="plugin_id", referencedColumnName="id")
     */
    protected $plugin;

    /**
     *  @ORM\OneToMany(targetEntity="PluginVersionDigest", mappedBy="pluginVersion", cascade={"persist", "remove"})
     */
    protected $digests;

    public function __construct() {
        $this->nbVersionsPluginVersions = new ArrayCollection();
        $this->digests = new ArrayCollection();
        return $this;
    }

    public function getId() {
        return $this->id;
    }
    
    public function setId($id) {
        $this->id = $id;
    }
    
    public function setVersion($v) {
        $this->version = $v;
    }

    public function getVersion() {
        return $this->version;
    }
    
    public function getUrl() {
        return $this->url;
    }

    public function setUrl($url) {
        $this->url = $url;
    }
    
    public function getRelnotes() {
        return $this->relnotes;
    }

    public function setRelnotes($relnotes) {
        $this->relnotes = $relnotes;
    }
    
    public function getPluginId() {
        return $this->plugin_id;
    }

    public function setPluginId($pluginId) {
        $this->plugin_id = $pluginId;
    }
    
    public function getPlugin() {
        return $this->plugin;
    }

    public function setPlugin($plugin) {
        $this->plugin = $plugin;
    }
    
    public function getNbVersionsPluginVersions() {
        return $this->nbVersionsPluginVersions;
    }

    public function getDigests() {
        return $this->digests;
    }

    /**
     * @return resource
     */
    function getInfoXml() {
        return $this->info_xml;
    }

    /**
     * @param resource $info_xml
     * @return void
     */
    function setInfoXml($info_xml) {
        $this->info_xml = $info_xml;
    }

    function getArtifactSize() {
        return $this->artifact_size;
    }

    function setArtifactSize($artifact_size): void {
        $this->artifact_size = $artifact_size;
    }
}
