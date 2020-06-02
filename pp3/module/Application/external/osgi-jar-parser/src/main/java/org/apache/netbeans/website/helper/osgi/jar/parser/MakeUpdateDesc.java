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
package org.apache.netbeans.website.helper.osgi.jar.parser;

import java.io.File;
import java.io.IOException;
import java.io.InputStream;
import java.util.Properties;
import java.util.StringTokenizer;
import java.util.jar.Attributes;
import java.util.jar.JarFile;
import java.util.regex.Matcher;
import java.util.regex.Pattern;
import javax.xml.parsers.DocumentBuilder;
import javax.xml.parsers.DocumentBuilderFactory;
import javax.xml.parsers.ParserConfigurationException;
import javax.xml.transform.OutputKeys;
import javax.xml.transform.Transformer;
import javax.xml.transform.TransformerException;
import javax.xml.transform.TransformerFactory;
import javax.xml.transform.dom.DOMSource;
import javax.xml.transform.stream.StreamResult;
import org.w3c.dom.DOMException;
import org.w3c.dom.Document;
import org.w3c.dom.Element;

/**
 * This is based on MakeUpdateDesc.java from the netbeans build system.
 *
 * @see
 * <a href="https://github.com/apache/netbeans/blob/master/nbbuild/antsrc/org/netbeans/nbbuild/MakeNBM.java">https://github.com/apache/netbeans/blob/master/nbbuild/antsrc/org/netbeans/nbbuild/MakeNBM.java</a>
 */
public class MakeUpdateDesc {

    @SuppressWarnings("UseOfSystemOutOrSystemErr")
    public static void main(String[] argv) {
        try {
            DocumentBuilder db = DocumentBuilderFactory.newInstance().newDocumentBuilder();
            Document document = db.newDocument();
            Element moduleElement = document.createElement("module");
            document.appendChild(moduleElement);
            File file = new File(argv[0]);
            try (JarFile jarFile = new JarFile(file)) {
                fakeOSGiInfoXml(jarFile, file, document);
            }
            Transformer transformer = TransformerFactory.newInstance().newTransformer();
            transformer.setOutputProperty(OutputKeys.INDENT, "yes");
            transformer.transform(new DOMSource(document), new StreamResult(System.out));
        } catch (IOException | ParserConfigurationException | TransformerException | RuntimeException ex) {
            System.err.printf("Failed to extract description\n\n%s\n\n", ex.getMessage());
            ex.printStackTrace(System.err);
            System.exit(1);
        }
        System.exit(0);
    }

    /**
     * Create the equivalent of {@code Info/info.xml} for an OSGi bundle.
     *
     * @param jar a bundle
     *
     * @return a {@code <module ...><manifest .../></module>} valid according to
     * <a href="http://www.netbeans.org/dtds/autoupdate-info-2_5.dtd">DTD</a>
     */
    public static Element fakeOSGiInfoXml(JarFile jar, File whereFrom, Document doc) throws IOException {
        Attributes attr = jar.getManifest().getMainAttributes();
        Properties localized = new Properties();
        String bundleLocalization = attr.getValue("Bundle-Localization");
        if (bundleLocalization != null) {
            try (InputStream is = jar.getInputStream(jar.getEntry(bundleLocalization + ".properties"))) {
                localized.load(is);
            }
        }
        return fakeOSGiInfoXml(attr, localized, whereFrom, doc);
    }

