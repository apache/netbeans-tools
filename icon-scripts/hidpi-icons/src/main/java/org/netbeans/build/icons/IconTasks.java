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
package org.netbeans.build.icons;

import com.google.common.base.Preconditions;
import com.google.common.collect.ImmutableMap;
import com.google.common.collect.ImmutableSet;
import com.google.common.collect.ImmutableSetMultimap;
import com.google.common.collect.LinkedHashMultimap;
import com.google.common.collect.Lists;
import com.google.common.collect.Maps;
import com.google.common.collect.SetMultimap;
import com.google.common.collect.Sets;
import java.awt.Dimension;
import java.awt.image.BufferedImage;
import java.io.BufferedOutputStream;
import java.io.BufferedReader;
import java.io.File;
import java.io.FileOutputStream;
import java.io.FileReader;
import java.io.IOException;
import java.io.InputStreamReader;
import java.io.PrintWriter;
import java.util.AbstractMap.SimpleEntry;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;
import java.util.Map.Entry;
import java.util.Set;
import org.netbeans.build.icons.TypeTaggedString.ArtboardName;
import org.netbeans.build.icons.TypeTaggedString.IconPath;
import org.netbeans.build.icons.TypeTaggedString.Hash;

/**
 * <p>Script for maintaining mappings between bitmap icons (PNG and GIF files) and newer SVG
 * versions. See the the {@code icon-scripts/README.txt} file for more information.
 *
 * <p>This script must be run with icon-scripts/hidpi-icons as the working directory (as is
 * the default when running this file from the NetBeans IDE).
 */
public class IconTasks {
    /* Constants relating to artboard positioning in the generated add_illustrator_exports.jsx
    script. All values are in pixels. */
    private static final int ARTBOARD_MAX_X = 288;
    private static final int ARTBOARD_GRID = 12;
    private static final int ARTBOARD_MIN_SPACING = 2;

    private static final String LICENSE_HEADER = readLicenseHeader();

