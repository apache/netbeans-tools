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

namespace Application\Controller;

use Application\Pp\Catalog;
use Application\Repository\UserRepository;
use Exception;

class CliController extends BaseController {

    /**
     * @var \Application\Repository\PluginVersionRepository
     */
    private $_pluginVersionRepository;
    /**
     * @var \Application\Repository\NbVersionRepository
     */
    private $_nbVersionRepository;

    public function __construct($nbVersionRepository, $pluginVersionRepository, $config) {
        parent::__construct($config);
        $this->_pluginVersionRepository = $pluginVersionRepository;
        $this->_nbVersionRepository = $nbVersionRepository;
    }

    public function generateCatalogsAction() {
        $request = $this->getRequest();

        $versions = $this->_nbVersionRepository->getNbVersionCatalogToBeRebuild();
        foreach ($versions as $v) {
            $version = $v->getVersion();
            $itemsVerified = $this->_pluginVersionRepository->getVerifiedVersionsByNbVersion($version);
            $itemsExperimental = $this->_pluginVersionRepository->getNonVerifiedVersionsByNbVersion($version);
            $catalog = new Catalog($this->_pluginVersionRepository, $version, $itemsVerified, false, $this->_config['pp3']['dtdPath'], $this->getDownloadBaseUrl());
            try {
                $xml = $catalog->asXml(true);
                $catalog->storeXml($this->_config['pp3']['catalogSavepath'], $xml);
            } catch (Exception $e) {
                echo($e);
            }

            $catalog = new Catalog($this->_pluginVersionRepository, $version, $itemsExperimental, true, $this->_config['pp3']['dtdPath'], $this->getDownloadBaseUrl());
            try {
                $xml = $catalog->asXml(true);
                $catalog->storeXml($this->_config['pp3']['catalogSavepath'], $xml);
            } catch (Exception $e) {
                echo($e);
            }

            $v->markCatalogRebuild();
            $this->_nbVersionRepository->persist($v);
        }
    }

    protected function getDownloadBaseUrl() {
        return $this->_config['pp3']['downloadBaseUrl'];
    }

}
