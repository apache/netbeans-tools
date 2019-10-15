<?php

namespace Application\Pp;

use Zend\Http\Client;

/**
 * Description of MavenDataLoader
 *
 * @author honza
 */
class MavenDataLoader {

    public function getData($plugin) {
        $xmlData = $this->_fetchData($plugin->getUrl());
        if ($xmlData) {
            return $this->_xmlToArray($xmlData);
        }
    }

    public function getReleaseData($plugin) {
        $url = str_replace('maven-metadata.xml', $plugin->getReleaseVersion(), $plugin->getUrl()).'/'.$plugin->getArtifactId().'-'.$plugin->getReleaseVersion().'.pom';
        $xmlData = $this->_fetchData($url);
        if ($xmlData) {
            return $this->_xmlToArray($xmlData);
        }
    }


    private function _fetchData($url) {
        $client = new Client($url, array(
            'maxredirects' => 0,
            'timeout' => 30
        ));
        $response = $client->send();
        if ($response->isSuccess()) {
            return $response->getBody();
        }
        throw new \Exception('Unable to fetch metadata file from '.$url);
    }

    private function _xmlToArray($xmlstring) {
        $xml = simplexml_load_string($xmlstring, null, LIBXML_NOCDATA);
        $json = json_encode($xml);
        return json_decode($json, TRUE);
    }

}
