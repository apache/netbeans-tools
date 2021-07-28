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
# Extracts, if required, a $1 zip file into the $2 dire tcory.
#
# Version 2.0 by vieiro@apache.org
# This script is expected to be run by the 'www-data' user.
#

if [ $# -ne 2 ]; then
    echo "Use: extract-if-required.sh zipfile output-directory"
    echo "     Extracts the zipfile into output-directory"
    exit 2
fi

ZIP_FILE=$1
DEST_DIR=$2
TS_FILE="$1.timestamp"

if [ ! -f "$ZIP_FILE" ]; then
    echo "$ZIP_FILE not found."
    exit 1
fi

if [ ! -f "$TS_FILE" ] || [ "$ZIP_FILE" -nt "$TS_FILE" ]; then
    echo "Unzipping $ZIP_FILE zip..."
    rm -rf "$DEST_DIR"
    mkdir -p "$DEST_DIR"
    if [ $? -ne 0 ]; then
        echo "Error creating $DEST_DIR"
        exit 3
    fi
    (cd "$DEST_DIR"; unzip "$ZIP_FILE" > /dev/null )
    if [ $? -ne 0 ]; then
        echo "Error unzipping $ZIP_FILE ..."
        echo "Removing the ZIP and timestamp files..."
        rm -rf "$ZIP_FILE"
        rm -rf "$TS_FILE"
    else
        touch "$TS_FILE"
    fi
else
    echo "Unzipping $ZIP_FILE is not required."
fi


