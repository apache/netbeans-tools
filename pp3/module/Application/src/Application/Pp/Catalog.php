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

use ZipArchive;
use Zend\Http\Client;

class Catalog {
    const OSGI_JAR_PARSER = 'java -jar "' . __DIR__ . '/../../../external/osgi-jar-parser.jar"';

    const CATALOG_FILE_NAME = 'catalog.xml';
    const CATALOG_FILE_NAME_EXPERIMENTAL = 'catalog-experimental.xml';
    
    const CATALOG_DTD = '-//NetBeans//DTD Autoupdate Catalog 2.8//EN';

    const MODULE_UPDATES_ELEMENT = 'module_updates';
    const MODULE_UPDATES_ATTR_timestamp = 'timestamp';

    // Attributes from info.xml/module  that can be used in catalog/module
    const MODULE_ELEMENT = 'module';
    const MODULE_ATTRS = ['codenamebase', 'homepage',  'license',
                          'needsrestart', 'moduleauthor', 'releasedate',
                          'global', 'preferredupdate', 'targetcluster'];
    // distribution will always be set by the portal
    const MODULE_ATTR_distribution = 'distribution';
    const MODULE_ATTR_downloadsize = 'downloadsize';

    const MANIFEST_ELEMENT = 'manifest';
    // Attributes from info.xml/manifest that can be used in catalog/manifest
    const MANIFEST_ATTRS = ['OpenIDE-Module',
        'OpenIDE-Module-Name',
        'OpenIDE-Module-Specification-Version',
        'OpenIDE-Module-Implementation-Version',
        'OpenIDE-Module-Module-Dependencies',
        'OpenIDE-Module-Package-Dependencies',
        'OpenIDE-Module-Java-Dependencies',
        'OpenIDE-Module-IDE-Dependencies',
        'OpenIDE-Module-Short-Description',
        'OpenIDE-Module-Long-Description',
        'OpenIDE-Module-Display-Category',
        'OpenIDE-Module-Provides',
        'OpenIDE-Module-Requires',
        'OpenIDE-Module-Recommends',
        'OpenIDE-Module-Needs',
        'AutoUpdate-Show-In-Client',
        'AutoUpdate-Essential-Module',
        'OpenIDE-Module-Fragment-Host'];

    const MESSAGEDIGEST_ELEMENT = 'message_digest';
    const MESSAGEDIGEST_ATTR_algorithm = 'algorithm';
    const MESSAGEDIGEST_ATTRS_value = 'value';

    const LICENSE_ELEMENT = 'license';
    const LICENSE_ATTR_name = 'name';

    /**
     * @var \Application\Entity\PluginVersion[]
     */
    private $_items;
    private $_version;
    private $_isExperimental;
    private $_downloadPath;
    /**
     * @var \Application\Repository\PluginVersionRepository
     */
    private $_pluginVersionRepository;

    public function __construct($pluginVersionRepository, $version, $items, $isExperimental, $dtdPath, $downloadPath) {
        $this->_pluginVersionRepository = $pluginVersionRepository;
        $this->_version = $version;
        $this->_items = $items;
        $this->_isExperimental = $isExperimental;
        $this->_dtdPath = $dtdPath;
        $this->_downloadPath = $downloadPath;
    }

    public function asXml($validate = true) {
        $implementation = new \DOMImplementation();
        $dtd = $implementation->createDocumentType(
                                    self::MODULE_UPDATES_ELEMENT,
                                    self::CATALOG_DTD,
                                    $this->_dtdPath);

        $xml = $implementation->createDocument('', '', $dtd);
        $modulesEl = $xml->createElement(self::MODULE_UPDATES_ELEMENT);
        $d = new \DateTime('now');
        $modulesEl->setAttribute(self::MODULE_UPDATES_ATTR_timestamp, $d->format('s/i/h/d/m/Y'));

        $licenses = array();

        foreach ($this->_items as $item) {
            $this->updateInfoXML($item);
            $infoXMLResource = $item->getInfoXml();

            if(! $infoXMLResource) {
                error_log(sprintf('PluginVersion(id: %d) is missing info.xml', $item->getId()));
                continue;
            }

            $infoXML = new \DOMDocument();
            $infoXML->loadXML(stream_get_contents($item->getInfoXml()));
            $moduleSource = $infoXML->getElementsByTagName(self::MODULE_ELEMENT);

            if(count($moduleSource) != 1) {
                error_log(sprintf('PluginVersion(id: %d) invalid info.xml not exactly one module element', $item->getId()));
                continue;
            }

            $manifestSource = $moduleSource[0]->getElementsByTagName(self::MANIFEST_ELEMENT);
            if(count($manifestSource) != 1) {
                error_log(sprintf('PluginVersion(id: %d) invalid info.xml not exactly one manifest element', $item->getId()));
                continue;
            }

            $licenseSource = $moduleSource[0]->getElementsByTagName(self::LICENSE_ELEMENT);

            $moduleElement = $xml->createElement(self::MODULE_ELEMENT);
            $moduleElement->setAttribute(self::MODULE_ATTR_distribution, $this->_downloadPath.$item->getId());
            $moduleElement->setAttribute(self::MODULE_ATTR_downloadsize, intval($item->getArtifactSize()));
            foreach(self::MODULE_ATTRS as $attr) {
                $inputData = $moduleSource[0]->getAttribute($attr);
                if($inputData) {
                    $moduleElement->setAttribute($attr, $inputData);
                }
            }

            $manifestElement = $xml->createElement(self::MANIFEST_ELEMENT);
            foreach (self::MANIFEST_ATTRS as $attr) {
                $inputData = $manifestSource[0]->getAttribute($attr);
                if ($inputData) {
                    $manifestElement->setAttribute($attr, $inputData);
                }
            }

            if(count($licenseSource) == 1 && ($licenseSource[0]->getAttribute(self::LICENSE_ATTR_name))) {
                $licenses[$licenseSource[0]->getAttribute(self::LICENSE_ATTR_name)] = $licenseSource[0]->textContent;
            }

            $moduleElement->appendChild($manifestElement);

            foreach($item->getDigests() as $digest) {
                $messageDigest = $xml->createElement(self::MESSAGEDIGEST_ELEMENT);
                $messageDigest->setAttribute(self::MESSAGEDIGEST_ATTR_algorithm, $digest->getAlgorithm());
                $messageDigest->setAttribute(self::MESSAGEDIGEST_ATTRS_value, $digest->getValue());
                $moduleElement->appendChild($messageDigest);
            }

            $modulesEl->appendChild($moduleElement);
        }

        foreach($licenses as $name => $text) {
            $licenseElement = $xml->createElement(self::LICENSE_ELEMENT);
            $licenseElement->setAttribute(self::LICENSE_ATTR_name, $name);
            $licenseElement->textContent = $text;
            $modulesEl->appendChild($licenseElement);
        }

        $xml->appendChild($modulesEl);
        if ($validate) {
            libxml_use_internal_errors(true);
            if (!$xml->validate()) {
                $msg = [];
                foreach (libxml_get_errors() as $error) {
                    array_push($msg, $error->message);
                }
                libxml_clear_errors();
                throw new \Exception('Catalog for '.$this->_version.' is not valid:<br/>'.implode('<br/>', $msg));
            }
            libxml_use_internal_errors(false);
        }
        $xml->formatOutput = TRUE;
        return $xml->saveXML();
    }

