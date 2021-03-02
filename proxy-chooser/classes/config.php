<?php

$CONFIG = array(
    // mirrorListUrl holds the URL where the list of mirrors can be found
    "mirrorListUrl" => "https://www.apache.org/mirrors/mirrors.list",
    // mirrorCacheFile holds a local copy of the contents of mirrorListUrl
    "mirrorCacheFile" => __DIR__ . "/../data/mirrors.list",
    // Path to the updates.xml - will be passed through sprintf, first parameter
    // is the netbeans version. It is expected, that updates.xml
    "updatesPattern" => __DIR__ . '/../../uc/%1$s/updates.xml.gz',
    // Location where the generated updates files are cached (first parameter
    // is netbeans version, second parameter is the chosen proxy)
    "cachePattern" => __DIR__ . '/../data/%1$s/%2$s/updates.xml.gz',
    // Pattern for Update Center URL, parameter 1 is the request host,
    // parameter 2 is the chosen mirror
    "updateCenterUrl" => 'https://%1$s/uc-proxy-chooser/<strong>version</strong>/updates.xml.gz?mirror=%2$s'
);
