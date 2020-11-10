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
use Application\Entity\NbVersionPluginVersion;
use Doctrine\Common\Collections\ArrayCollection;

class NbVersion {

    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     */
    protected $id;

    /** @ORM\Column(type="string", length=255) */
    protected $version;

    /** @ORM\Column(type="integer") */
    protected $verifiable;

    /**
     *  @ORM\OneToMany(targetEntity="NbVersionPluginVersion", mappedBy="nbVersion", cascade={"persist", "remove"})     
     */
    protected $nbVersionsPluginVersions;

    /** @ORM\Column(type="datetime") */
    protected $catalog_rebuild_requested;

    /** @ORM\Column(type="datetime") */
    protected $catalog_rebuild;
    
    public function __construct() {
        $this->nbVersionsPluginVersions = new ArrayCollection();
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

    public function setVerifiable($v) {
        $this->verifiable = $v;
    }

    public function getVerifiable() {
        return $this->verifiable;
    }

    public function getNbVersionsPluginVersions() {
        return $this->nbVersionsPluginVersions;
    }

    function getCatalogRebuildRequested(): \DateTime {
        return $this->catalog_rebuild_requested;
    }

    function setCatalogRebuildRequested($catalog_rebuild_requested) {
        $this->catalog_rebuild_requested = $catalog_rebuild_requested;
    }

    function getCatalogRebuild(): \DateTime {
        return $this->catalog_rebuild;
    }

    function setCatalogRebuild($catalog_rebuild) {
        $this->catalog_rebuild = $catalog_rebuild;
    }
}
