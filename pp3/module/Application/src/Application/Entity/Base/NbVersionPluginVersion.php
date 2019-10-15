<?php

namespace Application\Entity\Base;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

class NbVersionPluginVersion {

     /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     */
    protected $id;

    /** @ORM\Column(type="integer") */
    protected $plugin_version_id;

    /** @ORM\Column(type="integer") */
    protected $nb_version_id;

    /** @ORM\Column(type="integer") */
    protected $verification_id;

    /**
     * @ORM\ManyToOne(targetEntity="PluginVersion", inversedBy="nbVersionsPluginVersions")
     * @ORM\JoinColumn(name="plugin_version_id", referencedColumnName="id")
     */
    protected $pluginVersion;

    /**
     * @ORM\ManyToOne(targetEntity="NbVersion", inversedBy="nbVersionsPluginVersions")
     * @ORM\JoinColumn(name="nb_version_id", referencedColumnName="id")
     */
    protected $nbVersion;

    /**
     * @ORM\OneToOne(targetEntity="Verification", inversedBy="nbVersionPluginVersion", cascade={"remove"})
     * @ORM\JoinColumn(name="verification_id", referencedColumnName="id")
     */
    protected $verification;

    public function __construct() {
        $this->pluginVersion = new ArrayCollection();
        return $this;
    }

    public function getNbVersionId() {
        return $this->nb_version_id;
    }

    public function setNbVersionId($id) {
        $this->nb_version_id = $id;
    }

    public function getId() {
        return $this->id;
    }

    public function setId($id) {
        $this->id = $id;
    }


    public function getPluginVersion() {
        return $this->pluginVersion;
    }

    public function setPluginVersion($pluginVersion) {
        $this->pluginVersion = $pluginVersion;
    }    

    public function getNbVersion() {
        return $this->nbVersion;
    }

    public function setNbVersion($nbVersion) {
        $this->nbVersion = $nbVersion;
    }   
    
    public function getVerificationId() {
        return $this->verification_id;
    }

    public function getVerification() {
        return $this->verification;
    }

    public function setVerification($v) {
        $this->verification = $v;
    }
}