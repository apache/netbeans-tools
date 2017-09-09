/**
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
package convert;

import java.io.IOException;
import java.io.Writer;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.Paths;
import java.util.ArrayList;
import java.util.Arrays;
import java.util.Collection;
import java.util.Collections;
import java.util.HashMap;
import java.util.HashSet;
import java.util.List;
import java.util.Map;
import java.util.Set;
import java.util.regex.Matcher;
import java.util.regex.Pattern;
import java.util.stream.Collectors;

public class CategorizeLicenses {

    public static void main(String[] args) throws IOException {
        if (args.length != 2) {
            System.err.println("Use: CategorizeLicenses <source-directory> <target-directory>");
            return ;
        }
        Path root = Paths.get(args[0]);
        Map<String, List<String>> licenses = new HashMap<>();
        Map<String, List<String>> paragraphs = new HashMap<>();
        Set<String> noCDDL = new HashSet<>();
        Set<String> cddlNotRecognized = new HashSet<>();
        Files.find(root, Integer.MAX_VALUE, (p, attr) -> attr.isRegularFile())
             .forEach(p -> {
                try {
                    String path = root.relativize(p).toString();
                    String code = new String(Files.readAllBytes(p));

                    if (code.contains("CDDL")) {
                        String lic = snipLicense(code, p);

                        if (lic != null && lic.contains("CDDL")) {
                            lic = YEARS_PATTERN.matcher(lic).replaceAll(Matcher.quoteReplacement("<YEARS>"));
                            lic = lic.replaceAll("([^\n])\n([^\n])", "$1 $2");
                            lic = lic.replaceAll("[ \t]+", " ");
                            licenses.computeIfAbsent(lic, l -> new ArrayList<>()).add(path);
                            for (String par : lic.split("\n")) {
                                paragraphs.computeIfAbsent(par, l -> new ArrayList<>()).add(path);
                            }
                            return ;
                        }
                    
                        cddlNotRecognized.add(path);
                        return ;
                    }
                    noCDDL.add(path);
                } catch (IOException ex) {
                    ex.printStackTrace();
                }
             });
        
        Path target = Paths.get(args[1]);

        int i = 0;
        for (Map.Entry<String, List<String>> e : licenses.entrySet()) {
            try (Writer w = Files.newBufferedWriter(target.resolve("lic" + i++))) {
                w.write(e.getKey());
                w.write("\n\n");
                for (String file : e.getValue()) {
                    w.write(file);
                    w.write("\n");
                }
            }
        }
        System.err.println("licenses count: " + licenses.size());
        System.err.println("paragraphs count: " + paragraphs.size());
        
        System.err.println("cddl, unrecognized file: " + cddlNotRecognized.size());
        System.err.println("no cddl license: " + noCDDL.size());

        dump(licenses, target, "lic");
        dump(paragraphs, target, "par");
        dump(Collections.singletonMap("Files which contain string CDDL, but their comment structure is not (yet) recognized.", cddlNotRecognized), target, "have-cddl-not-recognized-filetype");
        dump(Collections.singletonMap("Files which do not contain string CDDL", noCDDL), target, "do-not-have-cddl");
    }
        private static final Pattern YEARS_PATTERN = Pattern.compile("[12][019][0-9][0-9]([ \t]*[-,/][ \t]*[12][019][0-9][0-9])?");

    private static void dump(Map<String, ? extends Collection<String>> cat, Path target, String name) throws IOException {
        int i = 0;
        for (Map.Entry<String, ? extends Collection<String>> e : cat.entrySet()) {
            try (Writer w = Files.newBufferedWriter(target.resolve(name + i++))) {
                w.write(e.getKey());
                w.write("\n\n");
                w.write("files:\n");
                e.getValue().stream().sorted().forEach(file -> {
                    try {
                        w.write(file);
                        w.write("\n");
                    } catch (IOException ex) {
                        throw new IllegalStateException(ex);
                    }
                });
            }
        }
    }
    private static String snipLicense(String code, Path file) {
        String fn = file.getFileName().toString();
        switch (fn.substring(fn.lastIndexOf('.') + 1)) {
            case "javx": case "c": case "h": case "cpp":
            case "java": return snipLicense(code, "/\\*+", "\\*+/", "^[ \t]*\\**[ \t]*");
            case "html": case "xsd": case "xsl": case "dtd":
            case "settings": case "wstcgrp": case "wstcref":
            case "wsgrp": 
            case "xml": return snipLicense(code, "<!--+", "-+->", "^[ \t]*");
            case "sh": return snipLicenseBundle(code, "#!.*");
            case "properties": return snipLicenseBundle(code, null);
        }
        
        return null;
    }

    private static String snipLicense(String code, String commentStart, String commentEnd, String normalizeLines) {
        Matcher startM = Pattern.compile(commentStart).matcher(code);
        if (!startM.find())
            return null;
        Matcher endM = Pattern.compile(commentEnd).matcher(code);
        if (!endM.find(startM.end()))
            return null;
        String lic = code.substring(startM.end(), endM.start());
        if (normalizeLines != null) {
            lic = Arrays.stream(lic.split("\n"))
                        .map(l -> l.replaceAll(normalizeLines, ""))
                        .collect(Collectors.joining("\n"));
        }
        return lic;
    }
    
    private static String snipLicenseBundle(String code, String firstLinePattern) {
        StringBuilder res = new StringBuilder();
        boolean firstLine = true;
        for (String line : code.split("\n")) {
            line = line.trim();
            if (firstLine && firstLinePattern != null && Pattern.compile(firstLinePattern).matcher(line).matches())
                continue;
            firstLine = false;
            if (line.startsWith("#")) {
                res.append(line.substring(1).trim());
                res.append("\n");
            } else {
                return res.toString();
            }
        }
        return res.toString();
    }
    
}
