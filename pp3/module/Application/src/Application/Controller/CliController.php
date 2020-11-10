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
use Application\Entity\PluginVersion;
use Zend\Mail;
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
    /**
     * @var \Application\Repository\NbVersionPluginVersionRepository
     */
    private $_nbVersionPluginVersionRepository;

    public function __construct($nbVersionRepository, $pluginVersionRepository, $nbVersionPluginVersionRepository, $config) {
        parent::__construct($config);
        $this->_pluginVersionRepository = $pluginVersionRepository;
        $this->_nbVersionRepository = $nbVersionRepository;
        $this->_nbVersionPluginVersionRepository = $nbVersionPluginVersionRepository;
    }

    public function generateCatalogsAction() {
        printf("Regenerating catalogs " . ((new \DateTime('now'))->format("Y-m-d\TH:i:sO")). "\n");
        $versions = $this->_nbVersionRepository->getEntityRepository()->findAll();
        foreach ($versions as $v) {
            $this->createCatalog($v, true);
            $this->createCatalog($v, false);

            $v->markCatalogRebuild();
            $this->_nbVersionRepository->persist($v);
        }
    }

    private function createCatalog(\Application\Entity\NbVersion $version, $experimental) {
        if ($experimental) {
            $items = $this->_pluginVersionRepository->getNonVerifiedVersionsByNbVersion($version->getVersion(), true);
        } else {
            $items = $this->_pluginVersionRepository->getVerifiedVersionsByNbVersion($version->getVersion(), true);
        }

        $catalog = new Catalog(
                $this->_pluginVersionRepository,
                $version->getVersion(),
                $items,
                $experimental,
                $this->_config['pp3']['dtdPath'],
                $this->getDownloadBaseUrl(),
                $this->_config['pp3']['catalogSavepath']
        );

        $rebuildNeeded = $version->getCatalogRebuildRequested() != null
            && ( $version->getCatalogRebuild() == null 
                 || ($version->getCatalogRebuild()->getTimestamp() < $version->getCatalogRebuildRequested()->getTimestamp())
               );

        $rebuildNeeded |= (! $catalog->catalogFileExits());

        if (! $rebuildNeeded) {
            printf("Skipping %scatalog for %s\n", $experimental ? 'experimental ' : '', $version->getVersion());
            return;
        }

        printf("Generating %scatalog for %s\n", $experimental ? 'experimental ' : '', $version->getVersion());

        try {
            $validationErrors = array();
            $catalog->storeXml(true, $validationErrors);
            foreach($validationErrors as $pluginVersionId => $errorList) {
                $versionErrors = array();
                foreach($errorList as $errorEntry) {
                    $versionErrors[] = array(
                        'code' => $errorEntry->code, 
                        'message' => $errorEntry->message
                    );
                }
                $pluginVersion = $this->_pluginVersionRepository->find($pluginVersionId);
                $pluginVersion->setErrorMessage(json_encode($versionErrors));
                // Remove netbeans version assignments
                foreach($pluginVersion->getNbVersionsPluginVersions() as $nbvPv) {
                    $this->_nbVersionPluginVersionRepository->remove($nbvPv);
                }
                $this->_pluginVersionRepository->persist($pluginVersion);
                $this->_sendErrorInformation($pluginVersion, $errorList);
            }
        } catch (Exception $e) {
            echo($e);
        }
    }

    private function _sendErrorInformation(PluginVersion $pluginVersion, $errorList) {
        $errors = '';
        foreach($errorList as $errorEntry) {
            $errors .= sprintf(" - %s (Code: %d)\n", trim($errorEntry->message), $errorEntry->code);
        }
        $plugin = $pluginVersion->getPlugin();
        $mail = new Mail\Message();
        $mail->addTo($plugin->getAuthor()->getEmail());
        $mail->setFrom('webmaster@netbeans.apache.org', 'NetBeans webmaster');
        $mail->setSubject('Publishing of your plugin '.$plugin->getName().' failed');
        $mail->setBody('Hello plugin owner,

this is to inform you that publishing of your plugin on the Plugin Portal Update Center failed.

Plugin:  '.$plugin->getName().'
Version: '.$pluginVersion->getVersion().'
Errors:
' . $errors . '

Do not give up though. Address the comment(s), upload new binary of your plugin and request the verification again!

Good luck!
NetBeans development team

P.S.: This is an automatic email. DO NOT REPLY to this email.');
        $transport = new Mail\Transport\Sendmail();
        $transport->send($mail);
    }

    protected function getDownloadBaseUrl() {
        return $this->_config['pp3']['downloadBaseUrl'];
    }

}
