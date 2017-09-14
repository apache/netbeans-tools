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
import java.io.Writer;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.Paths;
import java.util.regex.Pattern;

public class Convert {

    private static final String LICENSE_INPUT_PATTERN1 =
            "\\QDO NOT ALTER OR REMOVE COPYRIGHT NOTICES OR THIS HEADER.\\E\\s*" +
            "(" +
            "\\QCopyright <YEARS> Oracle and/or its affiliates. All rights reserved.\\E\\s*" +
            "|" +
            "\\QCopyright (c) <YEARS> Oracle and/or its affiliates. All rights reserved.\\E\\s*" +
            "|" +
            "\\QCopyright \u00A9 <YEARS> Oracle and/or its affiliates. All rights reserved.\\E\\s*" +
            "|" +
            "\\QCopyright <YEARS> Sun Microsystems, Inc. All rights reserved.\\E\\s*" +
            ")" +
            "(\\QOracle and Java are registered trademarks of Oracle and/or its affiliates. Other names may be trademarks of their respective owners.\\E\\s*)?" +
            "(" +
            "\\QThe contents of this file are subject to the terms of either the GNU General Public License Version 2 only (\"GPL\") or the Common Development and Distribution License(\"CDDL\") (collectively, the \"License\"). You may not use this file except in compliance with the License. You can obtain a copy of the License at http://www.netbeans.org/cddl-gplv2.html or nbbuild/licenses/CDDL-GPL-2-CP. See the License for the specific language governing permissions and limitations under the License. When distributing the software, include this License Header Notice in each file and include the License file at nbbuild/licenses/CDDL-GPL-2-CP. Oracle designates this particular file as subject to the \"Classpath\" exception as provided by Oracle in the GPL Version 2 section of the License file that accompanied this code. If applicable, add the following below the License Header, with the fields enclosed by brackets [] replaced by your own identifying information: \"Portions Copyrighted [year] [name of copyright owner]\"\\E\\s*" +
            "|" +
            "\\QThe contents of this file are subject to the terms of either the GNU General Public License Version 2 only (\"GPL\") or the Common Development and Distribution License(\"CDDL\") (collectively, the \"License\"). You may not use this file except in compliance with the License. You can obtain x copy of the License at http://www.netbeans.org/cddl-gplv2.html or nbbuild/licenses/CDDL-GPL-2-CP. See the License for the specific language governing permissions and limitations under the License. When distributing the software, include this License Header Notice in each file and include the License file at nbbuild/licenses/CDDL-GPL-2-CP. Oracle designates this particular file as subject to the \"Classpath\" exception as provided by Oracle in the GPL Version 2 section of the License file that accompanied this code. If applicable, add the following below the License Header, with the fields enclosed by brackets [] replaced by your own identifying information: \"Portions Copyrighted [year] [name of copyright owner]\"\\E\\s*" + //note: typo "x copy" instead of "a copy"
            "|" +
            "\\QThe contents of this file are subject to the terms of either the GNU General Public License Version 2 only (\"GPL\") or the Common Development and Distribution License(\"CDDL\") (collectively, the \"License\"). You may not use this file except in compliance with the License. You can obtain a copy of the License at http://www.netbeans.org/cddl-gplv2.html or nbbuild/licenses/CDDL-GPL-2-CP. See the License for the specific language governing permissions and limitations under the License. When distributing the software, include this License Header Notice in each file and include the License file at nbbuild/licenses/CDDL-GPL-2-CP. Sun designates this particular file as subject to the \"Classpath\" exception as provided by Sun in the GPL Version 2 section of the License file that accompanied this code. If applicable, add the following below the License Header, with the fields enclosed by brackets [] replaced by your own identifying information: \"Portions Copyrighted [year] [name of copyright owner]\"\\E\\s*" +
            "|" +
            "\\QThe contents of this file are subject to the terms of either the GNU General Public License Version 2 only (\"GPL\") or the Common Development and Distribution License(\"CDDL\") (collectively, the \"License\"). You may not use this file except in compliance with the License. You can obtain a copy of the License at http:www.netbeans.org/cddl-gplv2.html or nbbuild/licenses/CDDL-GPL-2-CP. See the License for the specific language governing permissions and limitations under the License. When distributing the software, include this License Header Notice in each file and include the License file at nbbuild/licenses/CDDL-GPL-2-CP. Sun designates this particular file as subject to the \"Classpath\" exception as provided by Sun in the GPL Version 2 section of the License file that accompanied this code. If applicable, add the following below the License Header, with the fields enclosed by brackets [] replaced by your own identifying information: \"Portions Copyrighted [year] [name of copyright owner]\"\\E\\s*" + //note: mistake in URL (no '//'), otherwise as above
            ")" +
            "(" +
            "\\QIf you wish your version of this file to be governed by only the CDDL or only the GPL Version 2, indicate your decision by adding \"[Contributor] elects to include this software in this distribution under the [CDDL or GPL Version 2] license.\" If you do not indicate a single choice of license, a recipient has the option to distribute your version of this file under either the CDDL, the GPL Version 2 or to extend the choice of license to its licensees as provided above. However, if you add GPL Version 2 code and therefore, elected the GPL Version 2 license, then the option applies only if the new code is made subject to such option by the copyright holder.\\E\\s*" +
            "|" +
            "\\QIf you wish your version of this file to be governed by only the CDDL or only the GPL Version 2, indicate your decision by adding \"[Contributor] elects to include this software in this distribution under the [CDDL or GPL Version 2] license.\" If you do not indicate x single choice of license, x recipient has the option to distribute your version of this file under either the CDDL, the GPL Version 2 or to extend the choice of license to its licensees as provided above. However, if you add GPL Version 2 code and therefore, elected the GPL Version 2 license, then the option applies only if the new code is made subject to such option by the copyright holder.\\E\\s*" + //note: typos "a single choice" -> "x single choice", "a recipient" -> "x recipient"
            "|" +
            //empty
            ")" +
            "(\\QContributor(s):\\E\\s*)?" +
            "(" +
            "\\QThe Original Software is NetBeans. The Initial Developer of the Original Software is Sun Microsystems, Inc. Portions Copyright <YEARS> Sun Microsystems, Inc. All Rights Reserved.\\E\\s*" +
            "|" +
            "\\QThe Original Software is NetBeans. The Initial Developer of the Original Software is Sun Microsystems, Inc. Portions created by Sun Microsystems, Inc. are Copyright (C) <YEARS> All Rights Reserved.\\E\\s*" +
            "|" +
            "\\QThe Original Software is NetBeans. The Initial Developer of the Original Code is Sun Microsystems, Inc. Portions Copyright <YEARS> Sun Microsystems, Inc. All Rights Reserved.\\E\\s*" +
            "|" +
            "\\QThe Original Software is NetBeans. The Initial Developer of the Original Software is Oracle. Portions Copyright <YEARS> Oracle. All Rights Reserved.\\E\\s*" +
            "|" +
            "\\QThe original software is NetBeans. The initial developer of the original software was Sun Microsystems, Inc.; portions copyright <YEARS> Sun Microsystems, Inc. All rights reserved.\\E\\s*" +
            "|" +
            "\\QThe Original Software is NetBeans. The Initial Developer of the Original Software is Sun Microsystems, Inc.\nPortions Copyrighted <YEARS> Sun Microsystems, Inc.\\E\\s*" +
            "|" +
            "\\QPortions Copyrighted <YEARS> Sun Microsystems, Inc.\\E\\s*" +
            "|" +
            "\\QPortions Copyrighted <YEARS> Oracle, Inc.\\E\\s*" +
            "|" +
            "\\QPortions Copyrighted <YEARS> Oracle\\E\\s*" +
            "|)" +
            "(\\QIf you wish your version of this file to be governed by only the CDDL or only the GPL Version 2, indicate your decision by adding \"[Contributor] elects to include this software in this distribution under the [CDDL or GPL Version 2] license.\" If you do not indicate a single choice of license, a recipient has the option to distribute your version of this file under either the CDDL, the GPL Version 2 or to extend the choice of license to its licensees as provided above. However, if you add GPL Version 2 code and therefore, elected the GPL Version 2 license, then the option applies only if the new code is made subject to such option by the copyright holder.\\E\\s*)?";

