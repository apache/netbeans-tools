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
import java.util.function.Function;
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
        int[] recognizedCount = new int[1];
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
                        Description lic = snipUnifiedLicenseOrNull(code, p);

                        if (lic != null) {
                            recognizedCount[0]++;
                            licenses.computeIfAbsent(lic.header, l -> new ArrayList<>()).add(path);
                            for (String par : lic.header.split("\n")) {
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
        System.err.println("files with recognized license headers: " + recognizedCount[0]);
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
    
    private static final Map<String, Function<String, Description>> extension2Convertor = new HashMap<>();
    
    static {
        enterExtensions(code -> snipLicense(code, "/\\*+", "\\*+/", "^[ \t]*\\**[ \t]*", CommentType.JAVA),
                        "javx", "c", "h", "cpp", "pass", "hint", "css", "java");
        enterExtensions(code -> snipLicense(code, "<!--+", "-+->", "^[ \t]*(-[ \t]*)?", CommentType.XML),
                        "html", "xsd", "xsl", "dtd", "settings", "wstcgrp", "wstcref",
                        "wsgrp", "xml");
        enterExtensions(code -> snipLicenseBundle(code, "#!.*"), "sh");
        enterExtensions(code -> snipLicenseBundle(code, null), "properties");
    }
    
    private static void enterExtensions(Function<String, Description> convertor, String... extensions) {
        for (String ext : extensions) {
            extension2Convertor.put(ext, convertor);
        }
    }

    public static Description snipUnifiedLicenseOrNull(String code, Path file) {
        String fn = file.getFileName().toString();
        String ext = fn.substring(fn.lastIndexOf('.') + 1);
        Function<String, Description> preferredConvertor = extension2Convertor.get(ext);
        Description desc = preferredConvertor != null ? preferredConvertor.apply(code) : null;
        
        if (desc == null) {
            for (Function<String, Description> convertor : extension2Convertor.values()) {
                desc = convertor.apply(code);
                
                if (desc != null) {
                    return desc;
                }
            }
        }
        
        return desc;
    }

    private static Description snipLicense(String code, String commentStart, String commentEnd, String normalizeLines, CommentType commentType) {
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
        return createUnifiedDescriptionOrNull(startM.start(), endM.end(), lic, commentType);
    }
    
    public static Description snipLicenseBundle(String code, String firstLinePattern) {
        StringBuilder res = new StringBuilder();
        boolean firstLine = true;
        int start = -1;
        int pos;
        int next = 0;
        int end = 0;
        String[] lines = code.split("\n");
        for (int i = 0; i < lines.length; i++) {
            String line = lines[i];
            pos = next;
            next += line.length() + ((i + 1) < lines.length ? 1 : 0);
            line = line.trim();
            if (firstLine && firstLinePattern != null && Pattern.compile(firstLinePattern).matcher(line).matches())
                continue;
            if (firstLine && line.trim().isEmpty())
                continue;
            if (line.startsWith("#")) {
                String part = line.substring(1).trim();
                if (firstLine && part.isEmpty())
                    continue;
                if (firstLine) {
                    start = pos;
                }
                firstLine = false;
                res.append(part);
                res.append("\n");
                if (!part.isEmpty()) {
                    end = next;
                }
            } else {
                return createUnifiedDescriptionOrNull(start, end, res.toString(), CommentType.PROPERTIES);
            }
        }
        return createUnifiedDescriptionOrNull(start, end, res.toString(), CommentType.PROPERTIES);
    }

    private static Description createUnifiedDescriptionOrNull(int start, int end, String lic, CommentType commentType) {
        if (lic != null && lic.contains("CDDL")) {
            if (start == (-1)) {
                System.err.println("!!!");
            }
            lic = YEARS_PATTERN.matcher(lic).replaceAll(Matcher.quoteReplacement("<YEARS>"));
            lic = lic.replaceAll("\\Q<p/>\\E", "\n"); //normalize <p/> to newlines
            lic = lic.replaceAll("([^\n])\n([^\n])", "$1 $2");
            lic = lic.replaceAll("[ \t]+", " ");
            lic = lic.replaceAll("\n+", "\n");
            lic = lic.replaceAll("^\n+", "");
            lic = lic.replaceAll("\n+$", "");
            return new Description(start, end, lic, commentType);
        }
        
        return null;
    }

    public static class Description {
        public final int start;
        public final int end;
        public final String header;
        public final CommentType commentType;

        public Description(int start, int end, String header, CommentType commentType) {
            this.start = start;
            this.end = end;
            this.header = header;
            this.commentType = commentType;
        }
        
    }
    
    public enum CommentType {
        JAVA,
        XML,
        PROPERTIES;
    }
}
