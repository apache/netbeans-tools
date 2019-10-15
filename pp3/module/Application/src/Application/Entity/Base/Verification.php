<?php

namespace Application\Entity\Base;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

class Verification {

    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     */
    protected $id;

    /** @ORM\Column(type="integer") */
    protected $status;

    /** @ORM\Column(type="datetime") */
    protected $created_at;

    /** @ORM\Column(type="integer") */
    protected $plugin_version_id;

    /**
     * @ORM\OneToMany(targetEntity="VerificationRequest", mappedBy="verification", cascade={"persist", "remove"})
     */
    protected $verification_requests;

    /**
     * @ORM\OneToOne(targetEntity="NbVersionPluginVersion", mappedBy="verification")
     */
    protected $nbVersionPluginVersion;

    public function __construct() {
        $this->verification_requests = new ArrayCollection();
        return $this;
    }

    public function getId() {
        return $this->id;
    }

    public function setId($id) {
        $this->id = $id;
    }

    public function getStatus() {
        return $this->status;
    }

    public function setStatus($status) {
        $this->status = $status;
    }

    public function getCreatedAt() {
        return $this->created_at;
    }

    public function setCreatedAt($cat) {
        $this->created_at = $cat;
    }

    public function getPluginVersionId() {
        return $this->plugin_version_id;
    }

    public function setPluginVersionId($pvid) {
        $this->plugin_version_id = $pvid;
    }

    public function getPluginVersion() {
        return $this->plugin_version;
    }      
    
    public function getVerificationRequests() {
        return $this->verification_requests;
    }

    public function getNbVersionPluginVersion() {
        return $this->nbVersionPluginVersion;
    }

}
