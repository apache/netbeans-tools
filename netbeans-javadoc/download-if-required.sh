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
# Downloads $1 in $2.
# If download fails, then $2 is deleted.
#
# Version 2.0 by vieiro@apache.org
# This script is expected to be run by the 'www-data' user.
#

#
#
# Step 0 - Check arguments
#
if [ $# -ne 2 ]; then
    echo "Use: download-if-required.sh url file"
    echo "   Downloads (if required) url into file."
    exit 1
fi

URL=$1
DEST_FILE=$2
if [ ! -f "$DEST_FILE" ]; then
    echo "Downloading $URL"
    echo curl -f -o "$DEST_FILE" -s -L "$URL"
    curl -f -o "$DEST_FILE" -s -L "$URL"
else
    echo "Downloading (again) from $URL"
    curl -f -o "$DEST_FILE" -z "$DEST_FILE" -s -L "$URL"
fi
if [ $? -ne 0 ]; then
    echo "Error downloading $DEST_FILE"
    rm -rf $DEST_FILE
    exit 1
fi


