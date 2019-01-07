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

import com.github.mustachejava.DefaultMustacheFactory;
import com.github.mustachejava.Mustache;
import com.github.mustachejava.MustacheFactory;
import com.github.mustachejava.functions.BundleFunctions;
import java.io.*;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.StandardCopyOption;
import java.util.ArrayList;
import java.util.Arrays;
import java.util.HashMap;
import java.util.List;
import java.util.Map;
import java.util.ResourceBundle;
import java.util.logging.Level;
import java.util.logging.Logger;
import java.util.regex.Matcher;
import java.util.regex.Pattern;
import java.util.stream.Collectors;

public class AsciidocPostProcessor {

    private static final Logger LOG = Logger.getLogger(AsciidocPostProcessor.class.getName());

    private static enum ContentSectionState {
        BEFORE_CONTENT_SECTION,
        INSIDE_CONTENT_SECTION,
        AFTER_CONTENT_SECTION
    };

    private static Pattern TITLE_PATTERN = Pattern.compile("^= (.*)$");

    private static Pattern EN_CONTENTS_PATTERN = Pattern.compile("^.*Contents.*");

    private static boolean isContentsHeader(String line) {
        return line.contains("*Content")
                || line.contains("目录")
                || line.contains("目次")
                || line.contains("Conteúdo")
                || line.contains("Содержание");
    }

    /**
     * Scans a generated AsciiDoc file and remove the "*Contents*" section with
     * all links in it. Because the contents section is being replaced by a
     * table of contents 'toc'.
     *
     * @param file
     * @param titles
     * @throws IOException
     */
    private static void cleanUpAndGetTitle(File file, HashMap<File, String> titles) throws IOException {
        File temporaryFile = new File(file.getParentFile(), file.getName() + ".tmp");
        ContentSectionState state = ContentSectionState.BEFORE_CONTENT_SECTION;
        String title = null;

        try (BufferedReader input = new BufferedReader(new InputStreamReader(new FileInputStream(file), StandardCharsets.UTF_8));
                PrintWriter output = new PrintWriter(new OutputStreamWriter(new FileOutputStream(temporaryFile), StandardCharsets.UTF_8))) {

            do {
                String line = input.readLine();
                if (line == null) {
                    break;
                }
                if (title == null) {
                    Matcher m = TITLE_PATTERN.matcher(line);
                    if (m.matches()) {
                        title = m.group(1);
                    }
                }
                switch (state) {
                    case BEFORE_CONTENT_SECTION:
                        if (isContentsHeader(line)) {
                            state = ContentSectionState.INSIDE_CONTENT_SECTION;
                            break;
                        }
                        output.println(line);
                        break;
                    case INSIDE_CONTENT_SECTION:
                        if (line.startsWith("* <")
                                || line.startsWith("* link:")) {
                            // Ignore bullet lists with cross references or external links
                        } else {
                            output.println(line);
                        }
                        if (line.startsWith("=")) {
                            state = ContentSectionState.AFTER_CONTENT_SECTION;
                        }
                        break;
                    case AFTER_CONTENT_SECTION:
                        output.println(line);
                        break;
                }
            } while (true);

        }

        Files.move(temporaryFile.toPath(), file.toPath(), StandardCopyOption.REPLACE_EXISTING, StandardCopyOption.ATOMIC_MOVE);

        if (title != null) {
            titles.put(file, title);
        }
    }

    static Map<File, String> removeContentSetcion(File dest) throws Exception {
        LOG.log(Level.INFO, "Removing \"Content\" section of files...");

        // Retrieve the list of asciidoc files
        List<File> asciidocFiles = Files.find(dest.toPath(), 999,
                (p, bfa) -> bfa.isRegularFile()).map(Path::toFile).filter((f) -> f.getName().endsWith(".asciidoc")).collect(Collectors.toList());

        // Remove the 'Contents' section and fetch titles
        HashMap<File, String> titles = new HashMap<>();
        for (File file : asciidocFiles) {
            cleanUpAndGetTitle(file, titles);
        }

        return titles;
    }

