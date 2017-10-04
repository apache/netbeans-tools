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

import org.junit.Test;
import static org.junit.Assert.*;

/**
 *
 * @author lahvac
 */
public class CategorizeLicensesTest {

    public CategorizeLicensesTest() {
    }

    @Test
    public void testSnipLicenseBundle1() {
        final String code = "\n" +
                            "#\n" +
                            "#CDDL\n" +
                            "#\n" +
                            "#lic\n" +
                            "#\n" +
                            "\n";
        CategorizeLicenses.Description desc =
                CategorizeLicenses.snipLicenseBundle(code,
                                                     null,
                                                     "#",
                                                     CategorizeLicenses.CommentType.PROPERTIES);
        assertEquals("CDDL\nlic", desc.header);
        assertEquals("#CDDL\n#\n#lic\n", code.substring(desc.start, desc.end));
    }

    @Test
    public void testSecondComment() {
        final String code = "<!--first-->\n" +
                            "<!--CDDL-->\n" +
                            "\n";
        CategorizeLicenses.Description desc =
                CategorizeLicenses.snipLicense(code,
                                               "<!--",
                                               "-->",
                                               null,
                                               CategorizeLicenses.CommentType.XML);
        assertEquals("CDDL", desc.header);
    }

}
