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

import java.io.File;
import java.text.Collator;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

/**
 *
 * @author avieiro
 */
public class LocalizedTutorialSection {
    
    public static final String URL_KEY = "url";
    public static final String TITLE_KEY = "title";
    private Language language;
    private ArrayList<File> files;
    private ArrayList<HashMap<String, String>> details;
    private String title;
    
    public LocalizedTutorialSection(Language language, String title) {
        this.language = language;
        this.files = new ArrayList<>();
        this.details = new ArrayList<>();
        this.title = title;
    }

    public void add(File file) {
        this.files.add(file);
    }

    public void addAll(List<File> files) {
        this.files.addAll(files);
    }

    public void sort(Map<File, String> fileTitles) {
        ArrayList<File> sortedFiles = new ArrayList<>(this.files);
        Collator collator = Collator.getInstance(language.locale);
        sortedFiles.sort((file1, file2) -> {
            String title1 = fileTitles.get(file1);
            String title2 = fileTitles.get(file2);
            title1 = title1 == null ? file1.getName() : title1;
            title2 = title2 == null ? file2.getName() : title2;
            return collator.compare(title1, title2);
        });
        this.files = sortedFiles;
        details.clear();
        for (File file : files) {
            HashMap<String, String> detail = new HashMap<String, String>();
            details.add(detail);
            detail.put(URL_KEY, file.getName().replaceAll(".asciidoc", ".html"));
            detail.put(TITLE_KEY, fileTitles.get(file));
        }
    }

    public Language getLanguage() {
        return language;
    }

    public ArrayList<File> getFiles() {
        return files;
    }

    public ArrayList<HashMap<String, String>> getDetails() {
        return details;
    }
    
    public String getTitle() {
        return title;
    }
}