    public static void main(String[] args) throws IOException {
        final File ICON_SCRIPTS_DIR = new File(System.getProperty("user.dir"), "../");
        if (!new File(ICON_SCRIPTS_DIR, "hidpi-icons/src/main/java/org/netbeans/build/icons/IconTasks.java").exists()) {
            System.err.println("Error: Working directory must be icon-scripts/hidpi-icons");
            System.exit(-1);
        }

        if (args.length != 1) {
            System.err.println("Usage: IconTasks <netbeans repo directory>");
            System.err.println();
            System.err.println("(Working directory must be icon-scripts/hidpi-icons )");
            System.exit(-1);
        }

        final File NBSRC_DIR = new File(args[0]);
        if (!new File(NBSRC_DIR, "platform/core.windows/build.xml").exists()) {
            System.err.println("Path " + args[0] + " (in command-line argument) is not a cloned NetBeans repository");
            System.exit(-1);
        }
        final File ILLUSTRATOR_SVGS_DIR = new File(ICON_SCRIPTS_DIR, "illustrator_exports/");
        final File TABLES_DIR = new File(ICON_SCRIPTS_DIR, "tables/");
        final File ICON_HASHES_FILE = new File(TABLES_DIR, "icon-hashes.txt");
        final File MAPPINGS_FILE = new File(TABLES_DIR, "mappings.tsv");
        final File READY_ARTBOARDS_FILE = new File(TABLES_DIR, "ready-artboards.txt");
        final File ICONS_HTML_FILE = new File(NBSRC_DIR, "icons.html");
        final File ILLUSTRATOR_ARTBOARD_SCRIPT_FILE = new File(ICON_SCRIPTS_DIR, "add_illustrator_artboards.jsx");
        boolean copySVGfiles =
                ILLUSTRATOR_SVGS_DIR.listFiles(f-> f.toString().endsWith(".svg")).length > 0;
        System.out.println("Using icon hashes file     : " + ICON_HASHES_FILE);
        System.out.println("Using mappings file        : " + MAPPINGS_FILE);
        System.out.println("Using ready-artboards file : " + READY_ARTBOARDS_FILE);
        if (copySVGfiles) {
            System.out.println("Copying SVG files from     : " + ILLUSTRATOR_SVGS_DIR);
        } else {
            System.out.println("The " + ILLUSTRATOR_SVGS_DIR +
                " folder has no SVGs in it to copy. " +
                "Will verify the existence of existing SVG files in the NetBeans repo only.");
        }

        ImmutableMap<IconPath, Hash> iconHashesByFile =
                readIconHashesByFile(NBSRC_DIR, ICON_HASHES_FILE);
        ImmutableSetMultimap<Hash, IconPath> filesByHash = Util.reverse(iconHashesByFile);
        ImmutableMap<Hash, Dimension> dimensionsByHash = readImageDimensions(NBSRC_DIR, filesByHash);
        // iconHashesByFile.forEach((key, value) -> System.out.println(key + " : " + value));

        ImmutableMap<IconPath, ArtboardName> artboardByFile =
                readArtboardByFileMappings(NBSRC_DIR, MAPPINGS_FILE);
        // artboardByFile.forEach((key, value) -> System.out.println(key + " : " + value));

        ImmutableSet<ArtboardName> readyArtboards = readReadyArtboards(READY_ARTBOARDS_FILE);

        SetMultimap<ArtboardName,Hash> hashesByArtboard = LinkedHashMultimap.create();
        for (Entry<IconPath, ArtboardName> entry : artboardByFile.entrySet()) {
            IconPath ip = entry.getKey();
            ArtboardName artboard = entry.getValue();
            Hash hash = Util.getChecked(iconHashesByFile, ip);
            hashesByArtboard.put(artboard, hash);
        }

        Set<ArtboardName> unknownReadyArtboards =
                Sets.difference(readyArtboards, hashesByArtboard.keySet());
        if (!unknownReadyArtboards.isEmpty()) {
            throw new RuntimeException("Unknown artboards " + unknownReadyArtboards);
        }
        if (copySVGfiles) {
            for (ArtboardName artboard : readyArtboards) {
                File artboardSVGFile = getIllustratorSVGFile(ILLUSTRATOR_SVGS_DIR, artboard);
                if (!artboardSVGFile.exists()) {
                    System.out.println("Illustrator export " + artboardSVGFile + " not found; skipping.");
                }
            }
        }

        // hashesByArtboard.asMap().forEach((key, value) -> System.out.println(key + " : " + value));

        Map<IconPath, ArtboardName> newArtboardByFile = Maps.newLinkedHashMap();

        for (Entry<ArtboardName, Hash> entry : hashesByArtboard.entries()) {
            ArtboardName artboard = entry.getKey();
            Hash hash = entry.getValue();
            List<IconPath> pathsForThisHash = Lists.newArrayList(filesByHash.get(hash));
            pathsForThisHash.sort((e1, e2) -> e1.toString().compareTo(e2.toString()));
            for (IconPath ip : pathsForThisHash) {
                Util.putChecked(newArtboardByFile, ip, artboard);
            }
        }

        ArtboardName UNASSIGNED_ARTBOARD = new ArtboardName("(no assigned artboard)");
        {
            List<Entry<IconPath,Dimension>> unassignedIcons = Lists.newArrayList();
            for (Entry<IconPath, Hash> entry : iconHashesByFile.entrySet()) {
                IconPath ip = entry.getKey();
                Hash hash = entry.getValue();
                Dimension dim = Util.getChecked(dimensionsByHash, hash);
                if (dim.width <= 64 && dim.height <= 64 && dim.width > 1 && dim.height > 1) {
                    unassignedIcons.add(new SimpleEntry(ip, dim));
                }
            }
            /* Order unassigned icons by width, then by height, then by path. (If there are multiple
            paths, the first path will end up being used for sorting per putIfAbsent below.) */
            unassignedIcons.sort((e1, e2) -> {
                int ret = Integer.compare(e1.getValue().width, e2.getValue().width);
                if (ret == 0) {
                    ret = Integer.compare(e1.getValue().height, e2.getValue().height);
                }
                if (ret == 0) {
                    ret = e1.getKey().toString().compareTo(e2.getKey().toString());
                }
                return ret;
            });
            for (Entry<IconPath,Dimension> entry : unassignedIcons) {
                newArtboardByFile.putIfAbsent(entry.getKey(), UNASSIGNED_ARTBOARD);
            }
        }

        // newArtboardByFile.forEach((key, value) -> System.out.println(key + " : " + value));
        ImmutableSetMultimap<ArtboardName, IconPath> filesByArtboard =
                Util.reverse(newArtboardByFile);

        for (ArtboardName artboard : readyArtboards) {
            final String svgContentToWrite;
            if (copySVGfiles) {
                svgContentToWrite = prepareSVGWithInsertedLicense(ILLUSTRATOR_SVGS_DIR, artboard);
            } else {
                svgContentToWrite = null;
            }
            BufferedImage srcImage = (svgContentToWrite == null) ? null :
                    ImageUtil.renderSVGImageFromXMLString(svgContentToWrite);
            for (IconPath ip : filesByArtboard.get(artboard)) {
                if (shouldIgnoreFile(ip)) {
                    continue;
                }
                IconPath destSVG = getSVGIconPath(ip);
                // System.out.println(srcSVGFile + "\t" + destSVG);
                File destSVGFile = new File(NBSRC_DIR, destSVG.toString());
                if (svgContentToWrite != null) {
                    BufferedImage destImage = destSVGFile.exists() ? ImageUtil.renderSVGImage(destSVGFile) : null;
                    if (destImage != null && ImageUtil.imagesAreEqual(srcImage, destImage)) {
                        System.out.println(
                                "Target SVG file is pixel-identical when rendered; skipping copy (" + destSVGFile + ")");
                    } else {
                        try (PrintWriter pw = createPrintWriter(destSVGFile)) {
                            pw.print(svgContentToWrite);
                        }
                        System.out.println("Copied SVG file to " + destSVGFile);
                    }
                } else {
                    if (!destSVGFile.exists()) {
                        throw new RuntimeException(destSVGFile + " does not exist, and no " +
                                "SVGs to copy exist in the illustrator_exports directory.");
                    }
                    System.out.println("Verified existence of SVG file " + destSVGFile);
                }
            }
        }

        int artboardX = 0;
        int artboardY = 0;
        int currentArtboardRowTallestIcon = 0;

        /* The mappings file is assumed to be in a git repo so that the user of the script can
        see what changed from run to run. */
        try (PrintWriter mappingsPW = createPrintWriter(MAPPINGS_FILE);
             PrintWriter htmlPW = createPrintWriter(ICONS_HTML_FILE);
             PrintWriter scriptPW = createPrintWriter(ILLUSTRATOR_ARTBOARD_SCRIPT_FILE))
        {
            scriptPW.println("/* This generated Adobe Illustrator script places newly mapped artboards in the");
            scriptPW.println("existing nb_vector_icons.ai file, with old PNG or GIF icons placed, embedded,");
            scriptPW.println("and locked in the \"Old Bitmaps\" layer.\n");
            scriptPW.println("To use this script, first open nb_vector_icons.ai in Adobe Illustrator. Then ");
            scriptPW.println("click File->Scripts->Other Script, and browse to this file. */\n");
            scriptPW.println("var doc = app.activeDocument;");
            scriptPW.println("var targetLayer = doc.layers.getByName(\"Old Bitmaps\");");
            scriptPW.println("var left, top, right, bottom, placedItem, embeddedItem, scaleX, scaleY;\n");

            scriptPW.println("var firstColumnX = 0;");
            scriptPW.println("for (var i = 0; i < doc.artboards.length; i++) {");
            scriptPW.println("  var abRect = doc.artboards[i].artboardRect;");
            scriptPW.println("  var rightX = abRect[2]; // right side of this artboard");
            scriptPW.println("  if (rightX > firstColumnX) {");
            scriptPW.println("    firstColumnX = rightX;");
            scriptPW.println("  }");
            scriptPW.println("}");
            scriptPW.println("firstColumnX += 32;\n");

            htmlPW.println(LICENSE_HEADER);
            htmlPW.println("""
                <html>
                <head>
                <title>NetBeans Icons</title>
                <!--The the image paths in this file assume that this HTML file is located in the
                    root of a clone of the NetBeans source repository. To use images from a specific
                    GitHub branch (e.g. belonging to a pull request), a line like the following can
                    be included here:
                  <base href="https://raw.githubusercontent.com/eirikbakke/incubator-netbeans/pr-svgs240612/">
                -->
                <style>
                table td, table td * { vertical-align: top; margin-left: 5px; }
                thead td { padding-right: 10px; padding-bottom: 10px; }
                td { padding-right: 10px; }
                thead { font-weight: bold; }
                </style></head><body>
                <h1>NetBeans Bitmap and SVG Icons</h1>
                <p>This file lists bitmap icon files (GIF and PNG) in the NetBeans repo along with
                   their mapping to corresponding modernized SVG versions where available. A single
                   "artboard name" is assigned to icons that are exact or near duplicates, or which
                   are intended to have the same meaning.
                <p>This file is generated by the
                <tt>icon-scripts/hidpi-icons</tt> script in the
                <a href="https://github.com/apache/netbeans-tools">netbeans-tools</a>
                repository. Image paths are relative to the root of the NetBeans
                <a href="https://github.com/apache/netbeans">source repository</a>.
                <p>See the <a href="https://github.com/apache/netbeans-tools/tree/master/icon-scripts#readme">README</a>,
                    <a href="https://cwiki.apache.org/confluence/display/NETBEANS/SVG+Icon+Style+Guide+and+Process">Style Guide</a>, and
                    <a href="https://vimeo.com/667860571">Icon Drawing Video Tutorial</a> for more information.
                """
            );
            htmlPW.println("""
                <p><table border='0' cellpadding='1' cellspacing='0'>
                <thead><tr><td>Artboard Name<td>SVG<td>Bitmap<td>Dim<td>Path of Bitmap in
                  Source Repo (no icon image means same as for previous row)</tr></thead>""");
            int artboardIdx = 0;
            Set<ArtboardName> artboardsInOrder = Sets.newLinkedHashSet();
            artboardsInOrder.addAll(Sets.filter(filesByArtboard.keySet(), a -> readyArtboards.contains(a)));
            artboardsInOrder.addAll(filesByArtboard.keySet());
            for (ArtboardName artboard : artboardsInOrder) {
                List<IconPath> ips = Lists.newArrayList(filesByArtboard.get(artboard));
                ips.removeIf(ip -> shouldIgnoreFile(ip));
                /* Make sure to retain the original order, except keep files with the same hash
                together. */
                Map<Hash,Integer> order = Maps.newLinkedHashMap();
                for (IconPath ip : ips) {
                    order.putIfAbsent(Util.getChecked(iconHashesByFile, ip), order.size());
                }
                ips.sort((ip1, ip2) -> Integer.compare(
                        Util.getChecked(order, Util.getChecked(iconHashesByFile, ip1)),
                        Util.getChecked(order, Util.getChecked(iconHashesByFile, ip2))));
                int subRowIdx = 0;
                Hash previousHash = null;
                for (IconPath ip : ips) {
                    Hash hash = Util.getChecked(iconHashesByFile, ip);
                    if (!UNASSIGNED_ARTBOARD.equals(artboard)) {
                        mappingsPW.println(artboard + "  " + ip);
                    }

                    htmlPW.print(artboardIdx % 2 == 0 ? "<tr>" :
                            "<tr style='background: #eee'>");
                    if (subRowIdx == 0) {
                        /* Add an invisible "^" to make it possible to search for artboard names
                        with Ctrl+F (e.g. "^ok") without getting matches in the path column. */
                        htmlPW.print("<td rowspan='" + ips.size() + "'><span style=\"color: #00000000\">^</span>" + artboard);
                        htmlPW.print("<td rowspan='" + ips.size() + "'>");
                        if (readyArtboards.contains(artboard)) {
                            htmlPW.print("<img src='" + getSVGIconPath(ip) + "'>");
                        }
                    }
                    htmlPW.print("<td>");
                    if (!hash.equals(previousHash)) {
                        htmlPW.print("<img src='" + ip + "'>");
                    }
                    htmlPW.print("<td>");
                    if (!hash.equals(previousHash)) {
                        Dimension dim = Util.getChecked(dimensionsByHash, hash);
                        htmlPW.print(dim.width + "x" + dim.height);
                    }
                    htmlPW.print("<td>" + ip);
                    htmlPW.println("</tr>");
                    previousHash = hash;
                    subRowIdx++;
                }

                if (copySVGfiles && !UNASSIGNED_ARTBOARD.equals(artboard) &&
                    /* We assume that _all_ existing artboards, ready or not, have been exported
                    to ILLUSTRATOR_SVGS_DIR. That way we can use the presence of an SVG file there
                    to determine if the Illustrator file already contains a given artboard or
                    not. */
                    !getIllustratorSVGFile(ILLUSTRATOR_SVGS_DIR, artboard).exists())
                {
                    IconPath ip = ips.get(0);
                    Hash hash = Util.getChecked(iconHashesByFile, ip);
                    Dimension dim = Util.getChecked(dimensionsByHash, hash);

                    if (artboardX + dim.width > ARTBOARD_MAX_X) {
                        artboardX = 0;
                        artboardY -= getArtboardAdvance(-artboardY, currentArtboardRowTallestIcon);
                        currentArtboardRowTallestIcon = 0;
                    }
                    File file = new File(NBSRC_DIR, ip.toString());
                    if (!file.exists()) {
                        throw new AssertionError("File existence should have been checked earlier");
                    }
                    scriptPW.println("left   = " + artboardX + " + firstColumnX;");
                    scriptPW.println("top    = " + artboardY + ";");
                    scriptPW.println("right  = left + " + dim.width + ";");
                    scriptPW.println("bottom = top  - " + dim.height + ";"); // Minus appears correct here.
                    scriptPW.println("newArtboard = doc.artboards.add([left, top, right, bottom]);");
                    scriptPW.println("newArtboard.name = \"" + artboard + "\";");
                    scriptPW.println("placedItem = targetLayer.placedItems.add();");
                    scriptPW.println("placedItem.file = new File(\"" + file.toString() + "\");");
                    /* PNGs may have embedded DPI values, which should be disregarded. Resize to get a
                    1:1 pixel mapping. */
                    scriptPW.println("scaleX = " + dim.width  + " / placedItem.width;");
                    scriptPW.println("scaleY = " + dim.height + " / placedItem.height;");
                    scriptPW.println("placedItem.resize(scaleX * 100, scaleY * 100);");
                    scriptPW.println("placedItem.left   = left;");
                    scriptPW.println("placedItem.top    = top;");
                    scriptPW.println("placedItem.locked = true;");
                    scriptPW.println("placedItem.embed();");
                    scriptPW.println();

                    currentArtboardRowTallestIcon = Math.max(currentArtboardRowTallestIcon, dim.height);
                    artboardX += getArtboardAdvance(artboardX, dim.width);
                }

                artboardIdx++;
            }
            htmlPW.println("</table>");
            htmlPW.println("</body>");
        }

        System.out.println(
                "A summary of bitmap icons, SVG icons, and artboard name mappings was generated here:");
        System.out.println(ICONS_HTML_FILE);
    }

