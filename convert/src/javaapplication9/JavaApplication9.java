package javaapplication9;

import java.io.IOException;
import java.io.Writer;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.Paths;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

public class JavaApplication9 {

    private static final String INPUT =
"# DO NOT ALTER OR REMOVE COPYRIGHT NOTICES OR THIS HEADER.\n" +
"#\n" +
"# Copyright 2010 Oracle and/or its affiliates. All rights reserved.\n" +
"#\n" +
"# Oracle and Java are registered trademarks of Oracle and/or its affiliates.\n" +
"# Other names may be trademarks of their respective owners.\n" +
"#\n" +
"# The contents of this file are subject to the terms of either the GNU\n" +
"# General Public License Version 2 only (\"GPL\") or the Common\n" +
"# Development and Distribution License(\"CDDL\") (collectively, the\n" +
"# \"License\"). You may not use this file except in compliance with the\n" +
"# License. You can obtain a copy of the License at\n" +
"# http://www.netbeans.org/cddl-gplv2.html\n" +
"# or nbbuild/licenses/CDDL-GPL-2-CP. See the License for the\n" +
"# specific language governing permissions and limitations under the\n" +
"# License.  When distributing the software, include this License Header\n" +
"# Notice in each file and include the License file at\n" +
"# nbbuild/licenses/CDDL-GPL-2-CP.  Oracle designates this\n" +
"# particular file as subject to the \"Classpath\" exception as provided\n" +
"# by Oracle in the GPL Version 2 section of the License file that\n" +
"# accompanied this code. If applicable, add the following below the\n" +
"# License Header, with the fields enclosed by brackets [] replaced by\n" +
"# your own identifying information:\n" +
"# \"Portions Copyrighted [year] [name of copyright owner]\"\n" +
"#\n" +
"# If you wish your version of this file to be governed by only the CDDL\n" +
"# or only the GPL Version 2, indicate your decision by adding\n" +
"# \"[Contributor] elects to include this software in this distribution\n" +
"# under the [CDDL or GPL Version 2] license.\" If you do not indicate a\n" +
"# single choice of license, a recipient has the option to distribute\n" +
"# your version of this file under either the CDDL, the GPL Version 2 or\n" +
"# to extend the choice of license to its licensees as provided above.\n" +
"# However, if you add GPL Version 2 code and therefore, elected the GPL\n" +
"# Version 2 license, then the option applies only if the new code is\n" +
"# made subject to such option by the copyright holder.\n" +
"#\n" +
"# Contributor(s):\n" +
"#\n" +
"# Portions Copyrighted 2010 Sun Microsystems, Inc.";

//    private static final String INPUT =
//"# DO NOT ALTER OR REMOVE COPYRIGHT NOTICES OR THIS HEADER.\n" +
//"#\n" +
//"# Copyright 2010 Sun Microsystems, Inc. All rights reserved.\n" +
//"#\n" +
//"# The contents of this file are subject to the terms of either the GNU\n" +
//"# General Public License Version 2 only (\"GPL\") or the Common\n" +
//"# Development and Distribution License(\"CDDL\") (collectively, the\n" +
//"# \"License\"). You may not use this file except in compliance with the\n" +
//"# License. You can obtain a copy of the License at\n" +
//"# http://www.netbeans.org/cddl-gplv2.html\n" +
//"# or nbbuild/licenses/CDDL-GPL-2-CP. See the License for the\n" +
//"# specific language governing permissions and limitations under the\n" +
//"# License.  When distributing the software, include this License Header\n" +
//"# Notice in each file and include the License file at\n" +
//"# nbbuild/licenses/CDDL-GPL-2-CP.  Sun designates this\n" +
//"# particular file as subject to the \"Classpath\" exception as provided\n" +
//"# by Sun in the GPL Version 2 section of the License file that\n" +
//"# accompanied this code. If applicable, add the following below the\n" +
//"# License Header, with the fields enclosed by brackets [] replaced by\n" +
//"# your own identifying information:\n" +
//"# \"Portions Copyrighted [year] [name of copyright owner]\"\n" +
//"#\n" +
//"# Contributor(s):\n" +
//"#\n" +
//"#The Original Software is NetBeans. The Initial Developer of the Original\n" +
//"# Software is Sun Microsystems, Inc. Portions Copyright 2010 Sun\n" +
//"#Microsystems, Inc. All Rights Reserved.\n" +
//"#\n" +
//"# If you wish your version of this file to be governed by only the CDDL\n" +
//"# or only the GPL Version 2, indicate your decision by adding\n" +
//"# \"[Contributor] elects to include this software in this distribution\n" +
//"# under the [CDDL or GPL Version 2] license.\" If you do not indicate a\n" +
//"# single choice of license, a recipient has the option to distribute\n" +
//"# your version of this file under either the CDDL, the GPL Version 2 or\n" +
//"# to extend the choice of license to its licensees as provided above.\n" +
//"# However, if you add GPL Version 2 code and therefore, elected the GPL\n" +
//"# Version 2 license, then the option applies only if the new code is\n" +
//"# made subject to such option by the copyright holder.";

//    private static final String INPUT =
//" # DO NOT ALTER OR REMOVE COPYRIGHT NOTICES OR THIS HEADER.\n" +
//" #\n" +
//" # Copyright 2010 Oracle and/or its affiliates. All rights reserved.\n" +
//" #\n" +
//" # Oracle and Java are registered trademarks of Oracle and/or its affiliates.\n" +
//" # Other names may be trademarks of their respective owners.\n" +
//" #\n" +
//" # The contents of this file are subject to the terms of either the GNU\n" +
//" # General Public License Version 2 only (\"GPL\") or the Common\n" +
//" # Development and Distribution License(\"CDDL\") (collectively, the\n" +
//" # \"License\"). You may not use this file except in compliance with the\n" +
//" # License. You can obtain a copy of the License at\n" +
//" # http://www.netbeans.org/cddl-gplv2.html\n" +
//" # or nbbuild/licenses/CDDL-GPL-2-CP. See the License for the\n" +
//" # specific language governing permissions and limitations under the\n" +
//" # License.  When distributing the software, include this License Header\n" +
//" # Notice in each file and include the License file at\n" +
//" # nbbuild/licenses/CDDL-GPL-2-CP.  Oracle designates this\n" +
//" # particular file as subject to the \"Classpath\" exception as provided\n" +
//" # by Oracle in the GPL Version 2 section of the License file that\n" +
//" # accompanied this code. If applicable, add the following below the\n" +
//" # License Header, with the fields enclosed by brackets [] replaced by\n" +
//" # your own identifying information:\n" +
//" # \"Portions Copyrighted [year] [name of copyright owner]\"\n" +
//" #\n" +
//" # Contributor(s):\n" +
//" #\n" +
//" # The Original Software is NetBeans. The Initial Developer of the Original\n" +
//" # Software is Sun Microsystems, Inc. Portions Copyright 2010 Sun\n" +
//" # Microsystems, Inc. All Rights Reserved.\n" +
//" #\n" +
//" # If you wish your version of this file to be governed by only the CDDL\n" +
//" # or only the GPL Version 2, indicate your decision by adding\n" +
//" # \"[Contributor] elects to include this software in this distribution\n" +
//" # under the [CDDL or GPL Version 2] license.\" If you do not indicate a\n" +
//" # single choice of license, a recipient has the option to distribute\n" +
//" # your version of this file under either the CDDL, the GPL Version 2 or\n" +
//" # to extend the choice of license to its licensees as provided above.\n" +
//" # However, if you add GPL Version 2 code and therefore, elected the GPL\n" +
//" # Version 2 license, then the option applies only if the new code is\n" +
//" # made subject to such option by the copyright holder.";

//    private static final String INPUT =
//"# DO NOT ALTER OR REMOVE COPYRIGHT NOTICES OR THIS HEADER.\n" +
//"#\n" +
//"# Copyright 2010 Sun Microsystems, Inc. All rights reserved.\n" +
//"#\n" +
//"# The contents of this file are subject to the terms of either the GNU\n" +
//"# General Public License Version 2 only (\"GPL\") or the Common\n" +
//"# Development and Distribution License(\"CDDL\") (collectively, the\n" +
//"# \"License\"). You may not use this file except in compliance with the\n" +
//"# License. You can obtain a copy of the License at\n" +
//"# http://www.netbeans.org/cddl-gplv2.html\n" +
//"# or nbbuild/licenses/CDDL-GPL-2-CP. See the License for the\n" +
//"# specific language governing permissions and limitations under the\n" +
//"# License.  When distributing the software, include this License Header\n" +
//"# Notice in each file and include the License file at\n" +
//"# nbbuild/licenses/CDDL-GPL-2-CP.  Sun designates this\n" +
//"# particular file as subject to the \"Classpath\" exception as provided\n" +
//"# by Sun in the GPL Version 2 section of the License file that\n" +
//"# accompanied this code. If applicable, add the following below the\n" +
//"# License Header, with the fields enclosed by brackets [] replaced by\n" +
//"# your own identifying information:\n" +
//"# \"Portions Copyrighted [year] [name of copyright owner]\"\n" +
//"#\n" +
//"# If you wish your version of this file to be governed by only the CDDL\n" +
//"# or only the GPL Version 2, indicate your decision by adding\n" +
//"# \"[Contributor] elects to include this software in this distribution\n" +
//"# under the [CDDL or GPL Version 2] license.\" If you do not indicate a\n" +
//"# single choice of license, a recipient has the option to distribute\n" +
//"# your version of this file under either the CDDL, the GPL Version 2 or\n" +
//"# to extend the choice of license to its licensees as provided above.\n" +
//"# However, if you add GPL Version 2 code and therefore, elected the GPL\n" +
//"# Version 2 license, then the option applies only if the new code is\n" +
//"# made subject to such option by the copyright holder.\n" +
//"#\n" +
//"# Contributor(s):\n" +
//"#\n" +
//"# Portions Copyrighted 2010 Sun Microsystems, Inc.";

//    private static final String INPUT =
//"# DO NOT ALTER OR REMOVE COPYRIGHT NOTICES OR THIS HEADER.\n" +
//"#\n" +
//"# Copyright 2010 Oracle and/or its affiliates. All rights reserved.\n" +
//"#\n" +
//"# Oracle and Java are registered trademarks of Oracle and/or its affiliates.\n" +
//"# Other names may be trademarks of their respective owners.\n" +
//"#\n" +
//"# The contents of this file are subject to the terms of either the GNU\n" +
//"# General Public License Version 2 only (\"GPL\") or the Common\n" +
//"# Development and Distribution License(\"CDDL\") (collectively, the\n" +
//"# \"License\"). You may not use this file except in compliance with the\n" +
//"# License. You can obtain a copy of the License at\n" +
//"# http://www.netbeans.org/cddl-gplv2.html\n" +
//"# or nbbuild/licenses/CDDL-GPL-2-CP. See the License for the\n" +
//"# specific language governing permissions and limitations under the\n" +
//"# License.  When distributing the software, include this License Header\n" +
//"# Notice in each file and include the License file at\n" +
//"# nbbuild/licenses/CDDL-GPL-2-CP.  Oracle designates this\n" +
//"# particular file as subject to the \"Classpath\" exception as provided\n" +
//"# by Oracle in the GPL Version 2 section of the License file that\n" +
//"# accompanied this code. If applicable, add the following below the\n" +
//"# License Header, with the fields enclosed by brackets [] replaced by\n" +
//"# your own identifying information:\n" +
//"# \"Portions Copyrighted [year] [name of copyright owner]\"\n" +
//"#\n" +
//"# Contributor(s):\n" +
//"#\n" +
//"# The Original Software is NetBeans. The Initial Developer of the Original\n" +
//"# Software is Sun Microsystems, Inc. Portions Copyright 2010 Sun\n" +
//"# Microsystems, Inc. All Rights Reserved.\n" +
//"#\n" +
//"# If you wish your version of this file to be governed by only the CDDL\n" +
//"# or only the GPL Version 2, indicate your decision by adding\n" +
//"# \"[Contributor] elects to include this software in this distribution\n" +
//"# under the [CDDL or GPL Version 2] license.\" If you do not indicate a\n" +
//"# single choice of license, a recipient has the option to distribute\n" +
//"# your version of this file under either the CDDL, the GPL Version 2 or\n" +
//"# to extend the choice of license to its licensees as provided above.\n" +
//"# However, if you add GPL Version 2 code and therefore, elected the GPL\n" +
//"# Version 2 license, then the option applies only if the new code is\n" +
//"# made subject to such option by the copyright holder.";

