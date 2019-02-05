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
['release90','9.0-vc3', True,jdk8,maven339,ant10,'1.4-SNAPSHOT','RELEASE90', 'http://bits.netbeans.org/9.0/javadoc', datetime(2018,07,29,12,00)],
['release100','10.0-vc5',True,jdk8,maven339,ant10,'1.4-SNAPSHOT','RELEASE100','http://bits.netbeans.org/10.0/javadoc',datetime(2018,12,27,12,00)],
##release 111
['master','', True,jdk8,maven339,ant10,'1.4-SNAPSHOT','dev-SNAPSHOT']] ## no need custom info

def write_pipelinebasic(afile,scm,jdktool,maventool,anttool):  
  afile.write("pipeline {\n")
  afile.write("   agent  { label 'ubuntu' }\n")
  afile.write("   tools {\n")
  afile.write("      maven '"+maventool+"'\n")
  afile.write("      jdk '"+jdktool+"'\n") 
  afile.write("   }\n")
  afile.write("   stages {\n")
  afile.write("      stage('Informations') {\n")
  afile.write("          steps {\n")
  afile.write("              echo "+'"'+'Branche we are building is : '+scm+'"'+"\n")
  afile.write("          }\n")
  afile.write("      }\n")

def write_pipelinecheckout(afile,scm):
  afile.write("      stage('SCM operation') {\n") 
  afile.write("          steps {\n")
  afile.write("              echo 'clean up netbeans sources'\n")
  afile.write("              sh 'rm -rf netbeanssources'\n")
  afile.write("              echo 'Get NetBeans sources'\n")
  afile.write("              checkout([$class: 'GitSCM', branches: [[name: '"+scm+"']], doGenerateSubmoduleConfigurations: false, extensions: [[$class: 'CloneOption', noTags: true, reference: '', shallow: true], [$class: 'MessageExclusion', excludedMessage: 'Automated site publishing.*'], [$class: 'RelativeTargetDirectory', relativeTargetDir: 'netbeanssources']], submoduleCfg: [], userRemoteConfigs: [[url: 'https://github.com/apache/incubator-netbeans/']]])\n") 
  afile.write("          }\n")
  afile.write("      }\n")

def write_pipelineclose(afile):
  afile.write("   }\n")
  afile.write("}\n")
  afile.close

##for each release generate a 


for arelease in releaseinfo:
  branch='refs/heads/'+arelease[0]
  if branch=='refs/heads/master':
    tag=branch
  else:
    tag='refs/tags/'+arelease[1]
  jdktool=arelease[3]
  maventool=arelease[4]
  anttool=arelease[5]
  apidocbuildFile = open ('Jenkinsfile-'+arelease[0]+'.groovy',"w")
  mavenbuildfile = open ('Jenkinsfile-maven-'+arelease[0]+'.groovy',"w")
  write_pipelinebasic(apidocbuildFile,branch,jdktool,maventool,anttool)
  write_pipelinebasic(mavenbuildfile,tag,jdktool,maventool,anttool)
  
## needed until we had mavenutil ready
##prepare nb-repository from master to populate
  if arelease[2] == True:
     mavenbuildfile.write("      stage('mavenutils preparation') {\n")
     mavenbuildfile.write("          // this stage is temporary\n")
     mavenbuildfile.write("          steps {\n")
     mavenbuildfile.write("              echo 'Get Mavenutils sources'\n")
     mavenbuildfile.write("              sh 'rm -rf mavenutils'\n")
     mavenbuildfile.write("              checkout([$class: 'GitSCM', branches: [[name: '*/master']], doGenerateSubmoduleConfigurations: false, extensions: [[$class: 'CloneOption', noTags: true, reference: '', shallow: true], [$class: 'MessageExclusion', excludedMessage: 'Automated site publishing.*'], [$class: 'RelativeTargetDirectory', relativeTargetDir: 'mavenutils']], submoduleCfg: [], userRemoteConfigs: [[url: 'https://github.com/apache/incubator-netbeans-mavenutils/']]])\n")
     mavenbuildfile.write("              script {\n")
     mavenbuildfile.write("                 def mvnfoldersforsite  = ['parent','nbm-shared','nb-repository-plugin']\n");
     mavenbuildfile.write("                 for (String mvnproject in mvnfoldersforsite) {\n")
     mavenbuildfile.write("                     dir('mavenutils/'+mvnproject) {\n")
     mavenbuildfile.write("                        sh "+'"'+'mvn clean install -Dmaven.repo.local=${env.WORKSPACE}/.repository'+'"'+"\n")
     mavenbuildfile.write("                     }\n")
     mavenbuildfile.write("                 }\n")
     mavenbuildfile.write("              }\n")
     mavenbuildfile.write("          }\n")
     mavenbuildfile.write("      }\n")
  
  write_pipelinecheckout(apidocbuildFile,branch)
  write_pipelinecheckout(mavenbuildfile,tag) 
