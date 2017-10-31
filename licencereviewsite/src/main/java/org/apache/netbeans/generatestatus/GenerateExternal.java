/*
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
package org.apache.netbeans.generatestatus;

import java.io.IOException;
import java.util.Locale;
import org.apache.maven.reporting.MavenReportException;
import org.apache.maven.plugins.annotations.Mojo;

/**
 *
 * @author skygo
 */
@Mojo(name = "checkexternal")
public class GenerateExternal extends AbstractReviewMavenReport {

    @Override
    protected void executeReport(Locale locale) throws MavenReportException {
        try {
            prepareReport("suspicious external");
        } catch (IOException ex) {
            throw new MavenReportException("error", ex);
        }
    }

    @Override
    public String getOutputName() {
        return "reviewexternal";
    }

    @Override
    public String getName(Locale locale) {
        return "Apache NetBeans External Checking";
    }

    @Override
    public String getDescription(Locale locale) {
        return "NetBeans review";
    }

}
