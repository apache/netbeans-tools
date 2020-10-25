/**
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
package convert;

import java.io.IOException;
import java.nio.charset.StandardCharsets;
import java.nio.file.DirectoryStream;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.Paths;
import java.util.ArrayList;
import java.util.List;
import java.util.Set;
import java.util.stream.Collectors;

/**
 *
 * @author boris.heithecker
 */
public class ConvertAndCopy {

    static Set<String> IMAGE_FILE_ENDINGS = Set.of(".gif");
    static long copied = 0l;
    static List<Path> notCopied = new ArrayList<>();
    static List<Path> imageFiles = new ArrayList<>();

    public static void main(String[] args) throws IOException {
        if (args.length != 2 && args.length != 3) {
            System.err.println("Use: Convert <source-directory> <destination-directory> [<log-file>]");
            return;
        }
        final Path sourceRoot = Paths.get(args[0]);
        final Path destinationRoot = Paths.get(args[1]);
        processDirectory(sourceRoot, destinationRoot);
        System.out.println("================================================");
        System.out.println("Copied/regenerated: " + copied + "; not copied: " + notCopied.size() + "; imageFiles " + imageFiles.size());
        if (args.length == 3) {
            final Path log = Paths.get(args[2]);
            final List<String> ll = new ArrayList<>();
            ll.add("=== Image files ===");
            imageFiles.stream()
                    .map(Path::toString)
                    .forEach(ll::add);
            ll.add("=== Not copied ===");
            notCopied.stream()
                    .map(Path::toString)
                    .forEach(ll::add);
            Files.write(log, ll);
        }
    }

    static void processDirectory(final Path sourceDir, final Path destDir) throws IOException {
        try (final DirectoryStream<Path> stream = Files.newDirectoryStream(sourceDir)) {
            for (final Path p : stream) {
                final Path rel = sourceDir.relativize(p);
                final Path t = destDir.resolve(rel);
                if (Files.isDirectory(p)) {
                    final Path td = Files.createDirectories(t);
                    processDirectory(p, td);
                    continue;
                }
                final String code = new String(Files.readAllBytes(p));
                final CategorizeLicenses.Description lic = CategorizeLicenses.snipUnifiedLicenseOrNull(code, p);
                if (lic != null) {
                    final boolean success = Convert.fixHeader(t, code, lic);
                    if (success) {
                        copied++;
                        System.out.println("Copied: " + t);
                        continue;
                    }
                } else {
                    final String name = rel.toString();
                    final String fileEnding = name.substring(name.lastIndexOf("."));
                    if (IMAGE_FILE_ENDINGS.contains(fileEnding)) {
                        imageFiles.add(p);
                        continue;
                    } else if (".properties".equals(fileEnding)) {
                        regenerateBundleFile(p, t);
                        copied++;
                        System.out.println("Regenerated: " + t);
                        continue;
                    }
                }
                notCopied.add(p);
            }
        }
    }

    private static void regenerateBundleFile(final Path source, final Path destination) throws IOException {
        final List<String> ll = Files.readAllLines(source, StandardCharsets.ISO_8859_1);
        final String mappings = ll.stream()
                .map(l -> l.trim())
                .filter(l -> !l.startsWith("#"))
                .collect(Collectors.joining("\n"));
        final String output = String.join("", Convert.BUNDLE_OUTPUT, mappings);
        Files.writeString(destination, output, StandardCharsets.ISO_8859_1);
    }
}
