<?php

namespace Application\Entity\Base;

use Doctrine\ORM\Mapping as ORM;
use Application\Entity\Plugin;
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

    /**
     *  @ORM\OneToMany(targetEntity="NbVersionPluginVersion", mappedBy="pluginVersion", cascade={"persist", "remove"})     
     */
    protected $nbVersionsPluginVersions;
    
    /**
     * @ORM\ManyToOne(targetEntity="Plugin", inversedBy="versions")
     * @ORM\JoinColumn(name="plugin_id", referencedColumnName="id")
     */
    protected $plugin;

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
}
