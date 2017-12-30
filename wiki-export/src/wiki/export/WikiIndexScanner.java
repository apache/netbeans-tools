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
import java.io.FileReader;
import java.io.FileWriter;
import java.io.PrintWriter;
import java.util.regex.Matcher;
import java.util.regex.Pattern;
import org.apache.tools.ant.BuildException;
import org.apache.tools.ant.Task;

/**
 * Given an already downloaded wiki index in wikitext format, generates a
 * properties file where keys are wiki names.
 *
 * @author Antonio Vieiro <vieiro@apache.org>
 */
public class WikiIndexScanner extends Task {

    /**
     * The wiki index page to download from.
     */
    private String wikiIndexPage;

    public void setWikiIndexPage(String wikiIndexPage) {
        this.wikiIndexPage = wikiIndexPage;
    }

    private File dest;

    public void setDest(File dest) {
        this.dest = dest;
    }

    private static final Pattern SECTION_PATTERN = Pattern.compile(".*===([^=]+)===.*");
    private static final Pattern WIKI_ENTRY_PATTERN = Pattern.compile(".*\\[\\[([^|]+)\\|([^\\]]+)\\]\\].*");

    @Override
    public void execute() throws BuildException {

        try {

            PrintWriter output = null;
            if (dest == null) {
                output = new PrintWriter(System.out);
            } else {
                output = new PrintWriter(new FileWriter(dest));
            }
            String section = "NetBeans Developer FAQ";
            BufferedReader reader = new BufferedReader(new FileReader(new File(wikiIndexPage)));
            do {
                String line = reader.readLine();
                if (line == null) {
                    break;
                }
                if (line.indexOf("==") != -1) {
                    Matcher sectionMatcher = SECTION_PATTERN.matcher(line);
                    if (sectionMatcher.matches()) {
                        section = sectionMatcher.group(1);
                    }
                }
                Matcher matcher = WIKI_ENTRY_PATTERN.matcher(line);
                if (matcher.matches()) {
                    String wikiLink = matcher.group(1).trim();
                    if (wikiLink.indexOf('#') != -1) {
                        continue;
                    }
                    String title = matcher.group(2).trim();
                    output.println(wikiLink + "=" + title);
                    output.println(wikiLink + ".section=" + section);
                }
            } while (true);
            reader.close();
            output.close();
        } catch (Exception ex) {
            throw new BuildException(ex);
        }
    }
}
