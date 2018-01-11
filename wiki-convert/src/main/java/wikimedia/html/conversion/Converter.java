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
package wikimedia.html.conversion;

import java.io.BufferedReader;
import java.io.BufferedWriter;
import java.io.File;
import java.io.FileFilter;
import java.io.FileInputStream;
import java.io.FileOutputStream;
import java.io.InputStreamReader;
import java.io.OutputStreamWriter;
import java.text.SimpleDateFormat;
import java.util.Date;
import java.util.logging.Level;
import java.util.logging.Logger;
import javax.xml.parsers.DocumentBuilderFactory;
import javax.xml.transform.Source;
import javax.xml.transform.Transformer;
import javax.xml.transform.TransformerConfigurationException;
import javax.xml.transform.TransformerFactory;
import javax.xml.transform.stream.StreamSource;
import org.eclipse.mylyn.wikitext.mediawiki.MediaWikiLanguage;
import org.eclipse.mylyn.wikitext.parser.MarkupParser;
import org.eclipse.mylyn.wikitext.parser.builder.HtmlDocumentBuilder;

/**
 * Converts mediawiki files in a source directory to html files in a destination
 * directory.
 *
 * @author Antonio Vieiro <vieiro@apache.org>
 */
public class Converter {

    private static enum FORMAT {
        html,
        asciidoc,
        github_markdown;
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
            + "    under the License.\n\n";

    private static final Logger LOG = Logger.getLogger(Converter.class.getName());
    private static final FileFilter MEDIAWIKI_FILES = (file) -> {
        return file.isFile() && file.getName().endsWith(".mediawiki");
    };
    private static Transformer transformer;
    private static DocumentBuilderFactory documentBuilderFactory;

    private static void convert(FORMAT format, File src, File dest) throws Exception {
        LOG.log(Level.INFO, "Converting from {0} to {1}", new Object[]{src.getAbsolutePath(), dest.getAbsolutePath()});
        File[] mediawiki_files = src.listFiles(MEDIAWIKI_FILES);
        if (mediawiki_files == null || mediawiki_files.length == 0) {
            LOG.log(Level.WARNING, "No *.mediawiki files found in {0}", src.getAbsolutePath());
        } else {
            MediaWikiLanguage mediaWikiLanguage = new MediaWikiLanguage();
            int fileCount = 0;
            for (File mediawiki : mediawiki_files) {
                switch (format) {
                    case html:
                        File html = new File(dest, mediawiki.getName().replaceAll("\\.mediawiki", ".html"));
                        convertMediaWikiToHTML(mediaWikiLanguage, mediawiki, html);
                        break;
                    case asciidoc:
                        File asciidoc = new File(dest, mediawiki.getName().replaceAll("\\.mediawiki", ".asciidoc"));
                        convertMediaWikiToAsciidoc(mediaWikiLanguage, mediawiki, asciidoc);
                        break;
                    case github_markdown:
                        File github_markdown_file = new File(dest, mediawiki.getName().replaceAll("\\.mediawiki", ".md"));
                        convertMediaWikiToGithubMarkdown(mediaWikiLanguage, mediawiki, github_markdown_file);
                        break;
                }
                fileCount++;
            }
            LOG.log(Level.INFO, "Converted {0} files.", fileCount);
        }
    }

    private static DocumentBuilderFactory getDocumentBuilderFactory() {
        if (documentBuilderFactory == null) {
            documentBuilderFactory = DocumentBuilderFactory.newInstance();
            documentBuilderFactory.setNamespaceAware(true);
            documentBuilderFactory.setValidating(false);
        }
        return documentBuilderFactory;
    }

    private Transformer getTransformer() throws TransformerConfigurationException {
        if (transformer == null) {
            TransformerFactory factory = TransformerFactory.newInstance();
            Source xslt = new StreamSource(Converter.class.getResourceAsStream("xhtml2confluence.xsl"));
            transformer = factory.newTransformer(xslt);
        }
        return transformer;
    }

    private static void convertMediaWikiToHTML(MediaWikiLanguage mediaWikiLanguage, File mediawikiFile, File htmlFile) throws Exception {
        // Convert *.wikimedia to strict xhtml
        try ( BufferedWriter output = new BufferedWriter(new OutputStreamWriter(new FileOutputStream(htmlFile), "utf-8"), 16 * 1024);  BufferedReader reader = new BufferedReader(new InputStreamReader(new FileInputStream(mediawikiFile), "utf-8"))) {
            LOG.log(Level.INFO, "  {0} > {1}", new Object[]{mediawikiFile.getName(), htmlFile.getName()});
            HtmlDocumentBuilder htmlBuilder = new HtmlDocumentBuilder(output);
            htmlBuilder.setEmitAsDocument(true);
            htmlBuilder.setXhtmlStrict(true);
            htmlBuilder.setEncoding("utf-8");
            htmlBuilder.setTitle(mediawikiFile.getName().replaceAll("\\.mediawiki", ""));
            MarkupParser parser = new MarkupParser(mediaWikiLanguage, htmlBuilder);
            parser.parse(reader);
        }
    }
    
