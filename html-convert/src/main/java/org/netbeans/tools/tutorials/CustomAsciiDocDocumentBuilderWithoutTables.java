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

import java.io.BufferedWriter;
import java.io.File;
import org.eclipse.mylyn.wikitext.parser.Attributes;

/**
 *
 */
public class CustomAsciiDocDocumentBuilderWithoutTables extends CustomAsciiDocDocumentBuilder {

    public CustomAsciiDocDocumentBuilderWithoutTables(File topDirectory, File imageDirectory, File outputFile, BufferedWriter output, ExternalLinksMap externalLinks) {
        super(topDirectory, imageDirectory, outputFile, output, externalLinks);
    }

    @Override
    protected Block computeBlock(BlockType type, Attributes attributes) {
        switch(type) {
            case TABLE:
            case TABLE_CELL_HEADER:
            case TABLE_CELL_NORMAL:
            case TABLE_ROW:
                return new AsciiDocContentBlock("", "", 0, 0);
        }
        return super.computeBlock(type, attributes); //To change body of generated methods, choose Tools | Templates.
    }


}