    private static Element fakeOSGiInfoXml(Attributes attr, Properties localized, File whereFrom, Document doc) {
        Element module = doc.getDocumentElement();
        String cnb = extractCodeName(attr);
        module.setAttribute("codenamebase", cnb);
        module.setAttribute("distribution", ""); // seems to be ignored anyway
        module.setAttribute("downloadsize", "0"); // recalculated anyway
        module.setAttribute("targetcluster", "pluginportal"); // #207075 comment #3
        Element manifest = doc.createElement("manifest");
        module.appendChild(manifest);
        manifest.setAttribute("AutoUpdate-Show-In-Client", "false");
        manifest.setAttribute("OpenIDE-Module", cnb);
        String bundleName = loc(localized, attr, "Bundle-Name");
        manifest.setAttribute("OpenIDE-Module-Name", bundleName != null ? bundleName : cnb);
        String bundleVersion = attr.getValue("Bundle-Version");
        manifest.setAttribute("OpenIDE-Module-Specification-Version",
            bundleVersion != null ? bundleVersion.replaceFirst("^(\\d+([.]\\d+([.]\\d+)?)?)([.].+)?$", "$1") : "0");
        String requireBundle = attr.getValue("Require-Bundle");
        if (requireBundle != null) {
            StringBuilder b = new StringBuilder();
            boolean needsNetbinox = false;
            // http://stackoverflow.com/questions/1757065/java-splitting-a-comma-separated-string-but-ignoring-commas-in-quotes
            for (String dep : requireBundle.split(",(?=([^\"]*\"[^\"]*\")*[^\"]*$)")) {
                Matcher m = Pattern.compile("([^;]+)(.*)").matcher(dep);
                if (!m.matches()) {
                    throw new RuntimeException("Could not parse dependency: " + dep + " in " + whereFrom);
                }
                String requiredBundleName = m.group(1); // dep CNB
                if (requiredBundleName.trim().equals("org.eclipse.osgi")) {
                    needsNetbinox = true;
                    continue;
                }
                Matcher m2 = Pattern.compile(";([^:=]+):?=\"?([^;\"]+)\"?").matcher(m.group(2));
                boolean isOptional = false;
                while (m2.find()) {
                    if (m2.group(1).equals("resolution") && m2.group(2).equals("optional")) {
                        isOptional = true;
                        break;
                    }
                }
                if (isOptional) {
                    continue;
                }
                m2.reset();
                StringBuilder depSB = new StringBuilder();
                depSB.append(requiredBundleName); // dep CNB
                while (m2.find()) {
                    if (!m2.group(1).equals("bundle-version")) {
                        continue;
                    }
                    String val = m2.group(2);
                    if (val.matches("[0-9]+([.][0-9]+)*")) {
                        // non-range dep occasionally used in OSGi; no exact equivalent in NB
                        depSB.append(" > ").append(val);
                        continue;
                    }
                    Matcher m3 = Pattern.compile("\\[([0-9]+)((?:[.][0-9]+)*),([0-9.]+)\\)").matcher(val);
                    if (!m3.matches()) {
                        throw new RuntimeException("Could not parse version range: " + val + " in " + whereFrom);
                    }
                    int major = Integer.parseInt(m3.group(1));
                    String rest = m3.group(2);
                    try {
                        int max = Integer.parseInt(m3.group(3));
                        if (major > 99) {
                            depSB.append('/').append(major / 100);
                            if (max > major + 100) {
                                depSB.append('-').append(max / 100 - 1);
                            }
                        } else if (max > 100) {
                            depSB.append("/0-").append(max / 100 - 1);
                        }
                    } catch (NumberFormatException x) {
                        // never mind end boundary, does not match NB conventions
                    }
                    depSB.append(" > ").append(major % 100).append(rest);
                }
                if (b.indexOf(depSB.toString()) == -1) {
                    if (b.length() > 0) {
                        b.append(", ");
                    }
                    b.append(depSB);
                }
            }
            if (b.length() > 0) {
                manifest.setAttribute("OpenIDE-Module-Module-Dependencies", b.toString());
            }
            if (needsNetbinox) {
                manifest.setAttribute("OpenIDE-Module-Needs", "org.netbeans.Netbinox");
            }
        }
        String provides = computeExported(attr).toString();
        if (!provides.isEmpty()) {
            manifest.setAttribute("OpenIDE-Module-Provides", provides);
        }
        String recommends = computeImported(attr).toString();
        if (!recommends.isEmpty()) {
            manifest.setAttribute("OpenIDE-Module-Recommends", recommends);
        }
        String bundleCategory = loc(localized, attr, "Bundle-Category");
        if (bundleCategory != null) {
            manifest.setAttribute("OpenIDE-Module-Display-Category", bundleCategory);
        }
        String bundleDescription = loc(localized, attr, "Bundle-Description");
        if (bundleDescription != null) {
            manifest.setAttribute("OpenIDE-Module-Short-Description", bundleDescription);
        }
        // XXX anything else need to be set?
        return module;
    }

    private static String loc(Properties localized, Attributes attr, String key) {
        String val = attr.getValue(key);
        if (val == null) {
            return null;
        } else if (val.startsWith("%")) {
            return localized.getProperty(val.substring(1));
        } else {
            return val;
        }
    }

    private static StringTokenizer createTokenizer(String osgiDep) {
        for (;;) {
            int first = osgiDep.indexOf('"');
            if (first == -1) {
                break;
            }
            int second = osgiDep.indexOf('"', first + 1);
            if (second == -1) {
                break;
            }
            osgiDep = osgiDep.substring(0, first - 1) + osgiDep.substring(second + 1);
        }

        return new StringTokenizer(osgiDep, ",");
    }

    private static String beforeSemicolon(StringTokenizer tok) {
        String dep = tok.nextToken().trim();
        int semicolon = dep.indexOf(';');
        if (semicolon >= 0) {
            dep = dep.substring(0, semicolon);
        }
        return dep.replace('-', '_');
    }

    private static StringBuilder computeExported(Attributes attr) {
        StringBuilder sb = new StringBuilder();
        String pkgs = attr.getValue("Export-Package"); // NOI18N
        if (pkgs == null) {
            return sb;
        }
        StringTokenizer tok = createTokenizer(pkgs); // NOI18N
        String token;
        while (tok.hasMoreElements()) {
            token = beforeSemicolon(tok);
            if (sb.indexOf(token) == -1) {
                sb.append(sb.length() == 0 ? "" : ", ").append(token);
            }
        }
        return sb;
    }

    private static StringBuilder computeImported(Attributes attr) {
        StringBuilder sb = new StringBuilder();
        String pkgs = attr.getValue("Import-Package"); // NOI18N
        if (pkgs != null) {
            StringTokenizer tok = createTokenizer(pkgs); // NOI18N
            String token;
            while (tok.hasMoreElements()) {
                token = beforeSemicolon(tok);
                if (sb.indexOf(token) == -1) {
                    sb.append(sb.length() == 0 ? "" : ", ").append(token);
                }
            }
        }
        String recomm = attr.getValue("Require-Bundle"); // NOI18N
        if (recomm != null) {
            StringTokenizer tok = createTokenizer(recomm); // NOI18N
            String token;
            while (tok.hasMoreElements()) {
                token = beforeSemicolon(tok);
                if (sb.indexOf(token) == -1) {
                    sb.append(sb.length() == 0 ? "" : ", ").append("cnb.").append(token);
                }
            }
        }
        return sb;
    }

    static String extractCodeName(Attributes attr) {
        return extractCodeName(attr, null);
    }

    static String extractCodeName(Attributes attr, boolean[] osgi) {
        String codename = attr.getValue("OpenIDE-Module");
        if (codename != null) {
            return codename;
        }
        codename = attr.getValue("Bundle-SymbolicName");
        if (codename == null) {
            return null;
        }
        codename = codename.replace('-', '_');
        if (osgi != null) {
            osgi[0] = true;
        }
        int params = codename.indexOf(';');
        if (params >= 0) {
            return codename.substring(0, params);
        } else {
            return codename;
        }
    }
}