    private static String asciidocHeader = null;
    
    private static String getAsciidocHeader() {
        if (asciidocHeader == null) {
            StringBuilder sb = new StringBuilder();
            String [] lines = APACHE_LICENSE_HEADER.split("\n");
            for (String line : lines) {
                sb.append("// ").append(line).append("\n");
            }
            sb.append("//\n\n");
            asciidocHeader = sb.toString();
        }
        return asciidocHeader;
    }
    
    private static ThreadLocal<SimpleDateFormat> sdf = null;
    
    private static final synchronized SimpleDateFormat getSimpleDateFormat() {
        if (sdf == null) {
            SimpleDateFormat d = new SimpleDateFormat("yyyy-MM-dd");
            sdf = new ThreadLocal<>();
            sdf.set(d);
        }
        return sdf.get();
    }

    private static void convertMediaWikiToAsciidoc(MediaWikiLanguage mediaWikiLanguage, File mediawikiFile, File asciidoc) throws Exception {
        // Convert *.wikimedia to asciidoc
        try ( BufferedWriter output = new BufferedWriter(new OutputStreamWriter(new FileOutputStream(asciidoc), "utf-8"), 16 * 1024);  BufferedReader reader = new BufferedReader(new InputStreamReader(new FileInputStream(mediawikiFile), "utf-8"))) {
            output.write(getAsciidocHeader());
            String title = mediawikiFile.getName().replace(".mediawiki", "");
            output.write("= " + title + "\n");
            output.write(":jbake-type: wiki\n");
            output.write(":jbake-tags: wiki, devfaq, needsreview\n");
            output.write(":jbake-status: published\n\n");
            CustomAsciiDocDocumentBuilder asciidocBuilder = new CustomAsciiDocDocumentBuilder(output);
            MarkupParser parser = new MarkupParser(mediaWikiLanguage, asciidocBuilder);
            parser.parse(reader);
            output.write("\n");
            output.write("*NOTE:* This document was automatically converted to the AsciiDoc format on " + getSimpleDateFormat().format(new Date()) + ", and needs to be reviewed.\n");
        }
    }

    private static void convertMediaWikiToGithubMarkdown(MediaWikiLanguage mediaWikiLanguage, File mediawikiFile, File markdownFile) throws Exception {
        // Convert *.wikimedia to asciidoc
        try ( BufferedWriter output = new BufferedWriter(new OutputStreamWriter(new FileOutputStream(markdownFile), "utf-8"), 16 * 1024);  BufferedReader reader = new BufferedReader(new InputStreamReader(new FileInputStream(mediawikiFile), "utf-8"))) {
            // LOG.log(Level.INFO, "  {0} > {1}", new Object[]{mediawikiFile.getName(), markdownFile.getName()});
            // JBAKE STUFF
            boolean jbake = false;
            if (jbake) {
                String wikiPageName = markdownFile.getName().replace(".md", "");
                output.write("title=" + wikiPageName + "\n");
                output.write("type=wiki\n");
                output.write("tags=wiki, devfaq\n");
                output.write("status=published\n");
                output.write("~~~~~~\n");
            }
            output.write("<!--\n");
            output.write(APACHE_LICENSE_HEADER);
            output.write("-->");
            GithubMarkdownDocumentBuilder markdownBuilder = new GithubMarkdownDocumentBuilder(output);
            MarkupParser parser = new MarkupParser(mediaWikiLanguage, markdownBuilder);
            parser.parse(reader);
        }
    }

    private static final void checkDirectory(File dir) throws Exception {
        String error = null;
        if (!dir.exists()) {
            error = dir.getAbsolutePath() + " does not exist.";
        }
        if (error == null && !dir.isDirectory()) {
            error = dir.getAbsolutePath() + " is not a directory.";
        }
        if (error == null && !dir.canRead()) {
            error = dir.getAbsolutePath() + " is not readable.";
        }
        if (error != null) {
            throw new IllegalArgumentException(error);
        }
    }

    private static void usage() {
        System.err.println("Use: java " + Converter.class.getName() + " format inputDirectory outputDirectory");
        System.err.println("  where format is one of 'html', 'asciidoc' or 'github_markdown'");
        System.exit(1);
    }

    public static void main(String[] args) throws Exception {
        if (args.length != 3) {
            usage();
        }

        FORMAT format = FORMAT.valueOf(args[0].trim());
        if (format == null) {
            usage();
        }

        File src = new File(args[1]);
        checkDirectory(src);

        File dest = new File(args[2]);
        if (!dest.exists()) {
            if (!dest.mkdirs()) {
                throw new IllegalStateException("Cannot create directory " + dest.getAbsolutePath());
            }
        }
        checkDirectory(dest);
        if (!dest.canWrite()) {
            throw new IllegalStateException("Cannot write to " + dest.getAbsolutePath());
        }

        convert(format, src, dest);
    }

}
