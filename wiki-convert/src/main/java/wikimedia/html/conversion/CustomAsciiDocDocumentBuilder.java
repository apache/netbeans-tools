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

import java.io.IOException;
import java.io.StringWriter;
import java.io.Writer;
import java.util.regex.Matcher;
import java.util.regex.Pattern;
import org.eclipse.mylyn.wikitext.asciidoc.internal.AsciiDocDocumentBuilder;
import org.eclipse.mylyn.wikitext.parser.Attributes;
import org.eclipse.mylyn.wikitext.parser.LinkAttributes;

/**
 * An AsciiDocDocumentBuilder that fixes some wiki-specific problems.
 *
 * @author Antonio Vieiro <vieiro@apache.org>
 */
public class CustomAsciiDocDocumentBuilder extends AsciiDocDocumentBuilder {

    class AsciiDocContentBlock extends NewlineDelimitedBlock {

        protected String prefix;
        protected String suffix;

        AsciiDocContentBlock(BlockType blockType, String prefix, String suffix) {
            this(blockType, prefix, suffix, 1, 1);
        }

        AsciiDocContentBlock(BlockType blockType, String prefix, String suffix, int leadingNewlines, int trailingNewlines) {
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
            CustomAsciiDocDocumentBuilder.this.emitContent(content);
            CustomAsciiDocDocumentBuilder.this.emitContent(suffix);
        }
    }

    private class LinkBlock extends AsciiDocContentBlock {

        private final LinkAttributes attributes;

        LinkBlock(LinkAttributes attributes) {
            super("", "", 0, 0); //$NON-NLS-1$ //$NON-NLS-2$
            this.attributes = attributes;
        }

        @Override
        protected void emitContent(String content) throws IOException {
            // link:http://url.com[label]
            CustomAsciiDocDocumentBuilder.this.emitContent("link:"); //$NON-NLS-1$
            String href = attributes.getHref();
            if (href.startsWith("/wiki/")) {
                href = href.substring(6) + ".html";
            }
            CustomAsciiDocDocumentBuilder.this.emitContent(href);
            CustomAsciiDocDocumentBuilder.this.emitContent('[');
            if (content != null) {
                CustomAsciiDocDocumentBuilder.this.emitContent(content);
            }
            CustomAsciiDocDocumentBuilder.this.emitContent(']');
        }

    }

    class AsciiDocPreformatted extends AsciiDocContentBlock {

        private String language;

        AsciiDocPreformatted(String language) {
            super(BlockType.PREFORMATTED, "", "", 1, 1);
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
            CustomAsciiDocDocumentBuilder.this.emitContent("[source," + language + "]\n");
            CustomAsciiDocDocumentBuilder.this.emitContent("----\n");
            CustomAsciiDocDocumentBuilder.this.emitContent(content.startsWith("\n") ? content : "\n" + content);
            CustomAsciiDocDocumentBuilder.this.emitContent("----\n");
        }
    }

    public CustomAsciiDocDocumentBuilder(Writer out) {
        super(out);
    }

    private static final String[][] MARKUP_FIXES = {
        {"-----", ""},
        {"<b>", "*"},
        {"</b>", "*"},
        {"<tt>", "`"},
        {"</tt>", "`"},
        {"<code>", "`"},
        {"</code>", "`"},
    };

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
                return new AsciiDocContentBlock("", "", 0, 0); //$NON-NLS-1$ //$NON-NLS-2$
            default:
                return super.computeSpan(type, attributes);
        }
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
            default:
                return super.computeBlock(type, attributes);
        }
    }
}