    private static final String JAVA_OUTPUT =
            "/**\n" +
            " * Licensed to the Apache Software Foundation (ASF) under one\n" +
            " * or more contributor license agreements.  See the NOTICE file\n" +
            " * distributed with this work for additional information\n" +
            " * regarding copyright ownership.  The ASF licenses this file\n" +
            " * to you under the Apache License, Version 2.0 (the\n" +
            " * \"License\"); you may not use this file except in compliance\n" +
            " * with the License.  You may obtain a copy of the License at\n" +
            " *\n" +
            " *   http://www.apache.org/licenses/LICENSE-2.0\n" +
            " *\n" +
            " * Unless required by applicable law or agreed to in writing,\n" +
            " * software distributed under the License is distributed on an\n" +
            " * \"AS IS\" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY\n" +
            " * KIND, either express or implied.  See the License for the\n" +
            " * specific language governing permissions and limitations\n" +
            " * under the License.\n" +
            " */";

    private static final String XML_OUTPUT =
            "<!--\n" +
            "\n" +
            "    Licensed to the Apache Software Foundation (ASF) under one\n" +
            "    or more contributor license agreements.  See the NOTICE file\n" +
            "    distributed with this work for additional information\n" +
            "    regarding copyright ownership.  The ASF licenses this file\n" +
            "    to you under the Apache License, Version 2.0 (the\n" +
            "    \"License\"); you may not use this file except in compliance\n" +
            "    with the License.  You may obtain a copy of the License at\n" +
            "\n" +
            "      http://www.apache.org/licenses/LICENSE-2.0\n" +
            "\n" +
            "    Unless required by applicable law or agreed to in writing,\n" +
            "    software distributed under the License is distributed on an\n" +
            "    \"AS IS\" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY\n" +
            "    KIND, either express or implied.  See the License for the\n" +
            "    specific language governing permissions and limitations\n" +
            "    under the License.\n" +
            "\n" +
            "-->";