    private static int getArtboardAdvance(int currentPosition, int iconSize) {
      int minAdvance = iconSize + ARTBOARD_MIN_SPACING;
      int nextGridPosition = Math.ceilDiv(currentPosition + minAdvance, ARTBOARD_GRID) * ARTBOARD_GRID;
      return nextGridPosition - currentPosition;
    }

    private static PrintWriter createPrintWriter(File file) throws IOException {
        // See https://stackoverflow.com/questions/1014287/is-there-a-way-to-make-printwriter-output-to-unix-format/14749004
        return new PrintWriter(new BufferedOutputStream(new FileOutputStream(file))) {
            @Override
            public void println() {
                write('\n');
            }
        };
    }

    private static IconPath getSVGIconPath(IconPath ip) {
        // Extension is verified in BitmapFile's constructor.
        String path = ip.toString();
        return new IconPath(path.substring(0, path.length() - 4) + ".svg");
    }

    private static File getIllustratorSVGFile(File illustratorSVGsDir, ArtboardName artboard) {
        Preconditions.checkNotNull(artboard);
        return new File(illustratorSVGsDir, "icon_" + artboard + ".svg");
    }

    private static boolean shouldIgnoreFile(IconPath ip) {
        String s = ip.toString();
        return s.startsWith("ide/usersguide/javahelp/") ||
                /* The window system icons have paint()-based vector icon implementations. See
                https://github.com/apache/netbeans/pull/859 */
                s.startsWith("platform/o.n.swing.tabcontrol/") ||
                s.startsWith("platform/openide.awt/") && s.contains("close_"); // close/bigclose
    }

