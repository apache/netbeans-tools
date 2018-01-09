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

import static com.google.common.base.Preconditions.checkNotNull;
import java.io.IOException;
import java.io.StringWriter;
import java.io.Writer;
import java.util.regex.Pattern;
import org.eclipse.mylyn.wikitext.markdown.internal.MarkdownDocumentBuilder;
import org.eclipse.mylyn.wikitext.parser.Attributes;
import org.eclipse.mylyn.wikitext.parser.LinkAttributes;

/**
 * A MarkdownDocumentBuilder that properly handles some block types that the
 * default Mylyn MarkdownDocumentBuilder just ignores. This is version specific,
 * as mylyn documentation is very poor.
 *
 * @author Antonio Vieiro <vieiro@apache.org>
 */
public class GithubMarkdownDocumentBuilder extends MarkdownDocumentBuilder {

    private static final Pattern PATTERN_LINE_BREAK = Pattern.compile("(.*(\r\n|\r|\n)?)?");

    private class GithubMarkdownBlock extends NewlineDelimitedBlock {

        protected String prefix;
        protected String suffix;

        GithubMarkdownBlock(BlockType type, String prefix, String suffix) {
            this(type, prefix, suffix, 1, 1);
        }

        GithubMarkdownBlock(String prefix, String suffix, int leadingNewlines, int trailingNewlines) {
            this(null, prefix, suffix, leadingNewlines, trailingNewlines);
        }

        GithubMarkdownBlock(BlockType type, String prefix, String suffix, int beforeLineCount, int afterLineCount) {
            super(type, beforeLineCount, afterLineCount);
            this.prefix = prefix;
            this.suffix = suffix;
        }

        @Override
        public void write(int c) throws IOException {
            GithubMarkdownDocumentBuilder.this.emitContent(c);
        }

        @Override
        public void write(String s) throws IOException {
            GithubMarkdownDocumentBuilder.this.emitContent(s);
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
            GithubMarkdownDocumentBuilder.this.emitContent(prefix);
            GithubMarkdownDocumentBuilder.this.emitContent(content);
            GithubMarkdownDocumentBuilder.this.emitContent(suffix);
        }
    }

    private class GithubDefinitionListBlock extends GithubMarkdownBlock {

        GithubDefinitionListBlock() {
            super(BlockType.DEFINITION_LIST, "", "");
        }
    }

    private class GithubDefinitionTermBlock extends GithubMarkdownBlock {

        private GithubDefinitionTermBlock() {
            super(BlockType.DEFINITION_TERM, "- ", " ", 1, 0);
        }

    }

    private class GithubDefinitionItem extends GithubMarkdownBlock {

        private GithubDefinitionItem() {
            super(BlockType.DEFINITION_ITEM, "", "", 0, 1);
        }
    }

    private class GithubTable extends GithubMarkdownBlock {

        private int count = 0;

        GithubTable(Attributes attributes) {
            super(BlockType.TABLE, "", "");
        }

        protected void addRow(GithubTableRow row) {
            checkNotNull(row);
            count++;
        }

        protected int getCount() {
            return count;
        }

    }

    private class GithubTableRow extends GithubMarkdownBlock {

        boolean header = false;
        int cellCount = 0;

        GithubTableRow(Attributes attributes) {
            super(BlockType.TABLE_ROW, "", "");
        }

        void addCell(GithubTableCellHeader header) {
            cellCount++;
        }

        void setHeader(boolean header) {
            this.header = header;
        }

        @Override
        protected void emitContent(String content) throws IOException {
            super.emitContent(content + "|\n");
            if (header) {
                for (int i = 0; i < cellCount; i++) {
                    super.emitContent("|---");
                }
                super.emitContent("|\n");
            }
        }
    }

    private class GithubTableCellHeader extends GithubMarkdownBlock {

        GithubTableCellHeader(Attributes attributes) {
            super(BlockType.TABLE_CELL_HEADER, "|", "", 0, 0);
        }

        @Override
        public void open() throws IOException {
            super.open();
            if (getPreviousBlock() instanceof GithubTableRow) {
                GithubTableRow row = (GithubTableRow) getPreviousBlock();
                row.setHeader(true);
                row.addCell(this);
            }
        }
    }

    private class GithubTableCellNormal extends GithubMarkdownBlock {

