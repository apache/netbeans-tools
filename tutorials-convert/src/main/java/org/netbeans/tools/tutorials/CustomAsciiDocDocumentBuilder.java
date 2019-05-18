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

import com.google.common.base.Joiner;
import static com.google.common.base.Preconditions.checkNotNull;
import com.google.common.base.Strings;

import java.io.BufferedWriter;
import java.io.File;
import java.io.IOException;
import java.io.StringWriter;
import java.io.Writer;
import java.nio.file.Files;
import java.util.logging.Level;
import java.util.logging.Logger;
import java.util.regex.Pattern;

import org.eclipse.mylyn.wikitext.asciidoc.internal.AsciiDocDocumentBuilder;
import org.eclipse.mylyn.wikitext.parser.Attributes;
import org.eclipse.mylyn.wikitext.parser.DocumentBuilder;
import org.eclipse.mylyn.wikitext.parser.ImageAttributes;
import org.eclipse.mylyn.wikitext.parser.LinkAttributes;
import org.eclipse.mylyn.wikitext.parser.builder.AbstractMarkupDocumentBuilder;

/**
 * An AsciiDocDocumentBuilder.
 *
 * @author Antonio Vieiro <vieiro@apache.org>
 */
public class CustomAsciiDocDocumentBuilder extends AsciiDocDocumentBuilder {

    /**
     * Base class for asciidoc delimited blocks.
     */
    class AsciiDocContentBlock extends AbstractMarkupDocumentBuilder.NewlineDelimitedBlock {

        protected String prefix;
        protected String suffix;

        AsciiDocContentBlock(DocumentBuilder.BlockType blockType, String prefix, String suffix) {
            this(blockType, prefix, suffix, 1, 1);
        }

        AsciiDocContentBlock(DocumentBuilder.BlockType blockType, String prefix, String suffix, int leadingNewlines, int trailingNewlines) {
            super(blockType, leadingNewlines, trailingNewlines);
            this.prefix = prefix;
            this.suffix = suffix;
        }

        AsciiDocContentBlock(String prefix, String suffix, int leadingNewlines, int trailingNewlines) {
            this(null, prefix, suffix, leadingNewlines, trailingNewlines);
        }

        @Override
        public void write(int c) throws IOException {
            CustomAsciiDocDocumentBuilder.this.emitContent(c);
        }

        @Override
        public void write(String s) throws IOException {
            CustomAsciiDocDocumentBuilder.this.emitContent(s);
        }

        @Override
        public void open() throws IOException {
            super.open();
            pushWriter(new StringWriter());
        }

        @Override
        public void close() throws IOException {
            Writer thisContent = popWriter();
            String content = thisContent.toString();
            if (content.length() > 0) {
                emitContent(content);
            }
            super.close();
        }

        protected void emitContent(final String content) throws IOException {
            CustomAsciiDocDocumentBuilder.this.emitContent(prefix);
            String trimmedContent = content.replaceAll("\\*Note:[ ]\\*\\*", "NOTE: ");
            // String trimmedContent = content.replaceAll("\\*[Nn][Oo][Tt][Ee]:[ ]*\\*", "NOTE: ");
            CustomAsciiDocDocumentBuilder.this.emitContent(trimmedContent);
            CustomAsciiDocDocumentBuilder.this.emitContent(suffix);
        }

    }

    /**
     * Handles links. Links with images are handled properly, copying images
     * from the image directory close to the document.
     */
    private class LinkBlock extends AsciiDocContentBlock {

        final LinkAttributes attributes;

        LinkBlock(LinkAttributes attributes) {
            super("", "", 0, 0);
            this.attributes = attributes;
        }

