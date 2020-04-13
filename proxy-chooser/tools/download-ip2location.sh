#!/bin/bash

# Licensed to the Apache Software Foundation (ASF) under one
# or more contributor license agreements.  See the NOTICE file
# distributed with this work for additional information
# regarding copyright ownership.  The ASF licenses this file
# to you under the Apache License, Version 2.0 (the
# "License"); you may not use this file except in compliance
# with the License.  You may obtain a copy of the License at
#
#   https://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing,
# software distributed under the License is distributed on an
# "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
# KIND, either express or implied.  See the License for the
# specific language governing permissions and limitations
# under the License.

# Helper script to download the IP2LOCATION data required for
# geolocation based proxy selection

PWD=`pwd`
BIN_DIR=`dirname $0`
ABS_BIN_DIR=`readlink -f $BIN_DIR`
DATA_DIR="$ABS_BIN_DIR/../data"
cd $DATA_DIR
curl https://download.ip2location.com/lite/IP2LOCATION-LITE-DB1.IPV6.BIN.ZIP > IP2LOCATION-LITE-DB1.IPV6.BIN.ZIP
curl https://download.ip2location.com/lite/IP2LOCATION-LITE-DB1.BIN.ZIP > IP2LOCATION-LITE-DB1.BIN.ZIP
unzip -o IP2LOCATION-LITE-DB1.IPV6.BIN.ZIP IP2LOCATION-LITE-DB1.IPV6.BIN
unzip -o IP2LOCATION-LITE-DB1.BIN.ZIP IP2LOCATION-LITE-DB1.BIN
rm *.ZIP
cd $PWD
