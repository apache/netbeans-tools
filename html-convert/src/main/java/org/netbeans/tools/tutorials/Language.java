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
import java.util.HashMap;
import java.util.Locale;

/**
 *
 * @author avieiro
 */
public enum Language {
    UNKNOWN("", Locale.getDefault(), "English"), DEFAULT(".asciidoc", Locale.ENGLISH, "English"), PORTUGUESE("_pt_BR.asciidoc", new Locale("pt_BR"), "Português brasileiro"), CHINESE("_zh_CN.asciidoc", new Locale("zh_CN"), "中文"), JAPANESE("_ja.asciidoc", new Locale("ja"), "日本人"), RUSSIAN("_ru.asciidoc", new Locale("ru"), "русский"), CATALAN("_ca.asciidoc", new Locale("ca_ES"), "Català");
    public final String extension;
    public final Locale locale;
    public final String title;
    /* Array of non-default languages.*/
    public static final Language[] FOREIGN_LANGUAGES = {RUSSIAN, JAPANESE, CHINESE, PORTUGUESE, CATALAN};

    Language(String extension, Locale locale, String title) {
        this.extension = extension;
        this.locale = locale;
        this.title = title;
    }

    public static Language getLanguage(File file) {
        String name = file.getName().toLowerCase();
        for (Language language : FOREIGN_LANGUAGES) {
            if (name.endsWith(language.extension.toLowerCase())) {
                return language;
            }
        }
        return name.endsWith(DEFAULT.extension.toLowerCase()) ? DEFAULT : UNKNOWN;
    }

    public static HashMap<Language, File> getTranslations(File file) {
        File parentDirectory = file.getParentFile();
        String prefix = file.getName().replace(".asciidoc", "");
        HashMap<Language, File> translations = new HashMap<>();
        for (Language l : FOREIGN_LANGUAGES) {
            File translationFile = new File(parentDirectory, prefix + l.extension);
            if (translationFile.exists()) {
                translations.put(l, translationFile);
            }
        }
        return translations;
    }
    
}