        @Override
        protected void emitContent(String content) throws IOException {
            String href = attributes.getHref();
            if (href == null) {
                if (attributes.getId() != null) {
                    super.emitContent("[[" + attributes.getId() + "]]\n");
                    return;
                }
                LOG.log(Level.WARNING, "Empty href: {0}", href);
            }
            href = href == null ? "" : href;
            href = copyImageIfRequired(href, false);

            if (href.startsWith("http")) {
                externalLinks.addExternalLink(href, CustomAsciiDocDocumentBuilder.this.relativePathToTutorialFile);
            }

            if (content.contains("image:")) {
                // Hande links with images properly, using a image with a "link" attribute
                // content is something like "image:images/whatever-small.png[]" (small image)
                // href is something like "images/whatever.png" (larger image)
                // This must be transformed (https://stackoverflow.com/questions/34299474/using-an-image-as-a-link-in-asciidoc)
                // to
                // image:whatever-small.png[link="whatever.png"]
                String smallPart = content.substring(6);
                int i = smallPart.indexOf('[');
                smallPart = i == -1 ? smallPart : smallPart.substring(0, i);
                StringBuilder sb = new StringBuilder();
                sb.append("\n[.feature]\n");
                sb.append("--\n");
                sb.append("image");
                sb.append(smallPart);
                sb.append("[role=\"left\", link=\"").append(href).append("\"]\n");
                sb.append("--\n");
                CustomAsciiDocDocumentBuilder.this.emitContent(sb.toString());
            } else {
                // link::http://url.com[label]
                CustomAsciiDocDocumentBuilder.this.emitContent("link:"); //$NON-NLS-1$
                CustomAsciiDocDocumentBuilder.this.emitContent(href);
                CustomAsciiDocDocumentBuilder.this.emitContent("[+");
                if (content != null) {
                    CustomAsciiDocDocumentBuilder.this.emitContent(content);
                }
                CustomAsciiDocDocumentBuilder.this.emitContent("+]");
            }
        }

    }

    /**
     * A header-1 block.
     */
    class AsciiDocMainHeaderBlock extends AsciiDocContentBlock {

        public AsciiDocMainHeaderBlock() {
            super("", "", 2, 2);
        }

        @Override
        protected void emitContent(String content) throws IOException {
            String trimmedContent = content.replaceAll("\n", " ");
            super.emitContent("= " + trimmedContent + "\n");
            super.emitContent(":jbake-type: platform-tutorial\n");
            super.emitContent(":jbake-tags: tutorials \n");
            super.emitContent(":jbake-status: published\n");
            super.emitContent(":syntax: true\n");
            super.emitContent(":source-highlighter: pygments\n");
            super.emitContent(":toc: left\n");
            super.emitContent(":toc-title:\n");
            super.emitContent(":icons: font\n");
            super.emitContent(":experimental:\n");
            super.emitContent(":description: " + trimmedContent + " - Apache NetBeans\n");
            super.emitContent(":keywords: Apache NetBeans Platform, Platform Tutorials, " + trimmedContent + "");
            super.emitContent("\n");
        }

    }

    private static final String headerPrefix(int level) {
        StringBuilder sb = new StringBuilder();
        for (int i = 0; i < level; i++) {
            sb.append("=");
        }
        sb.append(" ");
        return sb.toString();
    }

    class NumberedListItemBlock extends AsciiDocContentBlock {

        private int count = 0;
        
        private NumberedListItemBlock(String prefix) {
            super(BlockType.LIST_ITEM, prefix, "", 1, 1);
        }

        @Override
        public void open() throws IOException {
            super.open();
            if (getPreviousBlock() instanceof AsciiDocListBlock) {
                AsciiDocListBlock list = (AsciiDocListBlock) getPreviousBlock();
                list.addListItem(this);
                count = list.getCount();
            }
        }

        @Override
        protected void emitContent(String content) throws IOException {
            if (getPreviousBlock().getBlockType() == BlockType.NUMERIC_LIST) {
                prefix = String.format("%n[start=%d]%n%d. ", count, count);
            }
            super.emitContent(content);
        }

    }

    class AsciiDocListBlock extends AsciiDocContentBlock {

        private int count = 0;

        private AsciiDocListBlock(BlockType type, int leadingNewLines) {
            super(type, "", "", leadingNewLines, 1);
        }

        @Override
        protected void emitContent(String content) throws IOException {
            super.emitContent(prefix);
            super.emitContent(content);
            if (!content.endsWith("\n\n")) {
                super.emitContent(suffix);
            }
        }

        protected void addListItem(NumberedListItemBlock item) {
            count++;
        }

        protected int getCount() {
            return count;
        }
    }

    class AsciiDocHeaderBlock extends AsciiDocContentBlock {

        private Attributes attributes;
        private int level;

        public AsciiDocHeaderBlock(int level, Attributes attributes) {
            super("", "", 2, 2);
            this.attributes = attributes;
            this.level = level;
        }

        @Override
        protected void emitContent(String content) throws IOException {
            super.emitContent("\n");
            if (attributes != null && attributes.getId() != null) {
                super.emitContent("[[" + attributes.getId() + "]]\n");
            }
            StringBuilder sb = new StringBuilder();
            for (int i = 0; i < level; i++) {
                sb.append("=");
            }
            sb.append(" ");
            sb.append(content.replaceAll("\n", " "));
            sb.append("\n\n");
            super.emitContent(sb.toString());
        }
    }

