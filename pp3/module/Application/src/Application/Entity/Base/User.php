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

class User {

    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     */
    protected $id;

    /** @ORM\Column(name="idp_provider_id", type="string", length=20) */
    protected $idpProviderId;

    /** @ORM\Column(name="idp_user_id", type="string", length=100) */
    protected $idpUserId;

    /** @ORM\Column(name="name", type="string", length=255) */
    protected $name;

    /** @ORM\Column(name="email", type="string", length=100) */
    protected $email;

    /** @ORM\Column(name="admin", type="boolean") */
    protected $admin = false;

    /** @ORM\Column(name="verifier", type="boolean") */
    protected $verifier = false;

    public function __construct() {
        return $this;
    }

    /**
     * @return integer
     */
    function getId() {
        return $this->id;
    }

    /**
     * @return string
     */
    function getIdpProviderId() {
        return $this->idpProviderId;
    }

    /**
     * @return string
     */
    function getIdpUserId() {
        return $this->idpUserId;
    }

    /**
     * @return string
     */
    function getName() {
        return $this->name;
    }

    /**
     * @return string
     */
    function getEmail() {
        return $this->email;
    }

    /**
     * @param string $idpProviderId
     * @return void
     */
    function setIdpProviderId($idpProviderId) {
        $this->idpProviderId = $idpProviderId;
    }

    /**
     * @param string $idpUserId
     * @return void
     */
    function setIdpUserId($idpUserId) {
        $this->idpUserId = $idpUserId;
    }

    /**
     * @param string $name
     * @return void
     */
    function setName($name) {
        $this->name = $name;
    }

    /**
     * @param string $email
     * @return void
     */
    function setEmail($email) {
        $this->email = $email;
    }

    /**
     * @return boolean
     */
    function isAdmin() {
        return $this->admin;
    }

    /**
     * @return boolean
     */
    function isVerifier() {
        return $this->verifier;
    }

    /**
     * @param boolean $admin
     * @return void
     */
    function setAdmin($admin) {
        $this->admin = $admin;
    }

    /**
     * @param boolean $verifier
     * @return void
     */
    function setVerifier($verifier) {
        $this->verifier = $verifier;
    }
}
