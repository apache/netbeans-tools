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

require("classes/ApacheMirrors.php");
require("classes/IP2Location.php");
require("classes/CountryCodes.php");
require("classes/config.php");

header("X-Attribution: This site or product includes IP2Proxy LITE data available from https://lite.ip2location.com");

const MIRROR_ARCHIVE = "ARCHIVE";
const MIRROR_CI = "CI";

$requestUri = parse_url($_SERVER['REQUEST_URI']);
if(! preg_match('!.*updates.xml.gz$!', $requestUri['path'])) {
    sendError(404, "Only compressed (updates.xml.gz) version supported");
    exit(0);
}

// Initialize mirror list
$mirrors = new ApacheMirrors($CONFIG['mirrorListUrl'], $CONFIG['mirrorCacheFile']);
$countryCodes = new CountryCodes();

/**
 * Create a http error response. After the message is created, excecution is
 * stopped.
 *
 * @param type $httpStatus the http status to report
 * @param type $httpMessage status line message
 * @param type $userMessage body message, if not provided, $httpMessage will
 * be reused
 */
function sendError($httpStatus, $httpMessage, $userMessage = false) {
    header("HTTP/1.0 $httpStatus $httpMessage");
    header("Content-Type: text/plain");
    if($userMessage) {
        echo $userMessage;
    } else {
        echo $httpMessage . "\n";
    }
    exit(1);
}

/**
 * Generate a http status 400 and return a list of supported mirrors.
 *
 * @param string[] $mirrors
 */
function generateErrorList(ApacheMirrors $mirrors) {
    $userMessage = "Please choose one of these mirrors:\n\n";
    foreach ($mirrors->getProxyList() as $host => $url) {
        $userMessage .= sprintf("%s - %s\n", $host, $url);
    }
    sendError("400", "Invalid mirror", $userMessage);
}

/**
 * Stream the raw updates.xml file and stop execution. The contents is send
 * unmodified.
 *
 * @param string $updatesXML
 */
function streamRawUpdate($updatesXML) {
    header("Content-Type: application/x-gzip");
    $fid = fopen($updatesXML, "r");
    $output = fopen("php://output", "r");
    stream_copy_to_stream($fid, $output);
    fclose($fid);
    fclose($output);
    exit(0);
}

/**
 * Create an absolute url from the supplied url. If the url is already absolute,
 * it will be returned unmodified, unless it is a reference to the 'closer.lua'
 * service of the apache foundation. If it is a reference to 'closer.lua' the
 * referenced file will be resolved using the supplied mirror.
 *
 * @param string $url
 * @param string $baseHref
 * @param string $mirrorKey
 * @param string $mirror
 * @return string
 */
function createAbsoluteUrl(string $url, string $baseHref, string $mirrorKey, string $mirror) {
    $urlParts = parse_url($url);
    if($mirrorKey != MIRROR_CI && strpos($urlParts['path'], "/dyn/closer.lua") === 0) {
        // This rewrites calls to closer.lua into direct absolute paths with a
        // fixed name. This makes the assumption, that the update.xml.gz has
        // the same status as the referenced parts.
        //
        // I.e. if we determine, that the distribution was moved to archive
        // (updates.xml.gz is found on archive.apache.org), it is expected, that
        // all resources referenced through closer.lua are also to be found
        // in the archive. The same holds true for the "regular distribution"
        // case.
        $queryParts = [];
        parse_str($urlParts['query'], $queryParts);
        return $mirror . $queryParts['filename'];
    } else if(strpos($urlParts['path'], '/') === 0) {
        return $url;
    } else {
        return $baseHref . $url;
    }
}

/**
 * Read the supplied updates.xml and prepend the supplied mirror.
 *
 * @param string $updatesXML
 * @param string $mirror
 * @param string $nbVersion
 */
function streamUpdatesWithMirror($updatesXML, $mirrorKey, $mirror, $nbVersion) {
    global $CONFIG;

    $bufferFile = sprintf($CONFIG['cachePattern'], $nbVersion, $mirrorKey);

    if ((!file_exists($bufferFile)) || filemtime($updatesXML) > filemtime($bufferFile)) {
        mkdir(dirname($bufferFile), 0774, TRUE);
        if($mirror) {
            $baseHref = $mirror . "netbeans/netbeans/$nbVersion/nbms/";
            if($mirrorKey == 'CI') {
                $baseHref = $mirror;
            }
            $data = file_get_contents($updatesXML);
            $doc = new DOMDocument();
            $doc->loadXML(gzdecode($data));
            foreach ($doc->getElementsByTagName("module") as $mod) {
                $distribution = $mod->getAttribute("distribution");
                $mod->setAttribute("distribution", createAbsoluteUrl($distribution, $baseHref, $mirrorKey, $mirror));
            }
            foreach ($doc->getElementsByTagName("license") as $li) {
                if($li->hasAttribute("url")) {
                    $url = $li->getAttribute("url");
                    $li->setAttribute("url", createAbsoluteUrl($url, $baseHref, $mirrorKey, $mirror));
                }
            }
            $xml = gzencode($doc->saveXML());
        } else {
            $data = file_get_contents($updatesXML);
            $xml = gzencode($data);
        }
        file_put_contents($bufferFile, $xml);
    }

    $etag = '"' . md5($bufferFile . filemtime($bufferFile)) . '"';

    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $etag) {
        header("HTTP/1.0 304 Not modified");
        exit(0);
    }

    header("Etag: $etag");

    streamRawUpdate($bufferFile);
}

