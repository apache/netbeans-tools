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
    private $_catalogSavePath;
    /**
     * @var \Application\Repository\PluginVersionRepository
     */
    private $_pluginVersionRepository;

    public function __construct($pluginVersionRepository, $version, $items, $isExperimental, $dtdPath, $downloadPath, $catalogSavePath) {
        $this->_pluginVersionRepository = $pluginVersionRepository;
        $this->_version = $version;
        $this->_items = $items;
        $this->_isExperimental = $isExperimental;
        $this->_dtdPath = $dtdPath;
        $this->_downloadPath = $downloadPath;
        $this->_catalogSavePath = $catalogSavePath;
    }

    public function asXml($validate, &$validationErrors = null) {
        $implementation = new \DOMImplementation();
        $dtd = $implementation->createDocumentType(
                                    self::MODULE_UPDATES_ELEMENT,
                                    self::CATALOG_DTD,
                                    $this->_dtdPath);

        $xml = $implementation->createDocument('', '', $dtd);
        $modulesEl = $xml->createElement(self::MODULE_UPDATES_ELEMENT);
        $d = new \DateTime('now');
        $modulesEl->setAttribute(self::MODULE_UPDATES_ATTR_timestamp, $d->format('s/i/h/d/m/Y'));

        $validationErrors = array();

        $licenses = array();

        libxml_set_external_entity_loader(
            function ($public, $system, $context) {
                if($public === '-//NetBeans//DTD Autoupdate Catalog 2.8//EN') {
                    return __DIR__ . "/../../../../../public/dtd/autoupdate-catalog-2_8.dtd";
                } else if($public === '-//NetBeans//DTD Autoupdate Module Info 2.0//EN') {
                    return __DIR__ . "/../../../../../public/dtd/autoupdate-info-2_0.dtd";
                } else if($public === '-//NetBeans//DTD Autoupdate Module Info 2.2//EN') {
                    return __DIR__ . "/../../../../../public/dtd/autoupdate-info-2_2.dtd";
                } else if($public === '-//NetBeans//DTD Autoupdate Module Info 2.3//EN') {
                    return __DIR__ . "/../../../../../public/dtd/autoupdate-info-2_3.dtd";
                } else if($public === '-//NetBeans//DTD Autoupdate Module Info 2.4//EN') {
                    return __DIR__ . "/../../../../../public/dtd/autoupdate-info-2_4.dtd";
                } else if($public === '-//NetBeans//DTD Autoupdate Module Info 2.5//EN') {
                    return __DIR__ . "/../../../../../public/dtd/autoupdate-info-2_5.dtd";
                } else if($public === '-//NetBeans//DTD Autoupdate Module Info 2.7//EN') {
                    return __DIR__ . "/../../../../../public/dtd/autoupdate-info-2_7.dtd";
                }
                return null;
            }
        );

        foreach ($this->_items as $item) {
            $this->updateInfoXML($item);
            $this->_pluginVersionRepository->getEntityManager()->refresh($item);

            $infoXMLResource = $item->getInfoXml();

            if(! $infoXMLResource) {
                $validationErrors[$item->getId()] = sprintf('PluginVersion(id: %d) is missing info.xml', $item->getId());
                continue;
            }

            $infoXML = new \DOMDocument();
            $data = stream_get_contents($item->getInfoXml());
            $infoXML->loadXML($data);

            // For infoXML that is missing a doctype, assume a current one
            if(!$infoXML->doctype) {
                $doctype = $implementation->createDocumentType('module', '-//NetBeans//DTD Autoupdate Module Info 2.7//EN', 'http://www.netbeans.org/dtds/autoupdate-info-2_7.dtd');
                $infoXML->insertBefore($doctype, $infoXML->childNodes[0]);
                $infoXML->loadXML($infoXML->saveXML());
            }
            libxml_use_internal_errors(true);
            if (!$infoXML->validate()) {
                $validationErrors[$item->getId()] = libxml_get_errors();
                libxml_clear_errors();
                libxml_use_internal_errors(false);
                continue;
            }
            libxml_use_internal_errors(false);

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
            $moduleElement->setAttribute(self::MODULE_ATTR_distribution, $this->_downloadPath.'/'.$item->getId().'/'.$item->getArtifactFilename());
            $moduleElement->setAttribute(self::MODULE_ATTR_downloadsize, intval($item->getArtifactSize()));
            foreach(self::MODULE_ATTRS as $attr) {
                if($moduleSource[0]->hasAttribute($attr)) {
                    $moduleElement->setAttribute($attr, $moduleSource[0]->getAttribute($attr));
                }
            }

            $manifestElement = $xml->createElement(self::MANIFEST_ELEMENT);
            foreach (self::MANIFEST_ATTRS as $attr) {
                if ($manifestSource[0]->hasAttribute($attr)) {
                    $manifestElement->setAttribute($attr, $manifestSource[0]->getAttribute($attr));
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

        libxml_use_internal_errors(true);
        if (!$xml->validate()) {
            $validationErrors[-1] = libxml_get_errors();
            libxml_clear_errors(); 
        }
        libxml_use_internal_errors(false);

        if($validate && isset($validationErrors[-1])) {
            $xml->formatOutput = true;
            throw new \Exception('Catalog for '.$this->_version.' is not valid:<br><pre>'.print_r($validationErrors, true) . '</pre><pre><![CDATA[' . $xml->saveXML() .']]></pre>');
        }

        $xml->formatOutput = TRUE;
        return $xml->saveXML();
    }

    public function storeXml($validate, &$errors) {
        $xml = $this->asXml($validate, $errors);
        $path = $this->getCatalogPath();
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

    public function catalogFileExits() {
        return file_exists($this->getCatalogPath()) && file_exists($this->getCatalogPath().'.gz');
    }

    private function getCatalogPath() {
        $filename = $this->_isExperimental ? self::CATALOG_FILE_NAME_EXPERIMENTAL : self::CATALOG_FILE_NAME;
        $path = $this->_catalogSavePath.'/'.$this->_version.'/'.$filename;
        return $path;
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
        $archiveFile = tempnam(sys_get_temp_dir(), "mvn-download");
        $client->setStream("$archiveFile");
        $response = $client->send();
        if ($response->isSuccess()) {
            $filesize = filesize($archiveFile);
            $sha1 = hash_file("sha1", $archiveFile);
            $sha256 = hash_file("sha256", $archiveFile);

            if($sha1Reference && strtolower($sha1) != $sha1Reference) {
                error_log(sprintf('PluginVersion(id: %d) SHA-1 message digest does not match artifact. Expected: %s, Got: %s', $pluginVersion->getId(), $sha1Reference, $sha1));
                return;
            }

            if(! $sha1Reference) {
                $pluginVersion->addDigest("SHA-1", $sha1);
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
            }

            unlink($archiveFile);

            if ($infoXML) {
                // Update persistent data to fetch info.xml only once
                $stream = fopen('php://memory', 'r+');
                fwrite($stream, $infoXML);
                rewind($stream);
                $pluginVersion->setArtifactSize($filesize);
                $pluginVersion->setInfoXml($stream);
                $this->_pluginVersionRepository->merge($pluginVersion);
            }
        }
    }
}
