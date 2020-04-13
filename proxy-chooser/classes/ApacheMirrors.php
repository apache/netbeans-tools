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

class ApacheMirrors {
    private $cacheFile;
    private $mirrorUrl;
    private $mirrors = array();
    private $countryMirrors = array();

    public function __construct($mirrorUrl, $cacheFile) {
        $this->cacheFile = $cacheFile;
        $this->mirrorUrl = $mirrorUrl;
        $this->ensureMirrorListIsCurrent();
        $this->readMirrorList();
    }

    /**
     * Ensure that the locally cached list of apache mirrors is current. This
     * function checks the age of the locally cached file and if it is to old, a
     * new list is fetched from the original location.
     */
    private function ensureMirrorListIsCurrent() {
        if (file_exists($this->cacheFile)) {
            $age = filemtime($this->cacheFile);
        } else {
            $age = 0;
        }
        $now = time();
        // Cache the mirror list for one hour
        if ((!$age) || ($age < ($now - (1 * 60 * 60)))) {
            $mirrorlistInput = file_get_contents($this->mirrorUrl);
            if ($mirrorlistInput) {
                $cacheDir = dirname($this->cacheFile);
                $tmp = tempnam($cacheDir, "mirors");
                $fid = fopen($tmp, "w");
                fwrite($fid, $mirrorlistInput);
                fclose($fid);
                if (!rename($tmp, $this->cacheFile)) {
                    throw new Exception("Failed to rename mirror list");
                }
            }
        }
    }

    /**
     * Read the cached mirror list and return the list of http mirrors.
     *
     * @return map hostname to full url
     */
    private function readMirrorList() {
        $mirrorFid = fopen($this->cacheFile, "r");
        $this->mirrors = array();
        $this->countryMirrors = array();
        while ($row = fgetcsv($mirrorFid, 0, " ")) {
            if (count($row) >= 3 && ($row[0] == "http")) {
                $country = strtolower($row[1]);
                $hostname = parse_url($row[2], PHP_URL_HOST);
                if (!isset($this->countryMirrors[$country])) {
                    $this->countryMirrors[$country] = [];
                }
                $this->countryMirrors[$country][] = $hostname;
                $this->mirrors[$hostname] = $row[2];
            }
        }
        fclose($mirrorFid);
    }

    public function getProxyList() {
        return $this->mirrors;
    }

    public function proxyExists($hostname) {
        return array_key_exists($hostname, $this->mirrors);
    }

    public function proxyExistsCountry($countryCode) {
        return array_key_exists(strtolower($countryCode), $this->countryMirrors);
    }

    public function getProxy($hostname) {
        return $this->mirrors[$hostname];
    }

    public function getProxyCountryKey($countryCode) {
        if(! $this->proxyExistsCountry($countryCode)) {
            return null;
        }
        $candidates = $this->countryMirrors[strtolower($countryCode)];
        if(count($candidates) > 0) {
            $rand = rand(0, count($candidates) - 1);
            return $candidates[$rand];
        } else {
            return null;
        }
    }

    public function getFallbackProxyKey() {
        $candidates = $this->countryMirrors[strtolower('backup')];
        if (count($candidates) > 0) {
            $rand = rand(0, count($candidates) - 1);
            return $candidates[$rand];
        } else {
            return null;
        }
    }

    public function getCountryCodes() {
        return array_keys($this->countryMirrors);
    }

    public function getMirrorsForCountry($countryCode) {
        if(array_key_exists($countryCode, $this->countryMirrors)) {
            return $this->countryMirrors[$countryCode];
        } else {
            return [];
        }
    }
}