    public function storeXml($destinationFolder, $xml) {
        $filename = $this->_isExperimental ? self::CATALOG_FILE_NAME_EXPERIMENTAL : self::CATALOG_FILE_NAME;
        $path = $destinationFolder.'/'.$this->_version.'/'.$filename;
        if(!file_exists(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        if (!file_put_contents($path, $xml)) {
            throw new \Exception('Unable to save catalog on path '.$path); 
        }
        // save also .gz
        $gz =gzopen($path.'.gz', 'w9');
        gzwrite($gz, $xml);
        gzclose($gz);
        return true;
    }

    /**
     * Update the info XML for the module
     * 
     * @param \Application\Entity\PluginVersion $pluginVersion
     */
    private function updateInfoXML($pluginVersion) {
        $sha1Reference = false;
        $sha256Reference = false;

        foreach ($pluginVersion->getDigests() as $digest) {
            if ($digest->getAlgorithm() == 'SHA-1') {
                $sha1Reference = $digest->getValue();
            } else if ($digest->getAlgorithm() == 'SHA-256') {
                $sha256Reference = $digest->getValue();
            }
        }

        // Skip update if:
        // - info.xml is present (PP3 assumes immutable sources)
        // - SHA-256 checksum is present
        // - artifact size is present
        if($pluginVersion->getInfoXml() != null && $pluginVersion->getArtifactSize() && $sha256Reference) {
            return;
        }
        // Fetch NBM
        $client = new Client($pluginVersion->getUrl(), array(
            'maxredirects' => 0,
            'timeout' => 30
        ));
        $response = $client->send();
        if ($response->isSuccess()) {
            // Store result to file to make it processible by ZipArchive
            $archiveFile = tempnam(sys_get_temp_dir(), "mvn-download");
            $fid = fopen($archiveFile, "w");
            fwrite($fid, $response->getBody());
            fclose($fid);
            $response = null;

            $filesize = filesize($archiveFile);
            $sha1 = hash_file("sha1", $archiveFile);
            $sha256 = hash_file("sha256", $archiveFile);

            if(strtolower($sha1) != $sha1Reference) {
                error_log(sprintf('PluginVersion(id: %d) SHA-1 message digest does not match artifact. Expected: %s, Got: %s', $pluginVersion->getId(), $sha1Reference, $sha1));
                return;
            }

            if(! $sha256Reference) {
                $pluginVersion->addDigest('SHA-256', $sha256);
            }

            if(substr(strtolower($pluginVersion->getUrl()), -4) == '.jar') {
               $execution = self::OSGI_JAR_PARSER . " " . escapeshellarg($archiveFile);
               $return_var = -1;
               $output = [];
               exec($execution, $output, $return_var);
               $outputString = implode("\n", $output);
               if(intval($return_var) != 0) {
                   error_log(sprintf('PluginVersion(id: %d) Failed to extract info from JAR, Got: %s', $pluginVersion->getId(), $outputString));
                   return;
               }
               $infoXML = $outputString;
            } else {
                // Extract Info/info.xml from archive
                $archive = new ZipArchive();
                $archive->open($archiveFile);
                $infoXML = $archive->getFromName("Info/info.xml");
                $archive->close();
                unlink($archiveFile);
            }

            if ($infoXML) {
                // Update persistent data to fetch info.xml only once
                $stream = fopen('php://memory', 'r+');
                fwrite($stream, $infoXML);
                rewind($stream);
                $pluginVersion->setArtifactSize($filesize);
                $pluginVersion->setInfoXml($stream);
                $this->_pluginVersionRepository->merge($pluginVersion);
                $this->_pluginVersionRepository->getEntityManager()->refresh($pluginVersion);
            }
        }
    }
}
