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

import java.util.regex.Pattern;

/**
 * Modifies some hrefs in links to the new Apache infrastructure.
 */
public final class NetBeansTutorialsLinkReviewer {

	private static final String EXACT = "exact";

	private static final String[][] PREFIXES = {
		{
			"http://java.sun.com/j2se/1.4.2/download.html",
			"https://www.oracle.com/technetwork/java/javase/downloads/index.html"
		},
		{
			"http://java.sun.com/j2se/1.4.2/ja/download.html",
			"https://www.oracle.com/technetwork/java/javase/downloads/index.html"
		},
		{
			"http://java.sun.com/j2se/1.4.2/jadownload.html",
			"https://www.oracle.com/technetwork/java/javase/downloads/index.html"
		},
		{
			"http://java.sun.com/j2se/1.5.0/download.jsp",
			"https://www.oracle.com/technetwork/java/javase/downloads/index.html"
		},
		{
			"http://java.sun.com/j2se/1.5.0/ja/download.html",
			"https://www.oracle.com/technetwork/java/javase/downloads/index.html"
		},
		{
			"http://netbeans.org/kb/docs",
			"https://netbeans.apache.org/kb/docs"
		},
		{
			"http://Platform.netbeans.org/tutorials",
			"https://netbeans.apache.org/tutorials"
		},
		{
			"http://bits.nbextras.org",
			"https://bits.netbeans.org"
		},
		{
			"http://blogs.sun.com",
			"https://blogs.oracle.com"
		},
		{
			"https://code.google.com/p/openmap/downloads/list",
			"https://github.com/OpenMap-java/openmap/releases"
		},
		{
			"http://core.netbeans.org/source/browse/*checkout*/core/swing/plaf/src/org/netbeans/swing/plaf/util/RelativeColor.java",
			"https://github.com/apache/netbeans/blob/master/platform/o.n.swing.plaf/src/org/netbeans/swing/plaf/util/RelativeColor.java"
		},
		{
			"http://deadlock.netbeans.org/hudson/job/nbms-and-javadoc/lastStableBuild/artifact/nbbuild/nbms/extra/org-netbeans-modules-uihandler-interactive.nbm",
			"https://builds.apache.org/view/M-R/view/NetBeans/job/netbeans-linux/lastSuccessfulBuild/artifact/nbbuild/nbms/platform/org-netbeans-modules-uihandler.nbm"
		},
		{
			"http://www.netbeans.info/downloads/",
			"https://netbeans.apache.org/download/index.html",
			EXACT
		},
		{
			"http://download.netbeans.org",
			"https://netbeans.apache.org/download/index.html",
			EXACT
		},
		{
			"https://netbeans.org/downloads",
			"https://netbeans.apache.org/download/index.html",
			EXACT
		},
		{
			"http://graph.netbeans.org",
			"https://netbeans.apache.org/graph"
		},
		{
			"https://hg.netbeans.org/main/file/bdb88f1fa043/html",
			"https://github.com/apache/netbeans/tree/master/ide/html"
		},
		{
			"http://hg.netbeans.org/main/file/bdb88f1fa043/html/src/org/netbeans/modules/html/palette/items",
			"https://github.com/apache/netbeans/tree/master/ide/html/src/org/netbeans/modules/html/palette/items"
		},
		{
			"http://hg.netbeans.org/main/file/",
			"https://github.com/apache/netbeans/"
		},
		{
			"http://javacc.java.net",
			"https://javacc.github.io/javacc/"
		},
		{
			"http://java.net/projects/colorchooser/sources/svn/show/trunk/www/release?rev=82",
			"http://web.archive.org/web/20120107130444/http://java.net:80/projects/colorchooser/sources/svn/show/trunk/www/release?rev=82"
		},
		{
			"http://java.net/projects/javacc",
			"http://web.archive.org/web/20170410180215/https://java.net/projects/javacc/"
		},
		{
			"http://java.net/projects/javacc/downloads",
			"http://web.archive.org/web/20170130015602/https://java.net/projects/javacc/downloads"
		},
		{
			"http://java.net/projects/javacc",
			"https://javacc.github.io/javacc/"
		},
		{
			"http://java.net/projects/javafxbrowser",
			"http://web.archive.org/web/20150927002527/https://java.net/projects/javafxbrowser"
		},
		{
			"http://java.net/projects/nb-api-samples/sources/api-samples/show/versions/7.2/tutorials/CustomerProjectType",
			"http://web.archive.org/web/20130305120247/http://java.net:80/projects/nb-api-samples/sources/api-samples/show/versions/7.2/tutorials/CustomerProjectType"
		},
		{
			"http://java.net/projects/nb-api-samples/sources/api-samples/show/versions/7.3/tutorials/GoogleToolbar",
			"http://web.archive.org/web/20150523015116/https://java.net/projects/nb-api-samples/sources/api-samples/show/versions/7.3/tutorials/GoogleToolbar"
		},
		{
			"http://java.net/projects/nb-api-samples/sources/api-samples/show/versions/7.3/tutorials/PaintApp",
			"http://web.archive.org/web/20130131034823/http://java.net:80/projects/nb-api-samples/sources/api-samples/show/versions/7.3/tutorials/PaintApp"
		},
		{
			"http://java.net/projects/nb-api-samples/sources/api-samples/show/versions/7.3/tutorials/selection-management/3-of-4/EventManager",
			"http://web.archive.org/web/20130320045006/http://java.net:80/projects/nb-api-samples/sources/api-samples/show/versions/7.3/tutorials/selection-management/3-of-4/EventManager"
		},
		{
			"http://java.net/projects/nb-api-samples/sources/api-samples/show/versions/7.3/tutorials/WordProcessor",
			"http://web.archive.org/web/20130405002940/http://java.net:80/projects/nb-api-samples/sources/api-samples/show/versions/7.3/tutorials/WordProcessor"
		},
		{
			"http://java.net/projects/nb-api-samples/sources/api-samples/show/versions/8.0/tutorials/CountryCodeCompleter",
			"http://web.archive.org/web/20150927111721/https://java.net/projects/nb-api-samples/sources/api-samples/show/versions/8.0/tutorials/CountryCodeCompleter"
		},
		{
			"http://java.net/projects/nb-api-samples/sources/api-samples/",
			"http://web.archive.org/web/20170409072842/http://java.net/projects/nb-api-samples/"
		},
		{
			"http://java.net/projects/nbribbonbar/",
			"http://web.archive.org/web/20161103225925/https://java.net/projects/nbribbonbar"
		},
		{
			"http://java.net/projects/netbeans",
			"http://web.archive.org/web/20170410015153/https://java.net/projects/netbeans"
		},
		{
			"https://colorchooser.dev.java.net/",
			"http://web.archive.org/web/20081119053233/http://colorchooser.dev.java.net/"
		},
		{
			"http://www.netbeans.org/project/www/download/dev/javadoc/",
			"https://bits.netbeans.org/dev/javadoc/"
		},
		{
			"http://java.sun.com/j2se/1.3/docs/guide/",
			"https://docs.oracle.com/javase/8/docs/technotes/guides/"
		},
		{
			"http://www.netbeans.org/download/dev/javadoc/",
			"https://bits.netbeans.org/dev/javadoc"
		},
		{
			"http://netbeans.org/download/dev/javadoc",
			"https://bits.netbeans.org/dev/javadoc"
		},
		{
			"https://netbeans.org/download/dev/javadoc",
			"https://bits.netbeans.org/dev/javadoc"
		},
		{
			"https://netbeans.org/project/www/download/dev/javadoc/",
			"https://bits.netbeans.org/dev/javadoc/"
		},
		{
			"http://java.sun.com/j2se/1.4.2/docs/api/",
			"https://docs.oracle.com/javase/8/docs/api/"
		},
		{
			"http://docs.oracle.com/javase/1.4.2/docs/api/",
			"https://docs.oracle.com/javase/8/docs/api/"
		},
		{
			"http://java.sun.com/j2ee/1.4/docs/api/",
			"https://docs.oracle.com/javaee/1.4/api/"
		},
		{
			"http://java.sun.com/j2ee/",
			"https://docs.oracle.com/javaee/"
		},
		{
			"http://java.sun.com/j2se",
			"https://docs.oracle.com/javase"
		},
		{
			"http://java.sun.com/javase/downloads/index.jsp",
			"https://www.oracle.com/technetwork/java/javase/downloads/index.html"
		},
		{
			"http://java.sun.com/javase/downloads/index.jsp",
			"https://www.oracle.com/technetwork/java/javase/downloads/index.html"
		},
		{
			"https://jemmy.dev.java.net",
			"https://hg.openjdk.java.net/code-tools/jemmy/v2/file/7f1077e65e78"
		},
		{
			"http://jsourcery.com/output/sourceforge/g4j/0.3.12/siuying/gm/structure/GMThread.html",
			"http://g4j.sourceforge.net/"
		},
		{
			"http://mojo.codehaus.org/nbm-maven-plugin/",
			"http://bits.netbeans.org/mavenutilities/nbm-maven-plugin/"
		},
		{
			"https://netbeans.org/about/contact_form.html",
			"http://netbeans.apache.org/community/mailing-lists.html",
			EXACT
		},
		{
			"https://netbeans.org/kb/trails",
			"https://netbeans.apache.org/kb/docs",
		},
		{
			"https://netbeans.org/issues/show_bug.cgi",
			"https://bz.apache.org/netbeans/show_bug.cgi"
		},
		{
			"https://netbeans.org/bugzilla/show_bug.cgi",
			"https://bz.apache.org/netbeans/show_bug.cgi"
		},
		{
			"https://netbeans.org/kb/docs",
			"https://netbeans.apache.org/kb/docs"
		},
		{
			"https://graph.netbeans.org",
			"https://netbeans.apache.org/graph",
		},
		{
			"http://graph.netbeans.org",
			"https://netbeans.apache.org/graph",
		},
		{
			"https://platform.netbeans.org/graph",
			"https://netbeans.apache.org/graph"
		},
		{
			"http://platform.netbeans.org/graph",
			"https://netbeans.apache.org/graph"
		},
		{
			"http://platform.netbeans.org/tutorials",
			"https://netbeans.apache.org/tutorials"
		},
		{
			"https://platform.netbeans.org/tutorials",
			"https://netbeans.apache.org/tutorials"
		},
		{
			"https://platform.netbeans.org",
			"https://netbeans.apache.org/platform"
		},
		{
    			"http://www.sun.com/books/catalog/rich_client_programming.xml",
			"https://www.amazon.com/Rich-Client-Programming-Plugging-NetBeans/dp/0132354802"
		},
		{
    			"http://www.oracle.com/technetwork/java/javafx/overview/index.html",
			"https://www.oracle.com/technetwork/java/javase/overview/javafx-overview-2158620.html"
		},
		{
			"https://wiki.netbeans.org/wiki/view/DevFaq",
			"https://netbeans.apache.org/wiki/DevFaq"
		},
		{
			"http://wiki.netbeans.org/wiki/view/DevFaq",
			"https://netbeans.apache.org/wiki/DevFaq"
		},
		{
			"http://wiki.netbeans.org/DevFaq",
			"https://netbeans.apache.org/wiki/DevFaq"
		},
		{
			"https://wiki.netbeans.org/DevFaq",
			"https://netbeans.apache.org/wiki/DevFaq"
		},
		{
			"https://wiki.netbeans.org/wiki/view/Dev",
			"https://netbeans.apache.org/wiki/Dev"
		},
		{
			"http://wiki.netbeans.org/wiki/view/Dev",
			"https://netbeans.apache.org/wiki/Dev"
		},
		{
			"http://wiki.netbeans.org/Dev",
			"https://netbeans.apache.org/wiki/Dev"
		},
		{
			"https://wiki.netbeans.org/Dev",
			"https://netbeans.apache.org/wiki/Dev"
		},

	};