    /**
     * An inline code block.
     */
    class AsciiDocInlinePreformatted extends AsciiDocContentBlock {

        String language;

        AsciiDocInlinePreformatted(String language, CustomAsciiDocDocumentBuilder documentBuilder) {
            super(BlockType.CODE, " ``", "`` ", 0, 0);
            this.language = language;
        }

    }

    private static final Pattern RUBY_PATTERN = Pattern.compile("^require '.*", Pattern.MULTILINE);
    private static final Pattern C_PATTERN = Pattern.compile("^#include.*", Pattern.MULTILINE);
    private static final Pattern SHELL_PATTERN = Pattern.compile("^\\$ ", Pattern.MULTILINE);

    /**
     * Generates a
     * <pre> block.
     */
    class AsciiDocPreformatted extends AsciiDocContentBlock {

        String language;

        AsciiDocPreformatted(String language) {
            super(BlockType.PREFORMATTED, "", "", 1, 1);
            this.language = language;
        }

        @Override
        protected void emitContent(String content) throws IOException {
            if (language == null) {
                // Use "java" as a default language for tutorials
                language = "java";
                if (content.contains("<?xml") || content.contains("</")) {
                    language = "xml";
                }
                if (content.contains("<div") || content.contains("<p>")) {
                    language = "html";
                }
                if (C_PATTERN.matcher(content).find()) {
                    language = "c";
                }
                if (RUBY_PATTERN.matcher(content).find()) {
                    language = "ruby";
                }
                if (SHELL_PATTERN.matcher(content).find()) {
                    language = "shell";
                }
                if (content.contains("<?php")) {
                    language = "php";
                }
                if (content.contains("$.") || content.contains("function (")) {
                    language = "javascript";
                }
            }
            // [label](http://url.com) or
            // [label](http://url.com "title")
            CustomAsciiDocDocumentBuilder.this.emitContent("\n[source," + language + "]\n");
            CustomAsciiDocDocumentBuilder.this.emitContent("----\n");
            CustomAsciiDocDocumentBuilder.this.emitContent(content.startsWith("\n") ? content : "\n" + content);
            CustomAsciiDocDocumentBuilder.this.emitContent("\n----\n\n");
        }
    }

    private static Logger LOG = Logger.getLogger(CustomAsciiDocDocumentBuilder.class.getName());

    private File topDirectory;
    private File imageDirectory;
    private File outputDirectory;
    private File imageDestDirectory;
    private ExternalLinksMap externalLinks;
    private Language language;
    private String relativePathToTutorialsRoot;
    private File outputFile;
    private String relativePathToTutorialFile;

    public CustomAsciiDocDocumentBuilder(File topDirectory, File imageDirectory, File outputFile, BufferedWriter output, ExternalLinksMap externalLinks) {
        super(output);
        this.topDirectory = topDirectory;
        this.imageDirectory = imageDirectory;
        this.outputFile = outputFile;
        this.outputDirectory = outputFile.getParentFile();
        this.language = Language.getLanguage(outputFile);
        imageDestDirectory = new File(outputDirectory, "images");
        imageDestDirectory.mkdirs();
        this.externalLinks = externalLinks;
        relativePathToTutorialsRoot = outputDirectory.toURI().relativize(topDirectory.toURI()).getPath();
        relativePathToTutorialFile = topDirectory.toURI().relativize(outputFile.toURI()).getPath();

    }

    /**
     * Responsible for handling headers.
     *
     * @param level
     * @param attributes
     * @return
     */
    @Override
    protected Block computeHeading(int level, Attributes attributes) {
        if (level == 1) {
            return new AsciiDocMainHeaderBlock();
        }
        //return super.computeHeading(level, attributes);
        return new AsciiDocHeaderBlock(level, attributes);
    }

    @Override
    protected Block computeSpan(SpanType type, Attributes attributes) {
        switch (type) {
            case MARK:
                throw new IllegalStateException("Mark");
            case MONOSPACE:
                return new AsciiDocInlinePreformatted(null, this);
            case LINK:
                if (attributes instanceof LinkAttributes) {
                    LinkAttributes linkAttributes = (LinkAttributes) attributes;
                    if (linkAttributes.getHref() != null) {
                        if (linkAttributes.getHref().startsWith("#")) {
                            return new AsciiDocContentBlock("<<" + linkAttributes.getHref().substring(1) + ",", ">>", 0, 0);
                            /* This is an internal link */
                        }
                        return new LinkBlock((LinkAttributes) attributes);
                    } else if (linkAttributes.getId() != null) {
                        return new AsciiDocContentBlock("[[", "]]", 0, 1);
                    }
                }
                return new AsciiDocContentBlock("", "", 0, 0);
            case DELETED:
                return new AsciiDocContentBlock("[.line-through]#", "#", 0, 0);
            case UNDERLINED:
                return new AsciiDocContentBlock("[.underline]#", "#", 0, 0);
            default:
                return super.computeSpan(type, attributes);
        }
    }

