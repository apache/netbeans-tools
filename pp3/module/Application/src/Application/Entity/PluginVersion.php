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
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

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
