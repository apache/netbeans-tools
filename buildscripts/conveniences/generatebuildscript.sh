#!/usr/bin/python

##Licensed to the Apache Software Foundation (ASF) under one
##or more contributor license agreements.  See the NOTICE file
##distributed with this work for additional information
##regarding copyright ownership.  The ASF licenses this file
##to you under the Apache License, Version 2.0 (the
##"License"); you may not use this file except in compliance
##with the License.  You may obtain a copy of the License at
##
##  http://www.apache.org/licenses/LICENSE-2.0
##
##Unless required by applicable law or agreed to in writing,
##software distributed under the License is distributed on an
##"AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
##KIND, either express or implied.  See the License for the
##specific language governing permissions and limitations
##under the License.

import locale
from datetime import datetime

## tools needed
maven339='Maven 3.3.9'
jdk8='JDK 1.8 (latest)'
ant10='Ant (latest)'

## information for each release (tools + date of release to flag the doc)
## pick tools that are available on ubuntu node on build.apache.org
releaseinfo=[
['release90', True,jdk8,maven339,ant10,'1.4-SNAPSHOT','RELEASE90', 'http://bits.netbeans.org/9.0/javadoc', datetime(2018,07,29,12,00)],
['release100',True,jdk8,maven339,ant10,'1.4-SNAPSHOT','RELEASE100','http://bits.netbeans.org/10.0/javadoc',datetime(2018,12, 4,12,00)],
##release 111
['master',True,jdk8,maven339,ant10,'1.4-SNAPSHOT','dev-SNAPSHOT']] ## no need custom info

##for each release generate a 
for arelease in releaseinfo:
  branch=arelease[0]
  jdktool=arelease[2]
  maventool=arelease[3]
  anttool=arelease[4]
  tmpFile1 = open ('Jenkinsfile-'+branch+'.groovy',"w")
  tmpFile1.write("pipeline {\n")
  tmpFile1.write("   agent  { label 'ubuntu' }\n")
  tmpFile1.write("   tools {\n")
  tmpFile1.write("      maven '"+maventool+"'\n")
  tmpFile1.write("      jdk '"+jdktool+"'\n") 
  tmpFile1.write("   }\n")
  tmpFile1.write("   stages {\n")
  tmpFile1.write("      stage('Informations') {\n")
  tmpFile1.write("          steps {\n")
  tmpFile1.write("              echo "+'"'+'Branche we are building is : '+branch+'"'+"\n")
  tmpFile1.write("          }\n")
  tmpFile1.write("      }\n")
## needed until we had mavenutil ready
##prepare nb-repository from master to populate
  if arelease[1] == True:
     tmpFile1.write("      stage('mavenutils preparation') {\n")
     tmpFile1.write("          // this stage is temporary\n")
     tmpFile1.write("          steps {\n")
     tmpFile1.write("              echo 'Get Mavenutils sources'\n")
     tmpFile1.write("              sh 'rm -rf mavenutils'\n")
     tmpFile1.write("              checkout([$class: 'GitSCM', branches: [[name: '*/master']], doGenerateSubmoduleConfigurations: false, extensions: [[$class: 'CloneOption', noTags: true, reference: '', shallow: true], [$class: 'MessageExclusion', excludedMessage: 'Automated site publishing.*'], [$class: 'RelativeTargetDirectory', relativeTargetDir: 'mavenutils']], submoduleCfg: [], userRemoteConfigs: [[url: 'https://github.com/apache/incubator-netbeans-mavenutils/']]])\n")
     tmpFile1.write("              script {\n")
     tmpFile1.write("                 def mvnfoldersforsite  = ['parent','nbm-shared','nb-repository-plugin']\n");
     tmpFile1.write("                 for (String mvnproject in mvnfoldersforsite) {\n")
     tmpFile1.write("                     dir('mavenutils/'+mvnproject) {\n")
     tmpFile1.write("                        sh "+'"'+'mvn clean install -Dmaven.repo.local=${env.WORKSPACE}/.repository'+'"'+"\n")
     tmpFile1.write("                     }\n")
     tmpFile1.write("                 }\n")
     tmpFile1.write("              }\n")
     tmpFile1.write("          }\n")
     tmpFile1.write("      }\n")
   
  tmpFile1.write("      stage('SCM operation') {\n") 
  tmpFile1.write("          steps {\n")
  tmpFile1.write("              echo 'clean up netbeans sources'\n")
  tmpFile1.write("              sh 'rm -rf netbeanssources'\n")
  tmpFile1.write("              echo 'Get NetBeans sources'\n")
  tmpFile1.write("              checkout([$class: 'GitSCM', branches: [[name: '*/"+branch+"']], doGenerateSubmoduleConfigurations: false, extensions: [[$class: 'CloneOption', noTags: true, reference: '', shallow: true], [$class: 'MessageExclusion', excludedMessage: 'Automated site publishing.*'], [$class: 'RelativeTargetDirectory', relativeTargetDir: 'netbeanssources']], submoduleCfg: [], userRemoteConfigs: [[url: 'https://github.com/apache/incubator-netbeans/']]])\n") 
  tmpFile1.write("          }\n")
  tmpFile1.write("      }\n")
