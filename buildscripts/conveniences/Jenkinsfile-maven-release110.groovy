pipeline {
   agent  { label 'ubuntu' }
   tools {
      maven 'Maven 3.3.9'
      jdk 'JDK 1.8 (latest)'
   }
   stages {
      stage('Informations') {
          steps {
              echo "Branche we are building is : refs/heads/release110"
          }
      }
      stage('mavenutils preparation') {
          // this stage is temporary
          steps {
              echo 'Get Mavenutils sources'
              sh 'rm -rf mavenutils'
              checkout([$class: 'GitSCM', branches: [[name: '*/master']], doGenerateSubmoduleConfigurations: false, extensions: [[$class: 'CloneOption', noTags: true, reference: '', shallow: true], [$class: 'MessageExclusion', excludedMessage: 'Automated site publishing.*'], [$class: 'RelativeTargetDirectory', relativeTargetDir: 'mavenutils']], submoduleCfg: [], userRemoteConfigs: [[url: 'https://github.com/apache/incubator-netbeans-mavenutils/']]])
              script {
                 def mvnfoldersforsite  = ['parent','nbm-shared','nb-repository-plugin']
                 for (String mvnproject in mvnfoldersforsite) {
                     dir('mavenutils/'+mvnproject) {
                        sh "mvn clean install -Dmaven.repo.local=${env.WORKSPACE}/.repository"
                     }
                 }
              }
          }
      }
      stage('SCM operation') {
          steps {
              echo 'clean up netbeans sources'
              sh 'rm -rf netbeanssources'
              echo 'Get NetBeans sources'
              checkout([$class: 'GitSCM', branches: [[name: 'refs/heads/release110']], doGenerateSubmoduleConfigurations: false, extensions: [[$class: 'CloneOption', noTags: true, reference: '', shallow: true], [$class: 'MessageExclusion', excludedMessage: 'Automated site publishing.*'], [$class: 'RelativeTargetDirectory', relativeTargetDir: 'netbeanssources']], submoduleCfg: [], userRemoteConfigs: [[url: 'https://github.com/apache/incubator-netbeans/']]])
          }
      }
      stage('NetBeans Builds') {
          steps {
              dir ('netbeanssources'){
                  withAnt(installation: 'Ant (latest)') {
                      sh 'ant'
                      sh 'ant build-javadoc'
                      sh 'ant build-source-zips'
                      sh 'ant build-nbms'
                  }
              }
              archiveArtifacts 'WEBZIP.zip'
              archiveArtifacts 'netbeanssources/nbbuild/netbeans/**'
              archiveArtifacts 'netbeanssources/nbbuild/build/source-zips/**'
              archiveArtifacts 'netbeanssources/nbbuild/build/javadoc/**'
              archiveArtifacts 'netbeanssources/nbbuild/nbms/**'
            }
      }
      stage('NetBeans Maven Stage') {
          steps {
              script {
                        sh "mvn org.netbeans.maven:nb-repository-plugin:1.4-SNAPSHOT:download -DnexusIndexDirectory=${env.WORKSPACE}/repoindex -Dmaven.repo.local=${env.WORKSPACE}/.repository -DrepositoryUrl=https://repo.maven.apache.org/maven2"
                        sh 'mkdir -p testrepo/.m2'
                        sh "mvn org.netbeans.maven:nb-repository-plugin:1.4-SNAPSHOT:populate -DnexusIndexDirectory=${env.WORKSPACE}/repoindex -DnetbeansNbmDirectory=${env.WORKSPACE}/netbeanssources/nbbuild/nbms -DnetbeansInstallDirectory=${env.WORKSPACE}/netbeanssources/nbbuild/netbeans -DnetbeansSourcesDirectory=${env.WORKSPACE}/netbeanssources/nbbuild/build/source-zips -DnebeansJavadocDirectory=${env.WORKSPACE}/netbeanssources/nbbuild/build/javadoc  -Dmaven.repo.local=${env.WORKSPACE}/.repository -DforcedVersion=RELEASE110 -DskipInstall=true -DdeployUrl=file://${env.WORKSPACE}/testrepo/.m2"
              }
              archiveArtifacts 'testrepo/.m2/**'
          }
      }
   }
}
