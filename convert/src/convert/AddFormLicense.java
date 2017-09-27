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

import convert.CategorizeLicenses.Description;
import java.io.IOException;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.Paths;
import java.util.regex.Pattern;

public class AddFormLicense {

    private static final String ASF_LICENSE_INPUT =
            "\\QLicensed to the Apache Software Foundation (ASF) under one or more contributor license agreements. See the NOTICE file distributed with this work for additional information regarding copyright ownership. The ASF licenses this file to you under the Apache License, Version 2.0 (the \"License\"); you may not use this file except in compliance with the License. You may obtain a copy of the License at\\E\\s*" +
            "\\Qhttp://www.apache.org/licenses/LICENSE-2.0\\E\\s*" +
            "\\QUnless required by applicable law or agreed to in writing, software distributed under the License is distributed on an \"AS IS\" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the License for the specific language governing permissions and limitations under the License.\\E\\s*";

    public static void main(String[] args) throws IOException {
        if (args.length != 1 && args.length != 2) {
            System.err.println("Use: AddFormLicense <source-directory>");
            return ;
        }
        Pattern headerPattern1 = Pattern.compile(ASF_LICENSE_INPUT, Pattern.MULTILINE);
        Path root = Paths.get(args[0]);
        int[] count = new int[1];
        Files.find(root, Integer.MAX_VALUE, (p, attr) -> attr.isRegularFile())
             .filter(p -> p.getFileName().toString().endsWith(".form"))
             .forEach(p -> {
                try {
                    String path = root.relativize(p).toString();
                    Path java = p.getParent().resolve(p.getFileName().toString().replace(".form", ".java"));
                    
                    if (!Files.exists(java)) {
                        System.err.println("No adjacent java source file: " + p);
                        return ;
                    }

                    String code = new String(Files.readAllBytes(java));
                    CategorizeLicenses.Description lic = CategorizeLicenses.snipUnifiedLicenseOrNull(code, p);
                    boolean success = false;

                    if (lic != null) {
                        if (headerPattern1.matcher(lic.header).matches()) {
                            String formCode = new String(Files.readAllBytes(p));
                            int i = formCode.indexOf(">\n\n<Form");
                            if (i == (-1)) {
                                throw new IllegalStateException(p.toString());
                            }
                            formCode = formCode.substring(0, i + 3) + "\n\n" + formCode.substring(i + 3);
                            success = Convert.fixHeader(p, formCode, new Description(i + 3, i + 3, formCode, CategorizeLicenses.CommentType.XML));
                            count[0]++;
                        }
                    }

                    if (!success) {
                        System.err.println("Cannot rewrite: " + p);
                    }
                } catch (IOException ex) {
                    throw new IllegalStateException(ex);
                }
             });
    }

}