    private static final String OUTPUT =
"# Licensed to the Apache Software Foundation (ASF) under one or more\n" +
"# contributor license agreements.  See the NOTICE file distributed with\n" +
"# this work for additional information regarding copyright ownership.\n" +
"# The ASF licenses this file to You under the Apache License, Version 2.0\n" +
"# (the \"License\"); you may not use this file except in compliance with\n" +
"# the License.  You may obtain a copy of the License at\n" +
"#\n" +
"# http://www.apache.org/licenses/LICENSE-2.0\n" +
"#\n" +
"# Unless required by applicable law or agreed to in writing, software\n" +
"# distributed under the License is distributed on an \"AS IS\" BASIS,\n" +
"# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.\n" +
"# See the License for the specific language governing permissions and\n" +
"# limitations under the License.\n";

//    private static final String INPUT =
//"<!--\n" +
//"DO NOT ALTER OR REMOVE COPYRIGHT NOTICES OR THIS HEADER.\n" +
//"\n" +
//"Copyright 2009-2017 Oracle and/or its affiliates. All rights reserved.\n" +
//"\n" +
//"Oracle and Java are registered trademarks of Oracle and/or its affiliates.\n" +
//"Other names may be trademarks of their respective owners.\n" +
//"\n" +
//"The contents of this file are subject to the terms of either the GNU\n" +
//"General Public License Version 2 only (\"GPL\") or the Common\n" +
//"Development and Distribution License(\"CDDL\") (collectively, the\n" +
//"\"License\"). You may not use this file except in compliance with the\n" +
//"License. You can obtain a copy of the License at\n" +
//"http://www.netbeans.org/cddl-gplv2.html\n" +
//"or nbbuild/licenses/CDDL-GPL-2-CP. See the License for the\n" +
//"specific language governing permissions and limitations under the\n" +
//"License.  When distributing the software, include this License Header\n" +
//"Notice in each file and include the License file at\n" +
//"nbbuild/licenses/CDDL-GPL-2-CP.  Oracle designates this\n" +
//"particular file as subject to the \"Classpath\" exception as provided\n" +
//"by Oracle in the GPL Version 2 section of the License file that\n" +
//"accompanied this code. If applicable, add the following below the\n" +
//"License Header, with the fields enclosed by brackets [] replaced by\n" +
//"your own identifying information:\n" +
//"\"Portions Copyrighted [year] [name of copyright owner]\"\n" +
//"\n" +
//"Contributor(s):\n" +
//"\n" +
//"The Original Software is NetBeans. The Initial Developer of the Original\n" +
//"Software is Sun Microsystems, Inc. Portions Copyright 2009-2010 Sun\n" +
//"Microsystems, Inc. All Rights Reserved.\n" +
//"\n" +
//"If you wish your version of this file to be governed by only the CDDL\n" +
//"or only the GPL Version 2, indicate your decision by adding\n" +
//"\"[Contributor] elects to include this software in this distribution\n" +
//"under the [CDDL or GPL Version 2] license.\" If you do not indicate a\n" +
//"single choice of license, a recipient has the option to distribute\n" +
//"your version of this file under either the CDDL, the GPL Version 2 or\n" +
//"to extend the choice of license to its licensees as provided above.\n" +
//"However, if you add GPL Version 2 code and therefore, elected the GPL\n" +
//"Version 2 license, then the option applies only if the new code is\n" +
//"made subject to such option by the copyright holder.\n" +
//"-->";
//
////    private static final String INPUT =
////"<!--\n" +
////"DO NOT ALTER OR REMOVE COPYRIGHT NOTICES OR THIS HEADER.\n" +
////"\n" +
////"Copyright 1997-2009 Sun Microsystems, Inc. All rights reserved.\n" +
////"\n" +
////"\n" +
////"The contents of this file are subject to the terms of either the GNU\n" +
////"General Public License Version 2 only (\"GPL\") or the Common\n" +
////"Development and Distribution License(\"CDDL\") (collectively, the\n" +
////"\"License\"). You may not use this file except in compliance with the\n" +
////"License. You can obtain a copy of the License at\n" +
////"http://www.netbeans.org/cddl-gplv2.html\n" +
////"or nbbuild/licenses/CDDL-GPL-2-CP. See the License for the\n" +
////"specific language governing permissions and limitations under the\n" +
////"License.  When distributing the software, include this License Header\n" +
////"Notice in each file and include the License file at\n" +
////"nbbuild/licenses/CDDL-GPL-2-CP.  Sun designates this\n" +
////"particular file as subject to the \"Classpath\" exception as provided\n" +
////"by Sun in the GPL Version 2 section of the License file that\n" +
////"accompanied this code. If applicable, add the following below the\n" +
////"License Header, with the fields enclosed by brackets [] replaced by\n" +
////"your own identifying information:\n" +
////"\"Portions Copyrighted [year] [name of copyright owner]\"\n" +
////"\n" +
////"Contributor(s):\n" +
////"\n" +
////"The Original Software is NetBeans. The Initial Developer of the Original\n" +
////"Software is Sun Microsystems, Inc. Portions Copyright 1997-2009 Sun\n" +
////"Microsystems, Inc. All Rights Reserved.\n" +
////"\n" +
////"If you wish your version of this file to be governed by only the CDDL\n" +
////"or only the GPL Version 2, indicate your decision by adding\n" +
////"\"[Contributor] elects to include this software in this distribution\n" +
////"under the [CDDL or GPL Version 2] license.\" If you do not indicate a\n" +
////"single choice of license, a recipient has the option to distribute\n" +
////"your version of this file under either the CDDL, the GPL Version 2 or\n" +
////"to extend the choice of license to its licensees as provided above.\n" +
////"However, if you add GPL Version 2 code and therefore, elected the GPL\n" +
////"Version 2 license, then the option applies only if the new code is\n" +
////"made subject to such option by the copyright holder.\n" +
////"-->";
//
//    private static final String OUTPUT =
//"<!--\n" +
//"\n" +
//"  Licensed to the Apache Software Foundation (ASF) under one or more\n" +
//"  contributor license agreements.  See the NOTICE file distributed with\n" +
//"  this work for additional information regarding copyright ownership.\n" +
//"  The ASF licenses this file to You under the Apache License, Version 2.0\n" +
//"  (the \"License\"); you may not use this file except in compliance with\n" +
//"  the License.  You may obtain a copy of the License at\n" +
//"\n" +
//"  http://www.apache.org/licenses/LICENSE-2.0\n" +
//"\n" +
//"  Unless required by applicable law or agreed to in writing, software\n" +
//"  distributed under the License is distributed on an \"AS IS\" BASIS,\n" +
//"  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.\n" +
//"  See the License for the specific language governing permissions and\n" +
//"  limitations under the License.\n" +
//"-->";

////    private static final String INPUT =
////"/*\n" +
////" * DO NOT ALTER OR REMOVE COPYRIGHT NOTICES OR THIS HEADER.\n" +
////" *\n" +
////" * Copyright 2010 Oracle and/or its affiliates. All rights reserved.\n" +
////" *\n" +
////" * Oracle and Java are registered trademarks of Oracle and/or its affiliates.\n" +
////" * Other names may be trademarks of their respective owners.\n" +
////" *\n" +
////" * The contents of this file are subject to the terms of either the GNU\n" +
////" * General Public License Version 2 only (\"GPL\") or the Common\n" +
////" * Development and Distribution License(\"CDDL\") (collectively, the\n" +
////" * \"License\"). You may not use this file except in compliance with the\n" +
////" * License. You can obtain a copy of the License at\n" +
////" * http://www.netbeans.org/cddl-gplv2.html\n" +
////" * or nbbuild/licenses/CDDL-GPL-2-CP. See the License for the\n" +
////" * specific language governing permissions and limitations under the\n" +
////" * License.  When distributing the software, include this License Header\n" +
////" * Notice in each file and include the License file at\n" +
////" * nbbuild/licenses/CDDL-GPL-2-CP.  Oracle designates this\n" +
////" * particular file as subject to the \"Classpath\" exception as provided\n" +
////" * by Oracle in the GPL Version 2 section of the License file that\n" +
////" * accompanied this code. If applicable, add the following below the\n" +
////" * License Header, with the fields enclosed by brackets [] replaced by\n" +
////" * your own identifying information:\n" +
////" * \"Portions Copyrighted [year] [name of copyright owner]\"\n" +
////" *\n" +
////" * Contributor(s):\n" +
////" *\n" +
////" * Portions Copyrighted 2010 Sun Microsystems, Inc.\n" +
////" */";
//
////    private static final String INPUT =
////"/*\n" +
////" * DO NOT ALTER OR REMOVE COPYRIGHT NOTICES OR THIS HEADER.\n" +
////" *\n" +
////" * Copyright 2010 Oracle and/or its affiliates. All rights reserved.\n" +
////" *\n" +
////" * Oracle and Java are registered trademarks of Oracle and/or its affiliates.\n" +
////" * Other names may be trademarks of their respective owners.\n" +
////" *\n" +
////" * The contents of this file are subject to the terms of either the GNU General\n" +
////" * Public License Version 2 only (\"GPL\") or the Common Development and\n" +
////" * Distribution License(\"CDDL\") (collectively, the \"License\"). You may not use\n" +
////" * this file except in compliance with the License. You can obtain a copy of the\n" +
////" * License at http://www.netbeans.org/cddl-gplv2.html or\n" +
////" * nbbuild/licenses/CDDL-GPL-2-CP. See the License for the specific language\n" +
////" * governing permissions and limitations under the License. When distributing\n" +
////" * the software, include this License Header Notice in each file and include the\n" +
////" * License file at nbbuild/licenses/CDDL-GPL-2-CP. Oracle designates this\n" +
////" * particular file as subject to the \"Classpath\" exception as provided by Oracle\n" +
////" * in the GPL Version 2 section of the License file that accompanied this code.\n" +
////" * If applicable, add the following below the License Header, with the fields\n" +
////" * enclosed by brackets [] replaced by your own identifying information:\n" +
////" * \"Portions Copyrighted [year] [name of copyright owner]\"\n" +
////" *\n" +
////" * If you wish your version of this file to be governed by only the CDDL or only\n" +
////" * the GPL Version 2, indicate your decision by adding \"[Contributor] elects to\n" +
////" * include this software in this distribution under the [CDDL or GPL Version 2]\n" +
////" * license.\" If you do not indicate a single choice of license, a recipient has\n" +
////" * the option to distribute your version of this file under either the CDDL, the\n" +
////" * GPL Version 2 or to extend the choice of license to its licensees as provided\n" +
////" * above. However, if you add GPL Version 2 code and therefore, elected the GPL\n" +
////" * Version 2 license, then the option applies only if the new code is made\n" +
////" * subject to such option by the copyright holder.\n" +
////" *\n" +
////" * Contributor(s):\n" +
////" *\n" +
////" * Portions Copyrighted 2010 Sun Microsystems, Inc.\n" +
////" */";
//
////    private static final String INPUT =
////"/*\n" +
////" * DO NOT ALTER OR REMOVE COPYRIGHT NOTICES OR THIS HEADER.\n" +
////" *\n" +
////" * Copyright 2010 Sun Microsystems, Inc. All rights reserved.\n" +
////" *\n" +
////" * The contents of this file are subject to the terms of either the GNU\n" +
////" * General Public License Version 2 only (\"GPL\") or the Common\n" +
////" * Development and Distribution License(\"CDDL\") (collectively, the\n" +
////" * \"License\"). You may not use this file except in compliance with the\n" +
////" * License. You can obtain a copy of the License at\n" +
////" * http://www.netbeans.org/cddl-gplv2.html\n" +
////" * or nbbuild/licenses/CDDL-GPL-2-CP. See the License for the\n" +
////" * specific language governing permissions and limitations under the\n" +
////" * License.  When distributing the software, include this License Header\n" +
////" * Notice in each file and include the License file at\n" +
////" * nbbuild/licenses/CDDL-GPL-2-CP.  Sun designates this\n" +
////" * particular file as subject to the \"Classpath\" exception as provided\n" +
////" * by Sun in the GPL Version 2 section of the License file that\n" +
////" * accompanied this code. If applicable, add the following below the\n" +
////" * License Header, with the fields enclosed by brackets [] replaced by\n" +
////" * your own identifying information:\n" +
////" * \"Portions Copyrighted [year] [name of copyright owner]\"\n" +
////" *\n" +
////" * Contributor(s):\n" +
////" *\n" +
////" * The Original Software is NetBeans. The Initial Developer of the Original\n" +
////" * Software is Sun Microsystems, Inc. Portions Copyright 2010 Sun\n" +
////" * Microsystems, Inc. All Rights Reserved.\n" +
////" *\n" +
////" * If you wish your version of this file to be governed by only the CDDL\n" +
////" * or only the GPL Version 2, indicate your decision by adding\n" +
////" * \"[Contributor] elects to include this software in this distribution\n" +
////" * under the [CDDL or GPL Version 2] license.\" If you do not indicate a\n" +
////" * single choice of license, a recipient has the option to distribute\n" +
////" * your version of this file under either the CDDL, the GPL Version 2 or\n" +
////" * to extend the choice of license to its licensees as provided above.\n" +
////" * However, if you add GPL Version 2 code and therefore, elected the GPL\n" +
////" * Version 2 license, then the option applies only if the new code is\n" +
////" * made subject to such option by the copyright holder.\n" +
////" */";
//
////    private static final String INPUT =
////"/*\n" +
////" * DO NOT ALTER OR REMOVE COPYRIGHT NOTICES OR THIS HEADER.\n" +
////" *\n" +
////" * Copyright 2010 Sun Microsystems, Inc. All rights reserved.\n" +
////" *\n" +
////" * The contents of this file are subject to the terms of either the GNU\n" +
////" * General Public License Version 2 only (\"GPL\") or the Common\n" +
////" * Development and Distribution License(\"CDDL\") (collectively, the\n" +
////" * \"License\"). You may not use this file except in compliance with the\n" +
////" * License. You can obtain a copy of the License at\n" +
////" * http://www.netbeans.org/cddl-gplv2.html\n" +
////" * or nbbuild/licenses/CDDL-GPL-2-CP. See the License for the\n" +
////" * specific language governing permissions and limitations under the\n" +
////" * License.  When distributing the software, include this License Header\n" +
////" * Notice in each file and include the License file at\n" +
////" * nbbuild/licenses/CDDL-GPL-2-CP.  Sun designates this\n" +
////" * particular file as subject to the \"Classpath\" exception as provided\n" +
////" * by Sun in the GPL Version 2 section of the License file that\n" +
////" * accompanied this code. If applicable, add the following below the\n" +
////" * License Header, with the fields enclosed by brackets [] replaced by\n" +
////" * your own identifying information:\n" +
////" * \"Portions Copyrighted [year] [name of copyright owner]\"\n" +
////" *\n" +
////" * Contributor(s):\n" +
////" *\n" +
////" * Portions Copyrighted 2010 Sun Microsystems, Inc.\n" +
////" */";
//
////    private static final String INPUT =
////"/*\n" +
////" * DO NOT ALTER OR REMOVE COPYRIGHT NOTICES OR THIS HEADER.\n" +
////" *\n" +
////" * Copyright 2010 Oracle and/or its affiliates. All rights reserved.\n" +
////" *\n" +
////" * Oracle and Java are registered trademarks of Oracle and/or its affiliates.\n" +
////" * Other names may be trademarks of their respective owners.\n" +
////" *\n" +
////" * The contents of this file are subject to the terms of either the GNU\n" +
////" * General Public License Version 2 only (\"GPL\") or the Common\n" +
////" * Development and Distribution License(\"CDDL\") (collectively, the\n" +
////" * \"License\"). You may not use this file except in compliance with the\n" +
////" * License. You can obtain a copy of the License at\n" +
////" * http://www.netbeans.org/cddl-gplv2.html\n" +
////" * or nbbuild/licenses/CDDL-GPL-2-CP. See the License for the\n" +
////" * specific language governing permissions and limitations under the\n" +
////" * License.  When distributing the software, include this License Header\n" +
////" * Notice in each file and include the License file at\n" +
////" * nbbuild/licenses/CDDL-GPL-2-CP.  Oracle designates this\n" +
////" * particular file as subject to the \"Classpath\" exception as provided\n" +
////" * by Oracle in the GPL Version 2 section of the License file that\n" +
////" * accompanied this code. If applicable, add the following below the\n" +
////" * License Header, with the fields enclosed by brackets [] replaced by\n" +
////" * your own identifying information:\n" +
////" * \"Portions Copyrighted [year] [name of copyright owner]\"\n" +
////" *\n" +
////" * Contributor(s):\n" +
////" *\n" +
////" * The Original Software is NetBeans. The Initial Developer of the Original\n" +
////" * Software is Sun Microsystems, Inc. Portions Copyright 2010 Sun\n" +
////" * Microsystems, Inc. All Rights Reserved.\n" +
////" *\n" +
////" * If you wish your version of this file to be governed by only the CDDL\n" +
////" * or only the GPL Version 2, indicate your decision by adding\n" +
////" * \"[Contributor] elects to include this software in this distribution\n" +
////" * under the [CDDL or GPL Version 2] license.\" If you do not indicate a\n" +
////" * single choice of license, a recipient has the option to distribute\n" +
////" * your version of this file under either the CDDL, the GPL Version 2 or\n" +
////" * to extend the choice of license to its licensees as provided above.\n" +
////" * However, if you add GPL Version 2 code and therefore, elected the GPL\n" +
////" * Version 2 license, then the option applies only if the new code is\n" +
////" * made subject to such option by the copyright holder.\n" +
////" */";
////    private static final String INPUT =
////"/*\n" +
////" * DO NOT ALTER OR REMOVE COPYRIGHT NOTICES OR THIS HEADER.\n" +
////" *\n" +
////" * Copyright 2010 Oracle and/or its affiliates. All rights reserved.\n" +
////" *\n" +
////" * Oracle and Java are registered trademarks of Oracle and/or its affiliates.\n" +
////" * Other names may be trademarks of their respective owners.\n" +
////" *\n" +
////" * The contents of this file are subject to the terms of either the GNU\n" +
////" * General Public License Version 2 only (\"GPL\") or the Common\n" +
////" * Development and Distribution License(\"CDDL\") (collectively, the\n" +
////" * \"License\"). You may not use this file except in compliance with the\n" +
////" * License. You can obtain a copy of the License at\n" +
////" * http://www.netbeans.org/cddl-gplv2.html\n" +
////" * or nbbuild/licenses/CDDL-GPL-2-CP. See the License for the\n" +
////" * specific language governing permissions and limitations under the\n" +
////" * License.  When distributing the software, include this License Header\n" +
////" * Notice in each file and include the License file at\n" +
////" * nbbuild/licenses/CDDL-GPL-2-CP.  Oracle designates this\n" +
////" * particular file as subject to the \"Classpath\" exception as provided\n" +
////" * by Oracle in the GPL Version 2 section of the License file that\n" +
////" * accompanied this code. If applicable, add the following below the\n" +
////" * License Header, with the fields enclosed by brackets [] replaced by\n" +
////" * your own identifying information:\n" +
////" * \"Portions Copyrighted [year] [name of copyright owner]\"\n" +
////" *\n" +
////" * If you wish your version of this file to be governed by only the CDDL\n" +
////" * or only the GPL Version 2, indicate your decision by adding\n" +
////" * \"[Contributor] elects to include this software in this distribution\n" +
////" * under the [CDDL or GPL Version 2] license.\" If you do not indicate a\n" +
////" * single choice of license, a recipient has the option to distribute\n" +
////" * your version of this file under either the CDDL, the GPL Version 2 or\n" +
////" * to extend the choice of license to its licensees as provided above.\n" +
////" * However, if you add GPL Version 2 code and therefore, elected the GPL\n" +
////" * Version 2 license, then the option applies only if the new code is\n" +
////" * made subject to such option by the copyright holder.\n" +
////" *\n" +
////" * Contributor(s):\n" +
////" *\n" +
////" * Portions Copyrighted 2010 Sun Microsystems, Inc.\n" +
////" */";
//
////    private static final String INPUT =
////"/*\n" +
////" * DO NOT ALTER OR REMOVE COPYRIGHT NOTICES OR THIS HEADER.\n" +
////" *\n" +
////" * Copyright 2010 Sun Microsystems, Inc. All rights reserved.\n" +
////" *\n" +
////" * The contents of this file are subject to the terms of either the GNU\n" +
////" * General Public License Version 2 only (\"GPL\") or the Common\n" +
////" * Development and Distribution License(\"CDDL\") (collectively, the\n" +
////" * \"License\"). You may not use this file except in compliance with the\n" +
////" * License. You can obtain a copy of the License at\n" +
////" * http://www.netbeans.org/cddl-gplv2.html\n" +
////" * or nbbuild/licenses/CDDL-GPL-2-CP. See the License for the\n" +
////" * specific language governing permissions and limitations under the\n" +
////" * License.  When distributing the software, include this License Header\n" +
////" * Notice in each file and include the License file at\n" +
////" * nbbuild/licenses/CDDL-GPL-2-CP.  Sun designates this\n" +
////" * particular file as subject to the \"Classpath\" exception as provided\n" +
////" * by Sun in the GPL Version 2 section of the License file that\n" +
////" * accompanied this code. If applicable, add the following below the\n" +
////" * License Header, with the fields enclosed by brackets [] replaced by\n" +
////" * your own identifying information:\n" +
////" * \"Portions Copyrighted [year] [name of copyright owner]\"\n" +
////" *\n" +
////" * If you wish your version of this file to be governed by only the CDDL\n" +
////" * or only the GPL Version 2, indicate your decision by adding\n" +
////" * \"[Contributor] elects to include this software in this distribution\n" +
////" * under the [CDDL or GPL Version 2] license.\" If you do not indicate a\n" +
////" * single choice of license, a recipient has the option to distribute\n" +
////" * your version of this file under either the CDDL, the GPL Version 2 or\n" +
////" * to extend the choice of license to its licensees as provided above.\n" +
////" * However, if you add GPL Version 2 code and therefore, elected the GPL\n" +
////" * Version 2 license, then the option applies only if the new code is\n" +
////" * made subject to such option by the copyright holder.\n" +
////" *\n" +
////" * Contributor(s):\n" +
////" *\n" +
////" * Portions Copyrighted 2010 Sun Microsystems, Inc.\n" +
////" */";
//    private static final String OUTPUT =
//"/**\n" +
//" * Licensed to the Apache Software Foundation (ASF) under one\n" +
//" * or more contributor license agreements.  See the NOTICE file\n" +
//" * distributed with this work for additional information\n" +
//" * regarding copyright ownership.  The ASF licenses this file\n" +
//" * to you under the Apache License, Version 2.0 (the\n" +
//" * \"License\"); you may not use this file except in compliance\n" +
//" * with the License.  You may obtain a copy of the License at\n" +
//" *\n" +
//" *   http://www.apache.org/licenses/LICENSE-2.0\n" +
//" *\n" +
//" * Unless required by applicable law or agreed to in writing,\n" +
//" * software distributed under the License is distributed on an\n" +
//" * \"AS IS\" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY\n" +
//" * KIND, either express or implied.  See the License for the\n" +
//" * specific language governing permissions and limitations\n" +
//" * under the License.\n" +
//" */";