    private static final String BUNDLE_OUTPUT =
            "#\n" +
            "# Licensed to the Apache Software Foundation (ASF) under one\n" +
            "# or more contributor license agreements.  See the NOTICE file\n" +
            "# distributed with this work for additional information\n" +
            "# regarding copyright ownership.  The ASF licenses this file\n" +
            "# to you under the Apache License, Version 2.0 (the\n" +
            "# \"License\"); you may not use this file except in compliance\n" +
            "# with the License.  You may obtain a copy of the License at\n" +
            "#\n" +
            "#   http://www.apache.org/licenses/LICENSE-2.0\n" +
            "#\n" +
            "# Unless required by applicable law or agreed to in writing,\n" +
            "# software distributed under the License is distributed on an\n" +
            "# \"AS IS\" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY\n" +
            "# KIND, either express or implied.  See the License for the\n" +
            "# specific language governing permissions and limitations\n" +
            "# under the License.\n" +
            "#\n";

    public static void main(String[] args) throws IOException {
        if (args.length != 1) {
            System.err.println("Use: Convert <source-directory>");
            return ;
        }
        Pattern headerPattern = Pattern.compile(LICENSE_INPUT_PATTERN1, Pattern.MULTILINE);
        Path root = Paths.get(args[0]);
        int[] count = new int[1];
        Files.find(root, Integer.MAX_VALUE, (p, attr) -> attr.isRegularFile())
             .forEach(p -> {
                try {
                    String path = root.relativize(p).toString();
                    String code = new String(Files.readAllBytes(p));

                    if (code.contains("CDDL")) {
                        CategorizeLicenses.Description lic = CategorizeLicenses.snipUnifiedLicenseOrNull(code, p);

                        if (lic != null) {
                            if (headerPattern.matcher(lic.header).matches()) {
                                fixHeader(p, code, lic);
                                count[0]++;
                            }
                        }
                    }
                } catch (IOException ex) {
                    ex.printStackTrace();
                }
             });
        
        System.err.println("convertible: " + count[0]);
    }
    
    private static void fixHeader(Path file, String code, Description desc) {
        String fn = file.getFileName().toString();
        String outputLicense;
        switch (fn.substring(fn.lastIndexOf('.') + 1)) {
            case "javx": case "c": case "h": case "cpp":
            case "java": outputLicense = JAVA_OUTPUT; break;
            case "html": case "xsd": case "xsl": case "dtd":
            case "settings": case "wstcgrp": case "wstcref":
            case "wsgrp": 
            case "xml": outputLicense = XML_OUTPUT; break;
            case "sh":
            case "properties": outputLicense = BUNDLE_OUTPUT; break;
            default:
                System.err.println("cannot rewrite: " + file);
                return ;
        }
        
        try (Writer out = Files.newBufferedWriter(file)) {
            String newCode = code.substring(0, desc.start) + outputLicense + code.substring(desc.end);
            out.write(newCode);
        } catch (IOException ex) {
            throw new IllegalStateException(ex);
        }
    }
    
}
