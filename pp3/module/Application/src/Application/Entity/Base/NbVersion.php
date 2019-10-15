<?php

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
}
