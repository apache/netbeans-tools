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
package wiki.export;

import java.io.File;
import java.io.FileFilter;
import java.io.FileReader;
import java.io.FileWriter;
import java.io.PrintWriter;
import java.util.Properties;
import java.util.TreeMap;
import java.util.TreeSet;
import javax.xml.parsers.DocumentBuilder;
import javax.xml.parsers.DocumentBuilderFactory;
import javax.xml.transform.Result;
import javax.xml.transform.Source;
import javax.xml.transform.Transformer;
import javax.xml.transform.TransformerConfigurationException;
import javax.xml.transform.TransformerFactory;
import javax.xml.transform.stream.StreamResult;
import javax.xml.transform.stream.StreamSource;
import org.apache.tools.ant.BuildException;
import org.apache.tools.ant.Task;

/**
 * Generates wikimedia from xml files.
 *
 * @author Antonio Vieiro <vieiro@apache.org>
 */
public class WikiEntries2MediaWiki extends Task {

    /**
     * The properties file for the index containing DevFaq entries to download.
     */
    private File indexProperties;

    public void setIndexProperties(File indexProperties) {
        this.indexProperties = indexProperties;
    }

    /**
     * The destination directory where wikimedia output will be generated.
     */
    private File destDir;

    public void setDestDir(File destDir) {
        this.destDir = destDir;
    }

    /**
     * The source directory where downloaded xml files (and images) are kept.
     */
    private File srcDir;

    public void setSrcDir(File srcDir) {
        this.srcDir = srcDir;
    }

    private DocumentBuilderFactory documentBuilderFactory;

    private DocumentBuilderFactory getDocumentBuilderFactory() {
        if (documentBuilderFactory == null) {
            documentBuilderFactory = DocumentBuilderFactory.newInstance();
            documentBuilderFactory.setNamespaceAware(true);
            documentBuilderFactory.setValidating(false);
        }
        return documentBuilderFactory;
    }

    private Transformer transformer;

    private Transformer getTransformer() throws TransformerConfigurationException {
        if (transformer == null) {
            TransformerFactory factory = TransformerFactory.newInstance();
            Source xslt = new StreamSource(WikiEntries2MediaWiki.class.getResourceAsStream("xml2wikimedia.xsl"));
            transformer = factory.newTransformer(xslt);
        }
        return transformer;
    }

    @Override
    public void execute() throws BuildException {

        FileFilter XML = (file) -> {
            return file.isFile() && file.getName().endsWith(".xml");
        };

        File[] xmlFiles = srcDir.listFiles(XML);

        try {
            DocumentBuilder db = getDocumentBuilderFactory().newDocumentBuilder();
            Transformer t = getTransformer();
            for (File xmlFile : xmlFiles) {
                File textFile = new File(destDir, xmlFile.getName().replaceAll("\\.xml$", "") + ".mediawiki");
                t.reset();
                Source xmlSource = new StreamSource(xmlFile);
                Result result = new StreamResult(textFile);
                t.transform(xmlSource, result);
                log("Generated " + textFile.getAbsolutePath());
            }
        } catch (Exception ex) {
            throw new BuildException(ex);
        }

        /* Finally generate a proper index.mediawiki from index. properties */
        try {
            FileReader reader = new FileReader(indexProperties);
            Properties index = new Properties();
            index.load(reader);
            reader.close();
            File indexFile = new File(destDir, "index.mediawiki");
            log("Creating " + indexFile.getAbsolutePath() + " from " + indexProperties);
            
            TreeMap<String, TreeSet<String>> sections = new TreeMap<>();
            for (Object okey : index.keySet()) {
                String key = (String) okey;
                if (key.endsWith(".section")) {
                    String [] parts = key.split("\\.");
                    String wikiName = parts[0];
                    String sectionName = index.getProperty(key);
                    TreeSet<String> entries = sections.get(sectionName);
                    if (entries == null) {
                        entries = new TreeSet<>();
                        sections.put(sectionName, entries);
                    }
                    entries.add(wikiName);
                }
            }
            
            PrintWriter indexMediawiki = new PrintWriter(new FileWriter(indexFile));
            indexMediawiki.println("=Index=\n");
            for (String section : sections.keySet()) {
                indexMediawiki.println("\n==" + section + "==");
                for (String entry : sections.get(section)) {
                    indexMediawiki.format("* [[%s|%s]]\n", entry, index.getProperty(entry));
                }
            }
            indexMediawiki.close();
        } catch (Exception ex) {
            throw new BuildException(ex);
        }

    }
}
