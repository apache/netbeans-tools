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