## build netbeans all needed for javadoc and nb-repository plugin
  tmpFile1.write("      stage('NetBeans Builds') {\n")
  tmpFile1.write("          steps {\n")
  tmpFile1.write("              dir ('netbeanssources'){\n")
  tmpFile1.write("                  withAnt(installation: '"+anttool+"') {\n")
  tmpFile1.write("                      sh 'ant'\n")
## master use default parameter
  if branch=='master':
      tmpFile1.write("                      sh "+'"'+"ant build-javadoc -Djavadoc.web.zip=${env.WORKSPACE}/WEBZIP.zip"+'"'+"\n")
  else:
      locale.setlocale(locale.LC_ALL,"en_US.utf8")
##URL for javadoc
      javadocwebroot = arelease[6]
##date for javadoc and for feed
      javadocdate = arelease[8].strftime('%-d %b %Y')
      atomdate = arelease[8].strftime('%Y-%m-%dT%H:%M:%SZ')
      tmpFile1.write("                      sh "+'"'+"ant build-javadoc -Djavadoc.web.root='"+javadocwebroot+"' -Dmodules-javadoc-date='"+javadocdate+"' -Datom-date='"+atomdate+"' -Djavadoc.web.zip=${env.WORKSPACE}/WEBZIP.zip"+'"'+"\n")
  tmpFile1.write("                      sh 'ant build-source-zips'\n")
  tmpFile1.write("                      sh 'ant build-nbms'\n")
  tmpFile1.write("                  }\n")
  tmpFile1.write("              }\n")
  tmpFile1.write("              archiveArtifacts 'WEBZIP.zip'\n")
  tmpFile1.write("              archiveArtifacts 'netbeanssources/nbbuild/netbeans/**'\n")
  tmpFile1.write("              archiveArtifacts 'netbeanssources/nbbuild/build/source-zips/**'\n")
  tmpFile1.write("              archiveArtifacts 'netbeanssources/nbbuild/build/javadoc/**'\n")
  tmpFile1.write("              archiveArtifacts 'netbeanssources/nbbuild/nbms/**'\n")
  tmpFile1.write("            }\n")
  tmpFile1.write("      }\n")
#prepare maven artifacts
  tmpFile1.write("      stage('NetBeans Maven Stage') {\n")
  tmpFile1.write("          steps {\n")
  tmpFile1.write("              script {\n")
  nbbuildpath = "${env.WORKSPACE}/netbeanssources/nbbuild"
  tmpFile1.write("                        sh "+'"'+'mvn org.netbeans.maven:nb-repository-plugin:'+arelease[5]+':download -DnexusIndexDirectory=${env.WORKSPACE}/repoindex -Dmaven.repo.local=${env.WORKSPACE}/.repository'+ ' -DrepositoryURL=https://repo.maven.apache.org/maven2"'+"\n")
  tmpFile1.write("                        sh 'mkdir -p testrepo/.m2'\n")
  tmpFile1.write("                        sh "+'"'+'mvn org.netbeans.maven:nb-repository-plugin:'+arelease[5]+':populate -DnexusIndexDirectory=${env.WORKSPACE}/repoindex -DnetbeansNbmDirectory='+nbbuildpath+'/nbms -DnetbeansInstallDirectory='+nbbuildpath+'/netbeans -DnetbeansSourcesDirectory='+nbbuildpath+'/build/source-zips -DnebeansJavadocDirectory='+nbbuildpath+'/build/javadoc  -Dmaven.repo.local=${env.WORKSPACE}/.repository -DforcedVersion='+arelease[6]+' -DskipInstall=true -DdeployUrl=file://${env.WORKSPACE}/testrepo/.m2"'+"\n"
)
  tmpFile1.write("              }\n")
  tmpFile1.write("              archiveArtifacts 'testrepo/.m2/**'\n")
  tmpFile1.write("          }\n")
  tmpFile1.write("      }\n")
  tmpFile1.write("   }\n")
  tmpFile1.write("}\n")
  tmpFile1.close