## apidoc path do only build for javadoc   
## build netbeans all needed for javadoc and nb-repository plugin
  apidocbuildFile.write("      stage('NetBeans Builds') {\n")
  apidocbuildFile.write("          steps {\n")
  apidocbuildFile.write("              dir ('netbeanssources'){\n")
  apidocbuildFile.write("                  withAnt(installation: '"+anttool+"') {\n")
  apidocbuildFile.write("                      sh 'ant'\n")
## master use default parameter
  if branch=='refs/heads/master':
      apidocbuildFile.write("                      sh "+'"'+"ant build-javadoc -Djavadoc.web.zip=${env.WORKSPACE}/WEBZIP.zip"+'"'+"\n")
  else:
      locale.setlocale(locale.LC_ALL,"en_US.utf8")
##URL for javadoc
      javadocwebroot = arelease[8]
##date for javadoc and for feed
      javadocdate = arelease[9].strftime('%-d %b %Y')
      atomdate = arelease[9].strftime('%Y-%m-%dT%H:%M:%SZ')
      apidocbuildFile.write("                      sh "+'"'+"ant build-javadoc -Djavadoc.web.root='"+javadocwebroot+"' -Dmodules-javadoc-date='"+javadocdate+"' -Datom-date='"+atomdate+"' -Djavadoc.web.zip=${env.WORKSPACE}/WEBZIP.zip"+'"'+"\n")
  apidocbuildFile.write("                  }\n")
  apidocbuildFile.write("              }\n")
  apidocbuildFile.write("              archiveArtifacts 'WEBZIP.zip'\n")
  apidocbuildFile.write("            }\n")
  apidocbuildFile.write("      }\n")

## build artefacts for maven
  mavenbuildfile.write("      stage('NetBeans Builds') {\n")
  mavenbuildfile.write("          steps {\n")
  mavenbuildfile.write("              dir ('netbeanssources'){\n")
  mavenbuildfile.write("                  withAnt(installation: '"+anttool+"') {\n")
  mavenbuildfile.write("                      sh 'ant'\n")
  mavenbuildfile.write("                      sh 'ant build-javadoc'\n")
  mavenbuildfile.write("                      sh 'ant build-source-zips'\n")
  mavenbuildfile.write("                      sh 'ant build-nbms'\n")
  mavenbuildfile.write("                  }\n")
  mavenbuildfile.write("              }\n")
  mavenbuildfile.write("              archiveArtifacts 'WEBZIP.zip'\n")
  mavenbuildfile.write("              archiveArtifacts 'netbeanssources/nbbuild/netbeans/**'\n")
  mavenbuildfile.write("              archiveArtifacts 'netbeanssources/nbbuild/build/source-zips/**'\n")
  mavenbuildfile.write("              archiveArtifacts 'netbeanssources/nbbuild/build/javadoc/**'\n")
  mavenbuildfile.write("              archiveArtifacts 'netbeanssources/nbbuild/nbms/**'\n")
  mavenbuildfile.write("            }\n")
  mavenbuildfile.write("      }\n")

#prepare maven artifacts
  mavenbuildfile.write("      stage('NetBeans Maven Stage') {\n")
  mavenbuildfile.write("          steps {\n")
  mavenbuildfile.write("              script {\n")
  nbbuildpath = "${env.WORKSPACE}/netbeanssources/nbbuild"
  mavenbuildfile.write("                        sh "+'"'+'mvn org.netbeans.maven:nb-repository-plugin:'+arelease[6]+':download -DnexusIndexDirectory=${env.WORKSPACE}/repoindex -Dmaven.repo.local=${env.WORKSPACE}/.repository'+ ' -DrepositoryUrl=https://repo.maven.apache.org/maven2"'+"\n")
  mavenbuildfile.write("                        sh 'mkdir -p testrepo/.m2'\n")
  mavenbuildfile.write("                        sh "+'"'+'mvn org.netbeans.maven:nb-repository-plugin:'+arelease[6]+':populate -DnexusIndexDirectory=${env.WORKSPACE}/repoindex -DnetbeansNbmDirectory='+nbbuildpath+'/nbms -DnetbeansInstallDirectory='+nbbuildpath+'/netbeans -DnetbeansSourcesDirectory='+nbbuildpath+'/build/source-zips -DnebeansJavadocDirectory='+nbbuildpath+'/build/javadoc  -Dmaven.repo.local=${env.WORKSPACE}/.repository -DforcedVersion='+arelease[7]+' -DskipInstall=true -DdeployUrl=file://${env.WORKSPACE}/testrepo/.m2"'+"\n"
)
  mavenbuildfile.write("              }\n")
  mavenbuildfile.write("              archiveArtifacts 'testrepo/.m2/**'\n")
  mavenbuildfile.write("          }\n")
  mavenbuildfile.write("      }\n")
  
  
  write_pipelineclose(mavenbuildfile)
  write_pipelineclose(apidocbuildFile)


