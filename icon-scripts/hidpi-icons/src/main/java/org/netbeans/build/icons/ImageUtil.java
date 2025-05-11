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

import java.awt.Graphics2D;
import java.awt.RenderingHints;
import java.awt.image.BufferedImage;
import java.io.IOException;
import java.io.InputStream;
import java.util.LinkedHashMap;
import java.util.Map;
import com.github.weisj.jsvg.SVGDocument;
import com.github.weisj.jsvg.geometry.size.FloatSize;
import com.github.weisj.jsvg.parser.LoaderContext;
import com.github.weisj.jsvg.parser.ResourceLoader;
import com.github.weisj.jsvg.parser.SVGLoader;
import com.github.weisj.jsvg.renderer.awt.NullPlatformSupport;
import java.awt.Dimension;
import java.io.BufferedInputStream;
import java.io.ByteArrayInputStream;
import java.io.File;
import java.io.FileInputStream;
import java.net.URI;
import java.nio.charset.StandardCharsets;
import java.util.Arrays;
import javax.annotation.Nullable;
import javax.imageio.ImageIO;

public class ImageUtil {
    private static final ThreadLocal<SVGLoader> SVG_LOADER =
            new ThreadLocal<SVGLoader>()
    {
        @Override
        protected SVGLoader initialValue() {
            return new SVGLoader();
        }
    };

    private ImageUtil() {
    }

    private static SVGDocument readSVGDocument(InputStream is) throws IOException {
        final SVGDocument ret;
        try {
            ResourceLoader resourceLoader = (URI nnuri) -> {
                throw new IOException("External resource loading from SVG file not permitted");
            };
            ret = SVG_LOADER.get().load(is, null, LoaderContext.builder()
                .resourceLoader(resourceLoader)
                .build());
            if (ret == null) {
                throw new IOException("SVG loading failed (SVGLoader.load returned null)");
            }
        } catch (RuntimeException e) {
            throw new IOException("Error parsing SVG file", e);
        }
        return ret;
    }

    public static @Nullable Dimension readImageDimension(File file) throws IOException {
        if (file.getName().endsWith(".svg")) {
            SVGDocument svgDocument = readSVGDocument(new BufferedInputStream(new FileInputStream(file)));
            FloatSize floatSize = svgDocument.size();
            return new Dimension(
                    (int) Math.ceil(floatSize.getWidth()),
                    (int) Math.ceil(floatSize.getHeight()));
        }
        BufferedImage image = ImageIO.read(file);
        if (image == null)
          throw new IOException("ImageIO.read returned null for " + file);
        int width = image.getWidth();
        int height = image.getHeight();
        return new Dimension(width, height);
    }

    private static RenderingHints createHints() {
        Map<RenderingHints.Key,Object> hints = new LinkedHashMap<>();
        hints.put(RenderingHints.KEY_ANTIALIASING, RenderingHints.VALUE_ANTIALIAS_ON);
        hints.put(RenderingHints.KEY_TEXT_ANTIALIASING, RenderingHints.VALUE_TEXT_ANTIALIAS_ON);
        hints.put(RenderingHints.KEY_STROKE_CONTROL, RenderingHints.VALUE_STROKE_PURE);
        return new RenderingHints(hints);
    }

    public static BufferedImage renderSVGImage(File file) throws IOException {
        try (InputStream is = new BufferedInputStream(new FileInputStream(file))) {
            return renderSVGImage(is);
        }
    }

    public static BufferedImage renderSVGImageFromXMLString(String xmlString) throws IOException {
        try (InputStream is = new ByteArrayInputStream(xmlString.getBytes(StandardCharsets.UTF_8))) {
            return renderSVGImage(is);
        }
    }

    public static BufferedImage renderSVGImage(InputStream is) throws IOException {
        final int width, height;

        final SVGDocument svgDocument = readSVGDocument(is);
        final FloatSize documentSize = svgDocument.size();
        width = (int) Math.ceil(documentSize.getWidth());
        height = (int) Math.ceil(documentSize.getHeight());
        if (width <= 1 && height <= 1) {
            throw new IOException("SVG image did not specify a width/height, or is incorrectly sized");
        }

        // Include some DPI scaling for SVG rendering comparison purposes.
        final int DPI_SCALING = 2;
        BufferedImage img = new BufferedImage(width * DPI_SCALING, height * DPI_SCALING, BufferedImage.TYPE_INT_ARGB);
        final Graphics2D g = img.createGraphics();
        try {
            g.scale(DPI_SCALING, DPI_SCALING);
            g.addRenderingHints(createHints());
            svgDocument.renderWithPlatform(NullPlatformSupport.INSTANCE, g, null);
        } finally {
            g.dispose();
        }
        return img;
    }

    public static boolean imagesAreEqual(BufferedImage img1, BufferedImage img2) {
        if (img1.getWidth() != img2.getWidth() || img1.getHeight() != img2.getHeight()) {
            return false;
        }

        int width = img1.getWidth();
        int height = img1.getHeight();
        int[] pixels1 = img1.getRGB(0, 0, width, height, null, 0, width);
        int[] pixels2 = img2.getRGB(0, 0, width, height, null, 0, width);

        return Arrays.equals(pixels1, pixels2);
    }
}