    /**
     * Read the mappings.tsv file, where each line should state an artboard name followed by
     * (tab-separated) a relative filename of a PNG or GIF file. The existence of each file is
     * validated. For each artboard, the first file in the iteration order is the one that should
     * serve as a template for the SVG file.
     */
    private static ImmutableMap<IconPath, ArtboardName> readArtboardByFileMappings(File nbsrcDir, File file) throws IOException {
        Map<IconPath, ArtboardName> ret = new LinkedHashMap<>();
        try (BufferedReader br = new BufferedReader(new FileReader(file))) {
            String line;
            while ((line = br.readLine()) != null) {
                if (line.trim().isEmpty())
                  continue;
                String[] parts = line.split("\\s+", 2);
                if (parts.length == 2) {
                    String artboard = parts[0].trim();
                    String filePath = parts[1].trim();
                    File actualFile = new File(nbsrcDir, filePath);
                    if (!actualFile.exists()) {
                        throw new RuntimeException("File does not exist: " + actualFile);
                    }
                    IconPath iconPath = new IconPath(filePath);
                    ArtboardName artboardName = new ArtboardName(artboard);
                    if (shouldIgnoreFile(iconPath)) {
                        throw new RuntimeException(
                                "Ignore list should not match mapped icon (" + iconPath + ")");
                    }
                    Util.putChecked(ret, new IconPath(filePath), artboardName);
                } else {
                    throw new RuntimeException("Invalid line: " + line);
                }
            }
        }
        return ImmutableMap.copyOf(ret);
    }

