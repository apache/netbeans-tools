<?php

namespace Application\Entity;

use Doctrine\ORM\Mapping as ORM;
use Zend\Http\Client;

/**
 * @ORM\Entity
 * @ORM\Table(name="plugin_version")
 */
class PluginVersion extends Base\PluginVersion {

    public function setupUrl() {
        $url = explode('/', $this->plugin->getUrl());
        array_pop($url);
        array_push($url, $this->version);
        $baseUrl = implode('/', $url).'/';
        $this->setUrl($baseUrl.$this->plugin->getArtifactId().'-'.$this->version.$this->_getBinaryExtension($baseUrl));
    }
    
    public function addNbVersion($version) {
        $this->nbVersionsPluginVersions[] = $version;
    }
    
    private function _getBinaryExtension($baseUrl) {
        // there could be either .nbm or .jar, so check both
        $extension = array('.nbm', '.jar');
        $found = null;
        foreach($extension as $ext) {
            $path = $baseUrl.$this->plugin->getArtifactId().'-'.$this->version.$ext;
            $client = new Client($path, array(
                'maxredirects' => 0,
                'timeout' => 30
            ));
            $client->setMethod('HEAD');
            $response = $client->send();
            if ($response->isSuccess()) {
                return $ext;
            }
        }
        throw new \Exception('Nbm nor jar binary found on '.$baseUrl);
    }
}