        GithubTableCellNormal(Attributes attributes) {
            super(BlockType.TABLE_CELL_NORMAL, "|", "", 0, 0);
        }

    }

    private class GithubPreformattedContentBlock extends GithubMarkdownBlock {

        private String language;

        GithubPreformattedContentBlock(String language) {
            super(BlockType.PREFORMATTED, "", ""); //```" + (language == null ? "" : " " + language) + "\n", "```");
            this.language = language;
        }

        @Override
        protected void emitContent(String content) throws IOException {
            if (language == null) {
                /* No language specified, try to guess... */
                if (content.indexOf("/>") != -1 || content.indexOf("</") != -1) {
                    language = "xml";
                } else {
                    language = "java";
                }
            }
            
            // [label](http://url.com) or
            // [label](http://url.com "title")
            GithubMarkdownDocumentBuilder.this.emitContent("``` " + language);
            GithubMarkdownDocumentBuilder.this.emitContent(content.startsWith("\n") ? content : "\n" + content);
            GithubMarkdownDocumentBuilder.this.emitContent("\n```");
        }
    }

    private class LinkBlock extends GithubMarkdownBlock {

        private final LinkAttributes attributes;

        LinkBlock(LinkAttributes attributes) {
            super("", "", 0, 0);
            this.attributes = attributes;
        }

        @Override
        protected void emitContent(String content) throws IOException {
            // [label](http://url.com) or
            // [label](http://url.com "title")
            GithubMarkdownDocumentBuilder.this.emitContent('[');
            GithubMarkdownDocumentBuilder.this.emitContent(content);
            GithubMarkdownDocumentBuilder.this.emitContent(']');

            GithubMarkdownDocumentBuilder.this.emitContent('(');
            if (attributes.getHref() != null && !attributes.getHref().startsWith("/wiki/")) {
                GithubMarkdownDocumentBuilder.this.emitContent(attributes.getHref());
            }
            String title = attributes.getTitle();
            if (title != null && !title.equals("")) {
                String capitalized = Character.toUpperCase(title.charAt(0)) + title.substring(1);
                GithubMarkdownDocumentBuilder.this.emitContent(capitalized + ".html");
            }
            GithubMarkdownDocumentBuilder.this.emitContent(')');
        }

    }

    public GithubMarkdownDocumentBuilder(Writer out) {
        super(out);
    }

    private static final String[][] MARKUP_FIXES = {
        {"<b>", "**"},
        {"</b>", "**"},
        {"<tt>", "`"},
        {"</tt>", "`"},};

    @Override
    protected void emitContent(String str) throws IOException {
        for (String[] pairs : MARKUP_FIXES) {
            if (str.indexOf(pairs[0]) != 1) {
                str = str.replaceAll(pairs[0], pairs[1]);
            }
        }
        super.emitContent(str);
    }

    @Override
    protected Block computeSpan(SpanType type, Attributes attributes) {
        switch (type) {
            case LINK:
                if (attributes instanceof LinkAttributes) {
                    return new LinkBlock((LinkAttributes) attributes);
                }
                return new GithubMarkdownBlock("<", ">", 0, 0); //$NON-NLS-1$ //$NON-NLS-2$
            default:
                return super.computeSpan(type, attributes);
        }
    }

    @Override
    protected Block computeBlock(BlockType type, Attributes attributes
    ) {
        switch (type) {

            case PARAGRAPH:
            case QUOTE:
            case BULLETED_LIST:
            case NUMERIC_LIST:
            case LIST_ITEM:
            case CODE:
                return super.computeBlock(type, attributes);
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
                return new GithubPreformattedContentBlock(language);
            case DEFINITION_LIST:
                return new GithubDefinitionListBlock();
            case DEFINITION_TERM:
                return new GithubDefinitionTermBlock();
            case DEFINITION_ITEM:
                return new GithubDefinitionItem();
            case TABLE:
                return new GithubTable(attributes);
            case TABLE_ROW:
                return new GithubTableRow(attributes);
            case TABLE_CELL_HEADER:
                return new GithubTableCellHeader(attributes);
            case TABLE_CELL_NORMAL:
                return new GithubTableCellNormal(attributes);
            default:
                throw new IllegalStateException("Unexpected block type: " + type.name() + ":" + type);
        }
    }
}