    /**
     * Read the icon-hashes.txt file, which should contain a SHA-256 hash followed by
     * (space-separated) a relative filename of PNG and GIF files in the NetBeans source directory.
     * The existence of each file is validated, but the hashes are assumed to be correct.
     */
    private static ImmutableMap<IconPath, Hash> readIconHashesByFile(File nbsrcDir, File file) throws IOException {
        Map<IconPath, Hash> ret = new LinkedHashMap<>();
        try (BufferedReader br = new BufferedReader(new FileReader(file))) {
            String line;
            while ((line = br.readLine()) != null) {
                String[] parts = line.split("\\s+", 2);
                if (parts.length == 2) {
                    String hash = parts[0].trim();
                    String filePath = parts[1].trim();
                    File actualFile = new File(nbsrcDir, filePath);
                    if (!actualFile.exists()) {
                        throw new RuntimeException("File does not exist: " + actualFile);
                    }
                    Util.putChecked(ret, new IconPath(filePath), new Hash(hash));
                } else {
                    throw new RuntimeException("Invalid line: " + line);
                }
            }
        }
        return ImmutableMap.copyOf(ret);
    }

    private static ImmutableSet<ArtboardName> readReadyArtboards(File file) throws IOException {
        Set<ArtboardName> ret = Sets.newLinkedHashSet();
        try (BufferedReader br = new BufferedReader(new FileReader(file))) {
            String line;
            while ((line = br.readLine()) != null) {
                Util.addChecked(ret, new ArtboardName(line.trim()));
            }
        }
        return ImmutableSet.copyOf(ret);
    }