    @Override
    public void link(Attributes attributes, String hrefOrHashName, String text) {
        super.link(attributes, hrefOrHashName, text);
    }

    @Override
    public void entityReference(String entity) {
        super.entityReference(entity);
    }

    @Override
    protected Block
            computeBlock(BlockType type, Attributes attributes) {
        switch (type) {
            case CODE:
            case PREFORMATTED:
                String language = null;

                if (attributes.getCssClass() != null) {
                    if (attributes.getCssClass().equals("source-java")) {
                        language = "java";

                    } else if (attributes.getCssClass().equals("source-xml")) {
                        language = "xml";

                    } else if (attributes.getCssClass().equals("source-properties")) {
                        language = "yaml";

                    }
                }
                return new AsciiDocPreformatted(language);
            case NUMERIC_LIST:
                if (currentBlock != null) {
                    BlockType currentBlockType = currentBlock.getBlockType();
                    if (currentBlockType == BlockType.LIST_ITEM || currentBlockType == BlockType.DEFINITION_ITEM
                            || currentBlockType == BlockType.DEFINITION_TERM) {
                        return new AsciiDocListBlock(type, 1);
                    }
                }
                return new AsciiDocListBlock(type, 2);
            case LIST_ITEM:
                if (computeCurrentListType() == BlockType.NUMERIC_LIST) {
                    return new NumberedListItemBlock(""); //$NON-NLS-1$
                }
                return super.computeBlock(type, attributes);
            default:
                return super.computeBlock(type, attributes);
        }
    }

    @Override
    public void image(Attributes attributes, String url) {
        url = copyImageIfRequired(url, true);

        assertOpenBlock();
        try {
            currentBlock.write(computeImage(attributes, url, false));
        } catch (IOException e) {
            throw new RuntimeException(e);
        }
    }

    private String computeImage(Attributes attributes, String url, boolean inline) {
        // image:/path/to/img.jpg[] or
        // image:path/to/img.jpg[alt text]
        String altText = null;
        String title = null;
        if (attributes instanceof ImageAttributes) {
            ImageAttributes imageAttr = (ImageAttributes) attributes;
            altText = imageAttr.getAlt();
        }
        if (!Strings.isNullOrEmpty(attributes.getTitle())) {
            title = "title=\"" + attributes.getTitle().replaceAll("\n", " ").replaceAll("\r", " ") + '"'; //$NON-NLS-1$
        }

        StringBuilder sb = new StringBuilder();
        sb.append(inline ? "image:" : "image::"); //$NON-NLS-1$
        sb.append(Strings.nullToEmpty(url));
        sb.append("["); //$NON-NLS-1$
        sb.append(Joiner.on(", ").skipNulls().join(altText, title)); //$NON-NLS-1$
        sb.append("]"); //$NON-NLS-1$
        return sb.toString();
    }

    private String copyImageIfRequired(String url, boolean warnMissingImages) {
        if (url.startsWith("http://") || url.startsWith("https://")) {
            // System.err.format("Image url '%s'%n", url);
            return url;
        }
        if (url.startsWith("../../images/")) {
            url = url.replace("../../images/", "/");
        }
        if (url.startsWith("../images/")) {
            url = url.replace("../images/", "/");
        }
        File imageFile = new File(imageDirectory, url);
        File copiedImageFile = new File(imageDestDirectory, imageFile.getName());
        if (imageFile.exists()) {
            if (!copiedImageFile.exists()) {
                try {
                    Files.copy(imageFile.toPath(), copiedImageFile.toPath());
                    url = imageDestDirectory.getName() + "/" + imageFile.getName();
                } catch (IOException ex) {
                    Logger.getLogger(CustomAsciiDocDocumentBuilder.class.getName()).log(Level.SEVERE, null, ex);
                }
            } else {
                url = imageDestDirectory.getName() + "/" + imageFile.getName();
            }
        } else if (warnMissingImages) {
            LOG.log(Level.WARNING, "Image not found: {0}\n  in file {1}\n  for file {2}", new Object[]{url,
                imageFile.getAbsolutePath(),
                CustomAsciiDocDocumentBuilder.this.outputFile.getAbsolutePath()});
        }
        return url;
    }
}
