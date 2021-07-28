#!/usr/bin/env bash

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

######################################################################
#
# Downloads netbeans javadoc zip file (if required) from bulids.apache.org
# Extracts the zip file (if required) into a proper directory
#
# Version 1.0 by vieiro@apache.org
# This script is expected to be run by the 'www-data' user.
#

#----------------------------------------------------------------------
# A log function
# 

NETBEANS_JAVADOC_LOG="/var/log/netbeans-javadoc/netbeans-javadoc.log"

log() {
	TS=`date -Iseconds`
        (echo "$TS $@" | tee "$NETBEANS_JAVADOC_LOG")
}

#----------------------------------------------------------------------
# A function that downloads an URL (if required)
# and stores it in a file
# $1 - URL
# $2 - Destination file
#

download() {
        URL=$1
        DEST_FILE=$2
        if [ ! -f "$DEST_FILE" ]; then
                log "Downloading $URL"
	        curl -o "$DEST_FILE" -s -L "$URL"
        else
                log "Downloading again from $URL"
                curl -o "$DEST_FILE" -z "$DEST_FILE" -s -L "$URL"
        fi
        if [ $? -ne 0 ]; then
                log "Error downloading $DEST_FILE"
                rm -rf $DEST_FILE
                exit 1
        fi
}

#----------------------------------------------------------------------
# A function that extracts a zip file if required
# $1 - The zip file
# $2 - The destination folder
# $3 - A file used to check for timestamp. Updated after extraction.
#

extract() {
        ZIP_FILE=$1
        DEST_DIR=$2
        TS_FILE=$3

        if [ ! -f "$TS_FILE" ] || [ "$ZIP_FILE" -nt "$TS_FILE" ]; then
                log "Unzipping $ZIP_FILE zip..."
                rm -rf "$DEST_DIR"
                mkdir -p "$DEST_DIR"
                if [ $? -ne 0 ]; then
                        log "Error creating $DEST_DIR"
                        exit 3
                fi
                (cd "$DEST_DIR"; unzip "$ZIP_FILE" > /dev/null )
                if [ $? -ne 0 ]; then
                        log "Error unzipping $ZIP_FILE ..."
			log "Removing the ZIP and timestamp files..."
			rm -rf "$ZIP_FILE"
			rm -rf "$TS_FILE"
                else
                        touch "$TS_FILE"
                fi
        else
                log "Unzipping $ZIP_FILE is not required."
        fi
}


#
# Step 1 - Create the log file
#
rm -rf "$NETBEANS_JAVADOC_LOG"
touch "$NETBEANS_JAVADOC_LOG"
if [ $? -ne 0 ]; then
	echo "$NETBEANS_JAVADOC_LOG is not writable. Check permissions for the www-data user"
	exit 1
fi

#
# Step 2 - Download and extract the javadoc for different NetBeans releases
#

# NetBeans 9.0

download "https://builds.apache.org/job/netbeans-TLP/job/netbeans/job/release90/lastSuccessfulBuild/artifact/WEBZIP.zip" "/var/tmp/netbeans-90-javadoc.zip"
extract "/var/tmp/netbeans-90-javadoc.zip" "/var/www/bits.netbeans.org/9.0/javadoc" "/var/tmp/netbeans-90-javadoc.zip.timestamp"

# NetBeans 10.0 

download "https://builds.apache.org/job/netbeans-TLP/job/netbeans/job/release100/lastSuccessfulBuild/artifact/WEBZIP.zip" "/var/tmp/netbeans-100-javadoc.zip"
extract "/var/tmp/netbeans-100-javadoc.zip" "/var/www/bits.netbeans.org/10.0/javadoc" "/var/tmp/netbeans-100-javadoc.zip.timestamp"

# NetBeans 11.0

download "https://builds.apache.org/job/netbeans-TLP/job/netbeans/job/release110/lastSuccessfulBuild/artifact/WEBZIP.zip" "/var/tmp/netbeans-110-javadoc.zip"
extract "/var/tmp/netbeans-110-javadoc.zip" "/var/www/bits.netbeans.org/11.0/javadoc" "/var/tmp/netbeans-110-javadoc.zip.timestamp"

