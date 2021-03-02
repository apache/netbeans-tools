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

class CountryCodes {
    private $data = [];

    public function __construct() {
        $fid = fopen(__DIR__ . "/apache_proxy_countries.csv", "r");
        while($row = fgetcsv($fid)) {
            $this->data[] = [
                "apacheCode" => strtolower($row[0]),
                "ip2countryCode" => strtolower($row[1]),
                "name" => $row[2]
            ];
        }
        $this->data[] = [
            "apacheCode" => "backup",
            "ip2countryCode" => "-",
            "name" => "Fallback Mirror"
        ];
        fclose($fid);
    }

    public function resolveNameApache($code) {
        $normalized = strtolower($code);
        foreach($this->data as $element) {
            if($element['apacheCode'] == $normalized) {
                return $element['name'];
            }
        }
        return "-";
    }

    public function resolveApacheCodeFromIP2Country($code) {
        $normalized = strtolower($code);
        foreach($this->data as $element) {
            if($element['ip2countryCode'] == $normalized) {
                return $element['apacheCode'];
            }
        }
        return null;
    }
}