    private static String prepareSVGWithInsertedLicense(File illustratorSVGsDir, ArtboardName artboard) throws IOException {
        StringBuilder ret = new StringBuilder();
        File srcFile = getIllustratorSVGFile(illustratorSVGsDir, artboard);
        if (!srcFile.exists()) {
            return null;
        }
        try (BufferedReader br = new BufferedReader(new FileReader(srcFile))) {
            String line;
            boolean firstLine = true;
            String EXPECTED_FIRST_LINE = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>";
            while ((line = br.readLine()) != null) {
                if (firstLine && !line.equals(EXPECTED_FIRST_LINE)) {
                    throw new RuntimeException(srcFile + ": First line was not " +
                            EXPECTED_FIRST_LINE);
                }
                ret.append(line).append("\n");
                if (firstLine) {
                    ret.append(LICENSE_HEADER);
                }
                firstLine = false;
            }
        }
        return ret.toString()
                /* Illustrator keeps generating this useless/incorrect metadata element, and I can't
                find a way to get rid of it. Just remove it here if it's present. (Though it didn't
                really do any harm in any case.) */
                .replace("  <description>Apache NetBeans Logo\n  </description>", "");
    }

    private static String readLicenseHeader() {
        StringBuilder ret = new StringBuilder();
        try (BufferedReader br = new BufferedReader(new InputStreamReader(
                IconTasks.class.getClassLoader()
                .getResourceAsStream("org/netbeans/build/icons/license_xml_header.txt"))))
        {
            String line;
            while ((line = br.readLine()) != null) {
                ret.append(line).append("\n");
            }
        } catch (IOException e) {
            throw new RuntimeException(e);
        }
        return ret.toString();
    }

    private static ImmutableMap<Hash, Dimension> readImageDimensions(
            File nbsrcDir, ImmutableSetMultimap<Hash, IconPath> filesByHash)
            throws IOException
    {
        Map<Hash, Dimension> ret = Maps.newLinkedHashMap();
        for (Entry<Hash, IconPath> entry : filesByHash.entries()) {
            Hash hash = entry.getKey();
            IconPath ip = entry.getValue();
            if (ret.containsKey(hash)) {
                continue;
            }
            File file = new File(nbsrcDir, ip.toString());
            Dimension dim = ImageUtil.readImageDimension(file);
            Util.putChecked(ret, hash, dim);
        }
        return ImmutableMap.copyOf(ret);
    }
}
