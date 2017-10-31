/*
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
package org.apache.netbeans.generatestatus;

import java.io.IOException;
import java.util.HashMap;
import java.util.Locale;
import java.util.Map;
import org.apache.maven.doxia.sink.Sink;
import org.apache.maven.reporting.AbstractMavenReport;
import org.apache.maven.reporting.MavenReportException;
import org.jsoup.Jsoup;
import org.jsoup.nodes.Document;
import org.jsoup.nodes.Element;
import org.jsoup.select.Elements;

/**
 *
 * @author skygo
 */
public abstract class AbstractReviewMavenReport extends AbstractMavenReport {

    Map<String, Element> links = new HashMap<>();

    protected void prepareReport(String pattern) throws IOException {
        Document jenkinsresults = Jsoup.connect("https://builds.apache.org/job/incubator-netbeans-linux/lastCompletedBuild/testReport/").get();
        Elements unittestfailurereport = jenkinsresults.select("a.model-link");
        for (Element link : unittestfailurereport) {
            if (link.text().contains(pattern)) {
                String[] split = link.text().split(". module ");
                String[] split1 = split[1].split(" has ");
                String modulename = split1[0];
                links.put(modulename, link);
            }
        }
        scanWikiAndGenerate();
    }

    protected void scanWikiAndGenerate() throws IOException {

        Document confluence = Jsoup.connect("https://cwiki.apache.org/confluence/display/NETBEANS/List+of+Modules+to+Review").get();

        int missingModules = links.size();
        Elements areas = confluence.select("h3");
        Sink sink = getSink();
        sink.sectionTitle2();
        sink.rawText("Need to find: " + missingModules + " entries");
        sink.sectionTitle2_();
        for (Element area : areas) {
            String text = area.text();
            if (text.contains("Area:")) {

                sink.sectionTitle3();
                sink.text(text.replaceAll("Area:", ""));
                sink.sectionTitle3_();
                sink.table();
                Elements allrows = area.nextElementSibling().select("tr");
                for (Element arow : allrows) {
                    String trim = arow.children().get(0).text().trim();
                    if (links.containsKey(trim)) {
                        missingModules--;
                        sink.tableRow();
                        sink.tableCell("50px");
                        sink.link("https://builds.apache.org/job/incubator-netbeans-linux/lastCompletedBuild/testReport/" + links.get(trim).attr("href"));
                        sink.rawText("Report");
                        sink.link_();
                        sink.tableCell_();
                        sink.tableCell("300px");
                        sink.rawText(arow.children().get(0).text().trim());
                        sink.tableCell_();
                        sink.tableCell("300px");
                        sink.rawText(arow.children().get(1).text().trim());
                        sink.tableCell_();
                        sink.tableCell();
                        sink.rawText(arow.children().get(2).text().trim());
                        sink.tableCell_();
                        /*sink.tableCell();
                        sink.rawText(arow.children().get(3).text().trim());
                        sink.tableCell_();
                         */
                        links.remove(trim);
                        sink.tableRow_();
                    }

                }
                sink.table_();
            }
        }
        sink.sectionTitle2();
        sink.rawText("Remains : " + missingModules + " entries");
        sink.sectionTitle2_();

    }

}
