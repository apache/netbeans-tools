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

import java.io.BufferedReader;
import java.io.File;
import java.io.FileOutputStream;
import java.io.FileReader;
import java.io.InputStream;
import java.io.OutputStreamWriter;
import java.io.StringReader;
import java.io.Writer;
import java.net.HttpURLConnection;
import java.net.URL;
import java.util.HashMap;
import java.util.Map;
import java.util.Properties;
import java.util.TreeSet;
import java.util.regex.Matcher;
import java.util.regex.Pattern;
import javax.xml.parsers.DocumentBuilder;
import javax.xml.parsers.DocumentBuilderFactory;
import javax.xml.transform.OutputKeys;
import javax.xml.transform.Transformer;
import javax.xml.transform.TransformerConfigurationException;
import javax.xml.transform.TransformerFactory;
import javax.xml.transform.dom.DOMSource;
import javax.xml.transform.stream.StreamResult;
import org.apache.tools.ant.BuildException;
import org.apache.tools.ant.Task;
import org.w3c.dom.Comment;
import org.w3c.dom.Document;
import org.w3c.dom.Element;
import org.w3c.dom.NodeList;
import org.xml.sax.InputSource;

/**
 * Downloads the WikiNames in an index.properties file.
 *
 * @author Antonio Vieiro <vieiro@apache.org>
 */
public class WikiEntriesDownloader extends Task {

    /**
     * The properties file for the index containing DevFaq entries to download.
     */
    private File indexProperties;

    public void setIndexProperties(File indexProperties) {
        this.indexProperties = indexProperties;
    }

    /**
     * The prefix used to select which Wiki entries to download. Defaults to
     * "DevFaq"
     */
    private String prefix = "DevFaq";

    public void setPrefix(String prefix) {
        this.prefix = prefix;
    }

    /**
     * The destination directory where entries will be downloaded.
     */
    private File destDir;

    public void setDestDir(File destDir) {
        this.destDir = destDir;
    }

    private boolean skipExisting = true;

    public void setSkipExisting(boolean skipExisting) {
        this.skipExisting = skipExisting;
    }

    @Override
    public void execute() throws BuildException {

        try {
            FileReader reader = new FileReader(indexProperties);
            Properties entries = new Properties();
            entries.load(reader);
            reader.close();

            TreeSet<String> wikiEntries = new TreeSet<>();
            for (Object keyObject : entries.keySet()) {
                String key = (String) keyObject;
                if (key.startsWith(prefix) && ! key.endsWith("section")) {
                    wikiEntries.add(key);
                }
            }

            int nEntries = 0;

            for (String wikiEntry : wikiEntries) {
                /* Download the file if required */
                File wikiDest = new File(destDir, wikiEntry + ".xml");
                nEntries++;
                if (skipExisting && wikiDest.exists()) {
                    log("  Skipping already existing " + wikiEntry);
                } else {
                    downloadWikiEntry(wikiEntry, wikiDest);
                }
//                /* Scan for images in the file and download the Wikimedia XML if required */
//                Map<String, String> images = getImageLinks(wikiDest);

            }

            log("Downloaded " + nEntries + " entries.");

        } catch (Exception e) {
            throw new BuildException(e);
        }
    }

    private static final Pattern WIKI_IMAGE_PATTERN = Pattern.compile(".*\\[\\[Image:([^\\]]+)\\]\\].*");

    private Map<String, String> getImageLinks(String wikiText) throws Exception {
        Matcher m = WIKI_IMAGE_PATTERN.matcher(wikiText);
        HashMap<String, String> images = new HashMap<>();
        while (m.find()) {
            String image = m.group(1);
            String imageName = Character.toUpperCase(image.charAt(0)) + image.substring(1);
            images.put(image, imageName);
        }
        return images;
    }

