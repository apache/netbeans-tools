<!--
  Licensed to the Apache Software Foundation (ASF) under one
  or more contributor license agreements.  See the NOTICE file
  distributed with this work for additional information
  regarding copyright ownership.  The ASF licenses this file
  to you under the Apache License, Version 2.0 (the
  "License"); you may not use this file except in compliance
  with the License.  You may obtain a copy of the License at

    https://www.apache.org/licenses/LICENSE-2.0

  Unless required by applicable law or agreed to in writing,
  software distributed under the License is distributed on an
  "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
  KIND, either express or implied.  See the License for the
  specific language governing permissions and limitations
  under the License.
 -->

Proxy Chooser for Apache NetBeans
=================================

The updates.xml.gz files that are created at build time from the netbeans build
system contain relative references to the NBMs. Access to the NBMs is then
dispatched into the mirror network of apache via

https://www.apache.org/dyn/closer.lua

There are at least two problems with this approach:

- closer.lua is hit many times and can cause blocks because this is detected as
  abuse of the the download system
- closer.lua resolves to many different proxies, which collides with company
  policies, that require whitelisting of external downloads.

This PHP script uses the updates.xml.gz file of the Apache NetBeans releases
to produce an updates.xml.gz file with absolute links, that all point to the
same download mirror server and does not cause further closer.lua calls.

In addition the script allows to request a fixed mirror to be used.

Sample invocations:

https://netbeans-vm1.apache.org/uc-proxy-chooser/12.2/updates.xml.gz

This call generates the updates.xml.gz with a mirror that is randomly chosen
from the list of mirrors associated with the country that is detected based on
the request IP address.

https://netbeans-vm1.apache.org/uc-proxy-chooser/12.2/updates.xml.gz?mirror=ftp.tudelft.nl

This call generates the updates.xml.gz with the mirror ftp.tudelft.nl. The
parameter "mirror" takes the hostname of the desired mirror.

https://netbeans-vm1.apache.org/uc-proxy-chooser/12.2/updates.xml.gz?mirror=doesnotexist

If the mirror is not found in the mirror list the ASF provides, a http status
400 is generated. As body data the list of accepted values is returned.

https://netbeans-vm1.apache.org/uc-proxy-chooser/12.2/updates.xml.gz?mirror=BASE

This forces the delivery of the base file with relative links.

Prerequisites
-------------

- php-bcmath

Installation
------------

The base directory is assumed to be `/var/www/html/uc` in that directory for
each released version of netbeans a subdirectory is found, each of these holds
a .htaccess like this:

```
RedirectMatch ^/uc/12.0/((?!(updates\.xml|licenses)).*)(\?.*)?$ https://www.apache.org/dyn/closer.lua?action=download&filename=netbeans/netbeans/12.0/nbms/$1
```

So the updates.xml and the license files are served from the netbeans-vm, while
the NBMs are downloaded from the mirror network.

- The code of the proxy-chooser is to be placed into the directory
  `/var/www/html/uc-proxy-chooser`
- The directory `/var/www/html/uc-proxy-chooser/data` needs to be writeable by
  the PHP process

Users wishing to use the new system have to move from

https://netbeans.apache.org/nb/updates/dev/updates.xml.gz

to

https://netbeans.apache.org/uc-proxy-chooser/12.3/updates.xml.gz

Additional work
---------------

The IP2LOCATION database files need to be manually placed into the data
directory and kept up-to-date.

The manual procedure:

```
cd /var/www/html/uc/proxy-chooser/data
curl https://download.ip2location.com/lite/IP2LOCATION-LITE-DB1.IPV6.BIN.ZIP > IP2LOCATION-LITE-DB1.IPV6.BIN.ZIP
curl https://download.ip2location.com/lite/IP2LOCATION-LITE-DB1.BIN.ZIP > IP2LOCATION-LITE-DB1.BIN.ZIP
unzip -o IP2LOCATION-LITE-DB1.IPV6.BIN.ZIP IP2LOCATION-LITE-DB1.IPV6.BIN
unzip -o IP2LOCATION-LITE-DB1.BIN.ZIP IP2LOCATION-LITE-DB1.BIN
rm *.zip
```