#!/usr/bin/php

# Licensed to the Apache Software Foundation (ASF) under one
# or more contributor license agreements.  See the NOTICE file
# distributed with this work for additional information
# regarding copyright ownership.  The ASF licenses this file
# to you under the Apache License, Version 2.0 (the
# "License"); you may not use this file except in compliance
# with the License.  You may obtain a copy of the License at
#
#   http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing,
# software distributed under the License is distributed on an
# "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
# KIND, either express or implied.  See the License for the
# specific language governing permissions and limitations
# under the License.

<?php

function logMe($message) {
    echo "\n".date(DATE_ATOM)." ".$message;
}

function download($url,$destination) {
    echo "\n  $url??$destination";
    $ch = curl_init();
    if (!file_exists($destination)) {
        logMe ("Downloading $url");
    }
    else {
        logMe("Downloading again from $url");
    }
    curl_close($ch);
}


$jsonNetBeans = file_get_contents('https://gitbox.apache.org/repos/asf?p=netbeans-jenkins-lib.git;a=blob_plain;f=meta/netbeansrelease.json');
$netbeandata = json_decode($jsonNetBeans,TRUE);

foreach ($netbeandata as $key=>$value) {

    echo "\nReading info for release".$key."\n";
    download("https://build.apache.org/job/netbeans-TLP/job/netbeans/job/".$key."/lastSuccessfulBuild/artifact/WEBZIP.zip","/var/tmp/netbeans-".$key."-javadoc.zip");

}


// get information from json file
// $jsonNetBeans = file_get_contents('https://gitbox.apache.org/repos/asf?p=netbeans-jenkins-lib.git;a=blob_plain;f=meta/netbeansrelease.json');
// dump for debug
// var_dump(json_decode($jsonNetBeans));
// php on vm is able to decode json

// curl not availble in php


?>
