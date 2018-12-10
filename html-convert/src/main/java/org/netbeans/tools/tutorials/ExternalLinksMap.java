/*
    Licensed to the Apache Software Foundation (ASF) under one
    or more contributor license agreements.  See the NOTICE file
    distributed with this work for additional information
    regarding copyright ownership.  The ASF licenses this file
    to you under the Apache License, Version 2.0 (the
    "License"); you may not use this file except in compliance
    with the License.  You may obtain a copy of the License at

      http://www.apache.org/licenses/LICENSE-2.0

    Unless required by applicable law or agreed to in writing,
    software distributed under the License is distributed on an
    "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
    KIND, either express or implied.  See the License for the
    specific language governing permissions and limitations
    under the License.
 */
package org.netbeans.tools.tutorials;

import java.net.MalformedURLException;
import java.net.URL;
import java.util.Set;
import java.util.TreeMap;
import java.util.TreeSet;
import java.util.logging.Level;
import java.util.logging.Logger;

/**
 * Maps external links (String, href) to File's referencing it.
 */
public class ExternalLinksMap {
    
    /** Maps "href" to tutorials. */
    private TreeMap<String, TreeSet<String>> hrefToFile;
    /** Maps domain names ("bits.netbeans.org", for instance) to hrefs */
    private TreeMap<String, TreeSet<String>> domainToHref;
    
    public ExternalLinksMap() {
        hrefToFile = new TreeMap<>();
        domainToHref = new TreeMap<>();
    }
    
    public void addExternalLink(String href, String tutorial) {
        try {
            URL url = new URL(href);
            TreeSet<String> hrefs = domainToHref.get(url.getHost());
            if (hrefs == null) {
                hrefs = new TreeSet<>();
                domainToHref.put(url.getHost(), hrefs);
            }
            hrefs.add(href);
            
            TreeSet<String> tutorials = hrefToFile.get(href);
            if (tutorials == null) {
                tutorials = new TreeSet<>();
                hrefToFile.put(href, tutorials);
            }
            tutorials.add(tutorial);
        } catch (MalformedURLException ex) {
            Logger.getLogger(ExternalLinksMap.class.getName()).log(Level.SEVERE, null, ex);
        }
    }
    
    public Set<String> getDomains() {
        return domainToHref.keySet();
    }
    
    public Set<String> getHrefs(String domain) {
        return domainToHref.get(domain);
    }
    
    public Set<String> getTutorials(String href) {
        return hrefToFile.get(href);
    }
    
    @Override
    public String toString() {
        return domainToHref.toString() + " " + hrefToFile.toString();
    }
}