    /**
     * Generates index.asciidoc, index_ja.asciidoc, index_pt_BR.asciidoc, etc.,
     * in the nested directories, each index file has a list of links to
     * asciidoc documents in the directory. Asciidoc documents are sorted by
     * title, attending to the proper Locale collation rules.
     *
     * @param dest The destination directory.
     * @param titles
     * @throws IOException
     */
    static void generateIndexes(File dest, Map<File, String> titles) throws IOException {
        LOG.info("Generating index.asciidoc (and translations) on all directories...");
        /*
        Compute the list of directories under 'dest'
         */
        List<File> directories = Files.find(dest.toPath(), 999,
                (p, bfa) -> bfa.isDirectory()
        ).map((p) -> p.toFile()).collect(Collectors.toList());

        /*
        A filter that selects documents in english (i.e., without _ja, _pt_BR, etc. suffixes).
         */
        FileFilter englishTutorialsFilter = (f) -> f.isFile() && Language.getLanguage(f) == Language.DEFAULT;

        MustacheFactory factory = new DefaultMustacheFactory();
        Mustache indexMustache = factory.compile("org/netbeans/tools/tutorials/index-template.mustache");
        Mustache sectionMustache = factory.compile("org/netbeans/tools/tutorials/section-template.mustache");

        /*
        Iterate over all nexted directories...
         */
        for (File directory : directories) {
            if ("images".equals(directory.getName())) {
                continue;
            }

            HashMap<Language, List<File>> filesByLanguage = new HashMap<>();
            /*
            Compute the files in english
             */
            File[] tutorialsEnglish = directory.listFiles(englishTutorialsFilter);
            for (File english : tutorialsEnglish) {

                List<File> englishFiles = filesByLanguage.get(Language.DEFAULT);
                if (englishFiles == null) {
                    englishFiles = new ArrayList<File>();
                    filesByLanguage.put(Language.DEFAULT, englishFiles);
                }
                englishFiles.add(english);
                /*
                And retrieve the list of translations of the english file.
                 */
                HashMap<Language, File> translations = Language.getTranslations(english);
                for (Map.Entry<Language, File> translation : translations.entrySet()) {
                    List<File> languageFiles = filesByLanguage.get(translation.getKey());
                    if (languageFiles == null) {
                        languageFiles = new ArrayList<>();
                        filesByLanguage.put(translation.getKey(), languageFiles);
                    }
                    languageFiles.add(translation.getValue());
                }
            }

            for (Map.Entry<Language, List<File>> entry : filesByLanguage.entrySet()) {
                Language language = entry.getKey();
                if (language == Language.UNKNOWN) {
                    continue;
                }
                ResourceBundle bundle = ResourceBundle.getBundle("org.netbeans.tools.tutorials.TutorialsBundle", language.locale);
                String directoryTitle = bundle.getString( directory.getName() + ".title");
                if (directoryTitle == null) {
                    throw new IllegalArgumentException("Please add a title for directory '" + directory.getName() + "' in locale " + language.locale);
                }
                LocalizedTutorialSection section = new LocalizedTutorialSection(language, directoryTitle);
                section.addAll(entry.getValue());
                section.sort(titles);

                // Generate the index
                String name = "index" + language.extension;
                File output = new File(directory, name);
                try (Writer indexOutput = new OutputStreamWriter(new FileOutputStream(output), StandardCharsets.UTF_8)) {
                    indexMustache.execute(indexOutput, section);
                }
                
                // Also generate section.asciidoc (section_ja.asciidoc, etc.)
                // This will be in a sidebar for all tutorials in this section
                String sectionSidebarName = "section" + language.extension;
                File sectionSidebarFile = new File(directory, sectionSidebarName);
                try (Writer indexOutput = new OutputStreamWriter(new FileOutputStream(sectionSidebarFile), StandardCharsets.UTF_8)) {
                    sectionMustache.execute(indexOutput, section);
                }
                
            }


        }
    }

}
