pipeline {
   agent  { label 'ubuntu' }
   tools {
      maven 'Maven 3.3.9'
      jdk 'JDK 1.8 (latest)'
   }
   stages {
      stage('Informations') {
          steps {
              echo "Branche we are building is : release100"
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
              checkout([$class: 'GitSCM', branches: [[name: '*/release100']], doGenerateSubmoduleConfigurations: false, extensions: [[$class: 'CloneOption', noTags: true, reference: '', shallow: true], [$class: 'MessageExclusion', excludedMessage: 'Automated site publishing.*'], [$class: 'RelativeTargetDirectory', relativeTargetDir: 'netbeanssources']], submoduleCfg: [], userRemoteConfigs: [[url: 'https://github.com/apache/incubator-netbeans/']]])
          }
      }
      stage('NetBeans Builds') {
          steps {
              dir ('netbeanssources'){
                  withAnt(installation: 'Ant (latest)') {
                      sh 'ant'
                      sh "ant build-javadoc -Djavadoc.web.root='http://bits.netbeans.org/10.0/javadoc' -Dmodules-javadoc-date='4 Dec 2018' -Datom-date='2018-12-04T12:00:00Z' -Djavadoc.web.zip=${env.WORKSPACE}/WEBZIP.zip"
                      sh 'ant build-source-zips'
                      sh 'ant build-nbms'
                  }
              }
              archiveArtifacts 'WEBZIP.zip'
            }
      }
   }
}