    public static void main(String[] args) throws IOException {
        StringBuilder pattern = new StringBuilder();
        
        for (String piece : INPUT.split("2010")) {
            if (pattern.length() != 0) {
                pattern.append("[0-9][0-9][0-9][0-9](-[0-9][0-9][0-9][0-9])?");
            }
            pattern.append(Pattern.quote(piece));
        }
        
        Pattern inputPattern = Pattern.compile(pattern.toString());
//        Pattern inputPattern = Pattern.compile(Pattern.quote(INPUT), Pattern.MULTILINE);
        
        Path root = Paths.get("/home/lahvac/src/nb/apache-jackpot30/");
//        Path root = Paths.get("/home/lahvac/src/nb/apache-jackpot30/cmdline/compiler/antsrc/org/netbeans/modules/jackpot30/compiler/ant/JackpotCompiler.java");
        
        Files.find(root, Integer.MAX_VALUE, (p, attr) -> attr.isRegularFile())
             .forEach(p -> {
                try {
                    Matcher m = inputPattern.matcher(new String(Files.readAllBytes(p)));
                    if (m.find()) {
                        try (Writer w = Files.newBufferedWriter(p)) {
                            w.write(m.replaceAll(Matcher.quoteReplacement(OUTPUT)));
                        }
                    }
                } catch (IOException ex) {
                    ex.printStackTrace();
                }
             });
    }
    
}
