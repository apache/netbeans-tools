<?php

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
    function setIdpProviderId($idpProviderId): void {
        $this->idpProviderId = $idpProviderId;
    }

    /**
     * @param string $idpUserId
     * @return void
     */
    function setIdpUserId($idpUserId): void {
        $this->idpUserId = $idpUserId;
    }

    /**
     * @param string $name
     * @return void
     */
    function setName($name): void {
        $this->name = $name;
    }

    /**
     * @param string $email
     * @return void
     */
    function setEmail($email): void {
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
    function setAdmin($admin): void {
        $this->admin = $admin;
    }

    /**
     * @param boolean $verifier
     * @return void
     */
    function setVerifier($verifier): void {
        $this->verifier = $verifier;
    }
}