/**
 * Based on the requsted URI extract the target netbeans version.
 *
 * @param string $request_uri
 * @return string
 */
function extractNbVersion($request_uri) {
    $matches = array();
    if (preg_match('!.*/uc-proxy-chooser/([^/]+)/updates.xml.gz!', $request_uri, $matches)) {
        return $matches[1];
    } else {
        sendError("400", "Invalid URL");
    }
}

/**
 * Check if the requested netbeans version is distributed via the mirror network
 *
 * @param type $nbVersion
 * @return type
 */
function isOnMirrors($nbVersion) {
    $primaryServerUrl = "https://downloads.apache.org/netbeans/netbeans/$nbVersion/nbms/updates.xml";
    return urlHasContent($primaryServerUrl);
}

/**
 * Check if the requested netbeans version is archived
 *
 * @param type $nbVersion
 * @return type
 */
function isInArchive($nbVersion) {
    $primaryServerUrl = "https://archive.apache.org/dist/netbeans/netbeans/$nbVersion/nbms/updates.xml.gz";
    return urlHasContent($primaryServerUrl);
}

function ciUrl($version) {
    $pathVersion = preg_replace("!\D!", "", $version);
    return "https://ci-builds.apache.org/job/Netbeans/job/netbeans-TLP/job/netbeans/job/release$pathVersion/lastSuccessfulBuild/artifact/dist/netbeans/nbms/";
}

/**
 * Check if the requested netbeans version is distributed via the mirror network
 * or only accessible as a nightly build.
 *
 * @param type $nbVersion
 * @return type
 */
function isCIBuild($nbVersion) {
    $ciUrl = ciUrl($nbVersion) . "updates.xml.gz";
    return urlHasContent($ciUrl);
}

/**
 * Check if the supplied url return a non 404 response.
 *
 * @param string $url
 * @return boolean
 */
function urlHasContent($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code != 404;
}

// Extract the requested netbeans version from the request URI
$nbVersion = extractNbVersion($requestUri['path']);

// build the path to the updates.xml
$updatesXML = sprintf($CONFIG['updatesPattern'], $nbVersion);

// Check that the requested netbeans version is available
if(!file_exists($updatesXML)) {
    error_log("Failed to open updates.xml: " . $updatesXML);
    sendError("500", "Internal server error");
}

// Check the mirror parameter and check if a valid mirror was selected
$mirror = FALSE;
$mirrorKey = null;
if(array_key_exists("mirror", $_GET)) {
    $mirrorKey = $_GET['mirror'];
    if($mirrorKey == 'BASE') {
        // Request wants to fetch base file
    } else if(! $mirrors->proxyExists($mirrorKey)) {
        generateErrorList($mirrors);
    } else {
        $mirror = $mirrors->getProxy($mirrorKey);
    }
} else {
    $database = new IP2Location\Database(__DIR__ . "/data/IP2LOCATION-LITE-DB1.BIN");
    $database2 = new IP2Location\Database(__DIR__ . "/data/IP2LOCATION-LITE-DB1.IPV6.BIN");

    $res = $database->lookup($_SERVER['REMOTE_ADDR']);
    $res2 = $database2->lookup($_SERVER['REMOTE_ADDR']);

    $proxyKey = null;

    if($res && (! $mirrorKey)) {
        $mirrorKey = $mirrors->getProxyCountryKey($countryCodes->resolveApacheCodeFromIP2Country($res['countryCode']));
    }
    if($res2 && (! $mirrorKey)) {
        $mirrorKey = $mirrors->getProxyCountryKey($countryCodes->resolveApacheCodeFromIP2Country($res2['countryCode']));
    }
    if($mirrorKey == null) {
        $mirrorKey = $mirrors->getFallbackProxyKey();
    }
    $mirror = $mirrors->getProxy($mirrorKey);
}

if (!isOnMirrors($nbVersion)) {
    if (isInArchive($nbVersion)) {
        // If version of netbeans is already archived, the nbms need to be
        // fetched from the apache archive
        $mirror = "https://archive.apache.org/dist/";
        $mirrorKey = MIRROR_ARCHIVE;
    } else if (isCIBuild($nbVersion)) {
        // If version of netbeans is still in prerelease state, the nbms need
        // to be fetched from the CI build system
        $mirror = ciUrl($nbVersion);
        $updatesXML = $mirror . "updates.xml.gz";
        $mirrorKey = MIRROR_CI;
    } else {
        sendError(404, "Not found");
    }
}

streamUpdatesWithMirror($updatesXML, $mirrorKey, $mirror, $nbVersion);