    private static final String APACHE_LICENSE_HEADER = ""
            + "\n"
            + "    Licensed to the Apache Software Foundation (ASF) under one\n"
            + "    or more contributor license agreements.  See the NOTICE file\n"
            + "    distributed with this work for additional information\n"
            + "    regarding copyright ownership.  The ASF licenses this file\n"
            + "    to you under the Apache License, Version 2.0 (the\n"
            + "    \"License\"); you may not use this file except in compliance\n"
            + "    with the License.  You may obtain a copy of the License at\n"
            + "\n"
            + "      http://www.apache.org/licenses/LICENSE-2.0\n"
            + "\n"
            + "    Unless required by applicable law or agreed to in writing,\n"
            + "    software distributed under the License is distributed on an\n"
            + "    \"AS IS\" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY\n"
            + "    KIND, either express or implied.  See the License for the\n"
            + "    specific language governing permissions and limitations\n"
            + "    under the License.\n"
            + "\n";

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
            transformer = TransformerFactory.newInstance().newTransformer();
            transformer.setOutputProperty(OutputKeys.INDENT, "yes");
            transformer.setOutputProperty("{http://xml.apache.org/xslt}indent-amount", "2");
        }
        return transformer;
    }

    private void saveXML(Document dom, File file) throws Exception {
        Writer writer = new OutputStreamWriter(new FileOutputStream(file), "utf-8");
        StreamResult result = new StreamResult(writer);
        DOMSource source = new DOMSource(dom);
        Transformer transformer = getTransformer();
        transformer.transform(source, result);
        writer.close();
    }

    private void downloadWikiEntry(String wikiEntry, File wikiDest) throws Exception {

        String referer = "http://wiki.netbeans.org/" + wikiEntry;

        // This URL returns the wiki entry in XML format
        // The wikitext content is returned in the <export> element.
        URL url = new URL(String.format("http://wiki.netbeans.org/wiki/api.php?action=query&titles=%s&export&format=xml", wikiEntry));
        HttpURLConnection http = (HttpURLConnection) url.openConnection();
        http.setDefaultUseCaches(true);
        http.setDoInput(true);
        http.setUseCaches(true);
        http.addRequestProperty("User-Agent", "Mozilla/5.0 (X11; Linux x86_64; rv:57.0) Gecko/20100101 Firefox/57.0");
        http.addRequestProperty("Accept-Language", "en");
        http.addRequestProperty("Referer", referer);
        http.addRequestProperty("Accept", "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8");

        http.connect();

        log("  Fetching url " + url);
        log("    to " + wikiDest.getAbsolutePath());

        if (http.getResponseCode() == 200) {
            log("  Sleeping...");
            Thread.currentThread().sleep(500L);

            /*
            Parse the HTTP input, which is a MediaWiki XML document.
            From the document we just want to retrieve the 'export' tag text.
             */
            String exportTagText = null;
            DocumentBuilder db = getDocumentBuilderFactory().newDocumentBuilder();

            Document dom = db.parse(http.getInputStream());
            NodeList exportElements = dom.getElementsByTagName("export");
            if (exportElements.getLength() == 1) {
                exportTagText = exportElements.item(0).getTextContent();
            } else {
                throw new Exception("Cannot retrieve 'export' element for wiki name " + wikiEntry);
            }

            /* Now parse the exportTagText, which is itself a XML document */
            StringReader exportContent = new StringReader(exportTagText);
            InputSource inputSource = new InputSource(exportContent);
            dom = db.parse(inputSource);
            /* Add a comment and save it */
            Comment comment = dom.createComment(APACHE_LICENSE_HEADER);
            Element e = dom.getDocumentElement();
            dom.insertBefore(comment, e);
            saveXML(dom, wikiDest);
            exportContent.close();

            /* Fetch the wikitext, inside the 'text' element */
            NodeList textElements = dom.getElementsByTagName("text");
            if (textElements.getLength() == 1) {
                String wikiText = textElements.item(0).getTextContent();
                Map<String, String> images = getImageLinks(wikiText);
                System.out.println("IMAGES: " + images);
                for (Map.Entry<String, String> imageEntry : images.entrySet()) {
                    String imageName = imageEntry.getKey();
                    String imageValue = imageEntry.getKey();
                    File imageDest = new File(destDir, imageName);
                    if (skipExisting && imageDest.exists()) {
                        log("  Skipping already existing " + imageName);
                    } else {
                        downloadImage(wikiEntry, imageValue, imageDest);
                    }
                }
            } else {
                log("WARNING: Empty WikiEntry " + wikiEntry);
            }

        } else {
            log("BAD RESPONSE CODE: " + http.getResponseCode());
        }
    }

    /**
     * Downloads an image
     *
     * @param wikiEntry The wiki entry where the image is contained
     * @param imageName THe name of the image, first letter is upper case.
     * @param imageDest The image destination
     * @param xmlDest The xml destination (response from wikimedia api
     * containing the final image url.
     * @throws Exception
     */
    private void downloadImage(String wikiEntry, String imageName, File imageDest) throws Exception {

        String referer = "http://wiki.netbeans.org/" + wikiEntry;
        URL url = new URL(String.format("http://wiki.netbeans.org/wiki/api.php?action=query&titles=File:%s&prop=imageinfo&iiprop=url&format=xml", imageName));
        HttpURLConnection http = (HttpURLConnection) url.openConnection();
        http.setDefaultUseCaches(true);
        http.setDoInput(true);
        http.setUseCaches(true);
        http.addRequestProperty("User-Agent", "Mozilla/5.0 (X11; Linux x86_64; rv:57.0) Gecko/20100101 Firefox/57.0");
        http.addRequestProperty("Accept-Language", "en");
        http.addRequestProperty("Referer", referer);
        http.addRequestProperty("Accept", "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8");

        http.connect();

        if (http.getResponseCode() == 200) {
            DocumentBuilder builder = getDocumentBuilderFactory().newDocumentBuilder();
            Document dom = builder.parse(http.getInputStream());
            /*
        XML RESPONSE LOOKS LIKE
 <?xml version="1.0"?>
<api>
  <query>
    <normalized>
      <n from="File:Addplatform_DevFaqAppClientOnNbPlatformTut.png" to="File:Addplatform DevFaqAppClientOnNbPlatformTut.png"/>
    </normalized>
    <pages>
      <page pageid="1055" ns="6" title="File:Addplatform DevFaqAppClientOnNbPlatformTut.png" imagerepository="local">
        <imageinfo>
          <ii url="http://wiki.netbeans.org/wiki/images/7/7c/Addplatform_DevFaqAppClientOnNbPlatformTut.png" descriptionurl="http://wiki.netbeans.org/File:Addplatform_DevFaqAppClientOnNbPlatformTut.png"/>
        </imageinfo>
      </page>
    </pages>
  </query>
  <query-continue>
    <imageinfo iistart="2009-11-04T15:28:54Z"/>
  </query-continue>
</api>
             */
            String imageURL = null;
            NodeList n = dom.getElementsByTagName("ii");
            if (n != null && n.getLength() > 0) {
                Element e = (Element) n.item(0);
                imageURL = e.getAttribute("url");
            }
            if (imageURL == null) {
                log("Could not fetch image url");
            } else {
                log("Image url: '" + imageURL + "'");
                url = new URL(imageURL);
                downloadURL(url, referer, imageDest);
            }
        }
    }

    private void downloadURL(URL url, String referer, File wikiDest) throws Exception {
        HttpURLConnection http = (HttpURLConnection) url.openConnection();
        http.setDefaultUseCaches(true);
        http.setDoInput(true);
        http.setUseCaches(true);
        http.addRequestProperty("User-Agent", "Mozilla/5.0 (X11; Linux x86_64; rv:57.0) Gecko/20100101 Firefox/57.0");
        http.addRequestProperty("Accept-Language", "en");
        http.addRequestProperty("Referer", referer);
        http.addRequestProperty("Accept", "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8");

        http.connect();

        log("  Fetching url " + url);
        log("    to " + wikiDest.getAbsolutePath());

        if (http.getResponseCode() == 200) {
            log("  Sleeping...");
            Thread.currentThread().sleep(500L);
            InputStream input = http.getInputStream();
            byte[] chunk = new byte[16 * 1024];
            FileOutputStream fos = new FileOutputStream(wikiDest);
            do {
                int n = input.read(chunk);
                if (n < 0) {
                    break;
                }
                fos.write(chunk, 0, n);
            } while (true);
            input.close();
            fos.close();
        } else {
            log("BAD RESPONSE CODE: " + http.getResponseCode());
        }
    }
}