	public static final String updateHREF(String href) {

		String originalHREF = href;

		if (href.indexOf("download") != -1) {
			// System.out.println("Breakpoint! " + href); // Breakpoint
		}

		for (String[] prefixSet : PREFIXES) {
			if (href.startsWith(prefixSet[0])) {
				if (prefixSet.length == 3 && EXACT.equals(prefixSet[2])) {
					href = prefixSet[1];
				} else {
					href = prefixSet[1] + href.substring(prefixSet[0].length());
				}
				break;
			}
		}

		return href;
	}

	/**
	 * Quick & dirty tests.
	 *
	 * @param args
	 */
	public static void main(String[] args) {

		final String[][] tests = {
			{
				"http://netbeans.org/kb/docs/a/b/c",
				"https://netbeans.apache.org/kb/docs/a/b/c"
			},
			{
				"http://Platform.netbeans.org/tutorials/nbm-windowsapi.html",
				"https://netbeans.apache.org/tutorials/nbm-windowsapi.html"
			},
			{
				"http://bits.nbextras.org/dev/javadoc/org-openide-dialogs/org/openide/NotifyDescriptor.html",
				"https://bits.netbeans.org/dev/javadoc/org-openide-dialogs/org/openide/NotifyDescriptor.html"
			},
			{
				"http://blogs.sun.com/geertjan/entry/creating_a_better_java_class",
				"https://blogs.oracle.com/geertjan/entry/creating_a_better_java_class"

			}
		};

		for (String[] test : tests) {
			String result = updateHREF(test[0]);
			if (!result.equals(test[1])) {
				throw new IllegalArgumentException(String.format("%s is wrong: expected %s got %s",
					test[0],
					test[1],
					result));
			}
		}
	}

}