# NetBeans 11.1
download "https://builds.apache.org/job/netbeans-TLP/job/netbeans/job/release111/lastSuccessfulBuild/artifact/WEBZIP.zip" "/var/tmp/netbeans-111-javadoc.zip"
extract "/var/tmp/netbeans-111-javadoc.zip" "/var/www/bits.netbeans.org/11.1/javadoc" "/var/tmp/netbeans-111-javadoc.zip.timestamp"

# NetBeans dev

download "https://builds.apache.org/job/netbeans-TLP/job/netbeans/job/master/lastSuccessfulBuild/artifact/WEBZIP.zip" "/var/tmp/netbeans-dev-javadoc.zip"
extract "/var/tmp/netbeans-dev-javadoc.zip" "/var/www/bits.netbeans.org/dev/javadoc" "/var/tmp/netbeans-dev-javadoc.zip.timestamp"

# Maven stuff #deprecated
#download "https://builds.apache.org/job/netbeans-mavenutils-website/lastSuccessfulBuild/artifact/mavenusite.zip" "/var/tmp/netbeans-mavenusite.zip"
#extract "/var/tmp/netbeans-mavenusite.zip" "/var/www/bits.netbeans.org/mavenutilities" "/var/tmp/netbeans-mavenusite.zip.timestamp"

# Maven nbm-plugin-maven
download "https://builds.apache.org/job/netbeans-maven-TLP/job/netbeans-mavenutils-nbm-maven-plugin/job/master/lastSuccessfulBuild/artifact/WEBSITE.zip" "/var/tmp/netbeans-mavenutils-nbm-maven-pluginsite.zip"
extract "/var/tmp/netbeans-mavenutils-nbm-maven-pluginsite.zip" "/var/www/bits.netbeans.org/mavenutilities/nbm-maven-plugin" "/var/tmp/netbeans-mavenutils-nbm-maven-pluginsite.zip.timestamp"

#Maven nb-repository-plugin
download "https://builds.apache.org/job/netbeans-maven-TLP/job/netbeans-mavenutils-nb-repository-plugin/job/master/lastSuccessfulBuild/artifact/WEBSITE.zip" "/var/tmp/netbeans-mavenutils-nb-repository-pluginsite.zip"
extract "/var/tmp/netbeans-mavenutils-nb-repository-pluginsite.zip" "/var/www/bits.netbeans.org/mavenutilities/nb-repository-plugin" "/var/tmp/netbeans-mavenutils-nb-repository-pluginsite.zip.timestamp"

# Maven nbm-shared
download "https://builds.apache.org/job/netbeans-maven-TLP/job/netbeans-mavenutils-nbm-shared/job/master/lastSuccessfulBuild/artifact/WEBSITE.zip" "/var/tmp/netbeans-mavenutils-nbm-sharedsite.zip"
extract "/var/tmp/netbeans-mavenutils-nbm-sharedsite.zip" "/var/www/bits.netbeans.org/mavenutilities/nbm-shared" "/var/tmp/netbeans-mavenutils-nbm-sharedsite.zip.timestamp"

# Maven utilities parent
download "https://builds.apache.org/job/netbeans-maven-TLP/job/netbeans-mavenutils-parent/job/master/lastSuccessfulBuild/artifact/WEBSITE.zip" "/var/tmp/netbeans-mavenutils-parentsite.zip"
extract "/var/tmp/netbeans-mavenutils-parentsite.zip" "/var/www/bits.netbeans.org/mavenutilities/parent" "/var/tmp/netbeans-mavenutils-parentsite.zip.timestamp"

# Maven utilities harness
download "https://builds.apache.org/job/netbeans-maven-TLP/job/netbeans-mavenutils-nbm-maven-harness/job/master/lastSuccessfulBuild/artifact/WEBSITE.zip" "/var/tmp/netbeans-mavenutils-nbm-maven-harnesssite.zip"
extract "/var/tmp/netbeans-mavenutils-nbm-maven-harnesssite.zip" "/var/www/bits.netbeans.org/mavenutilities/nbm-maven-harness" "/var/tmp/netbeans-mavenutils-nbm-maven-harnesssite.zip.timestamp"

