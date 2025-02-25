<?php

/*
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

/* The URL to download the JSON with release information */
$url = 'https://raw.githubusercontent.com/apache/netbeans-jenkins-lib/master/meta/netbeansrelease.json';

$json = file_get_contents($url);
$baseuc = '/var/www/html/uc/';

function startsWith($haystack, $needle) {
    return substr_compare($haystack, $needle, 0, strlen($needle)) === 0;
}

function endsWith($haystack, $needle) {
    $length = strlen($needle);
    return $length > 0 ? substr($haystack, -$length) === $needle : true;
}

function getXML($filepath) {
    if (endsWith($filepath, '.gz')) {
        return simplexml_load_string(implode(gzfile($filepath)));
    } else {
        return simplexml_load_string(file_get_contents($filepath));
    }
}

function checkUpdateFiles($filename, $version_name) {
    global $baseuc;
    $filepath = $baseuc . $version_name . '/' . $filename;
    if (file_exists($filepath)) {
        $xml = getXML($filepath);
        echo str_pad($version_name, 6);
        if ($xml->notification) {
            if ($xml->notification != "A new version of Apache NetBeans IDE is available!") {
                echo " $filename notified to " . $xml->notification;
            }
            return false;
        } else {
            echo " $filename not notified";
        }
        echo " ";
    }
    return true;
}

function displayActiveHtaccessLine($version_name) {
    global $baseuc;
    $filepath = $baseuc . $version_name . '/.htaccess';
    if (file_exists($filepath)) {
        echo " htaccess[ ";
        $content = file($filepath);
        foreach ($content as $line) {
            if (!((startsWith($line, '#') || empty(trim($line))))) {
                echo str_replace("\n", "", $line) . " ";
            }
        }
        echo "] ";
    }
}

function readInformationforBranch($branch_name, $data) {
    $release_info = $data[$branch_name];
    echo "\n[" . str_pad($branch_name, 13) . "]... " . str_pad($release_info['position'], 3) . " ";
    $version_name = str_replace('/updates.xml.gz', '', str_replace('/updates.xml.gz?{$netbeans.hash.code}', '', str_replace('https://netbeans.apache.org/nb/updates/', '', $release_info['update_url'])));
// check for update.xml

    $nonefound = true;
    $nonefound &= checkUpdateFiles('updates.xml', $version_name);
    $nonefound &= checkUpdateFiles('updates.xml.gz', $version_name);

    if ($nonefound) {
        echo 'no notification found in updates.xml(.gz ) for' . $version_name . " ";
    }
    displayActiveHtaccessLine($version_name);
}

if (false === $json) {
    echo "$json  something is wrong with json";
} else {
    echo $json;
    $data = array_reverse(json_decode($json, true));
    $master = array_shift($data); // remove master from array

    foreach (array_keys($data) as $branch_name) {
        readInformationforBranch($branch_name, $data);
    }
    echo "\n";
}
?>

