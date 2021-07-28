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

/*

 This scripts downloads a JSON file containing NetBeans release
 information, then for each release it downloads (if required) a ZIP
 file with the release javadoc, and then it extracts (if required)
 the javadoc in an appropriate directory.

 Questions to vieiro@apache.org or ebarboni@apache.org.

 */

/* Customizable variables. MUST USE ABSOLUTE PATHS */

/* The URL to download the JSON with release information */
$url = 'https://raw.githubusercontent.com/apache/netbeans-jenkins-lib/master/meta/netbeansrelease.json';
/* The directory where the zip files (and timestamps) are kept. Usually /var/tmp/netbeans */
$output_dir = '/var/tmp/';
/* The directory where the zip files are extracted. Usually /var/www/virtual-host/javadoc/ */
$extract_dir = '/var/www/bits.netbeans.org/';

/* Non customizable below */
$pwd = getcwd();
$download_if_required = $pwd . '/download-if-required.sh';
$extract_if_required = $pwd . '/extract-if-required.sh';
$json = file_get_contents($url);
$indexa = array();
if ($json) {
    $data = json_decode($json, true);
    // The JSON is an object where keys are branch names.
    // Each entry in the object has a 'version_name' with version information.
    // Target url is of the form https://bits.netbeans.org/${version_name}/javadoc
    if ($data) {
        exec('mkdir -p ' . $output_dir);
        exec('mkdir -p ' . $extract_dir);
        foreach (array_keys($data) as $branch_name) {
            $release_info = $data[$branch_name];
            $version_name = $release_info['versionName'];
            $longapidocurl = $release_info['apidocurl'];
            $apidocurl = substr($longapidocurl,26); // remove bits.netbeans.org
            $release_extract_dir = $extract_dir . $apidocurl . '/';
	    $index = '<li>';
	    $index .= '<a href="'.$longapidocurl.'">Apache NetBeans ';
	    if ($release_info['tlp']=="false") {
	       $index .= "(incubating) ";
            }
            $index .= $version_name;
            $index .= '</a>';
	    $index .= "</li>";
            $indexa[$release_info['position']] = $index;
	    $netbeans_javadoc_zip_url = "https://builds.apache.org/job/Netbeans/job/netbeans-TLP/job/netbeans/job/" .
		    $branch_name .
		    "/lastSuccessfulBuild/artifact/WEBZIP.zip";

            $netbeans_javadoc_zip_file = $output_dir . '/netbeans-javadoc-' . $version_name . '.zip';

            /* Download using 'download-if-required.sh' */
            $command = $download_if_required . ' ' . $netbeans_javadoc_zip_url . ' ' . $netbeans_javadoc_zip_file;
            echo "Downloading javadoc zip for release ", PHP_EOL;
            echo "    URL: ", $netbeans_javadoc_zip_url, PHP_EOL;
            echo "    To : ", $netbeans_javadoc_zip_file, PHP_EOL;
            exec($command, $command_out, $return_val);
            if ($return_val == 0) {
                /* Extract using 'extract-if-required.sh' */
                $command = $extract_if_required . ' ' . $netbeans_javadoc_zip_file . ' ' . $release_extract_dir;
		exec("mkdir -p " . $release_extract_dir, $commnad_out, $return_val);
                echo "Extracting javadoc zip for release ", $branch_name, PHP_EOL;
                echo "   From: ", $netbeans_javadoc_zip_file, PHP_EOL;
                echo "   To  : ", $release_extract_dir, PHP_EOL;
                exec($command, $command_out, $return_val);
                if ($return_val != 0) {
                    echo "Could not extract file: ", PHP_EOL;
                    echo implode(PHP_EOL, $command_out);
                }
            } else {
                echo "Could not download file: ", PHP_EOL;
                echo implode(PHP_EOL, $command_out);
            }
        }
        $findex = $output_dir .'/jindex.txt';
	krsort($indexa,SORT_NUMERIC);
        file_put_contents($findex,implode("",$indexa));
        echo "Done.", PHP_EOL;
    } else {
        echo "Could not download JSON.", PHP_EOL;
    }
}

?>
