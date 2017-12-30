<?xml version="1.0" encoding="UTF-8"?>
<!--

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

-->
<xsl:stylesheet 
    xmlns:mw="http://www.mediawiki.org/xml/export-0.3/" 
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" 
    xsi:schemaLocation="http://www.mediawiki.org/xml/export-0.3/ http://www.mediawiki.org/xml/export-0.3.xsd"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" 
    version="1.0">
    
    <xsl:output method="text" omit-xml-declaration="yes"/>
    
    <xsl:template match="/">&lt;!--

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

--&gt;
<xsl:apply-templates />

===Apache Migration Information===

The content in this page was kindly donated by Oracle Corp. to the
Apache Software Foundation.

This page was exported from <xsl:value-of select="/mw:mediawiki/mw:page/mw:base" />, 
that last modified by NetBeans user <xsl:value-of select="/mw:mediawiki/mw:page/mw:revision/mw:contributor/mw:username" /> 
on <xsl:value-of select="/mw:mediawiki/mw:page/mw:revision/mw:timestamp" />.

</xsl:template>

    <xsl:template match="/mw:mediawiki/mw:page/mw:revision/mw:text/text()">
        <xsl:call-template name="string-replace-all">
            <xsl:with-param name="text" select="." />
            <xsl:with-param name="replace">__NOTOC__</xsl:with-param>
            <xsl:with-param name="by"/>
        </xsl:call-template>
    </xsl:template>

    <xsl:template match="text() "/>

    <xsl:template name="string-replace-all">
        <xsl:param name="text" />
        <xsl:param name="replace" />
        <xsl:param name="by" />
        <xsl:choose>
            <xsl:when test="$text = '' or $replace = ''or not($replace)" >
                <!-- Prevent this routine from hanging -->
                <xsl:value-of select="$text" />
            </xsl:when>
            <xsl:when test="contains($text, $replace)">
                <xsl:value-of select="substring-before($text,$replace)" />
                <xsl:value-of select="$by" />
                <xsl:call-template name="string-replace-all">
                    <xsl:with-param name="text" select="substring-after($text,$replace)" />
                    <xsl:with-param name="replace" select="$replace" />
                    <xsl:with-param name="by" select="$by" />
                </xsl:call-template>
            </xsl:when>
            <xsl:otherwise>
                <xsl:value-of select="$text" />
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>


</xsl:stylesheet>
