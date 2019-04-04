pipeline {
   agent  { label 'ubuntu' }
   options {
      buildDiscarder(logRotator(numToKeepStr: '2'))
      disableConcurrentBuilds() 
   }
   triggers {
      pollSCM('H/5 * * * * ')
   }
   tools {
      maven 'Maven 3.3.9'
      jdk 'JDK 1.8 (latest)'
   }
   stages {
      stage('Informations') {
          steps {
              slackSend (channel:'#netbeans-builds', message:"STARTED: Job '${env.JOB_NAME} [${env.BUILD_NUMBER}]' ($env.BUILD_URL), Branch we are building is : refs/heads/master",color:'#f0f0f0')
          }
      }
      stage('SCM operation') {
          steps {
              echo 'clean up netbeans sources'
              sh 'rm -rf netbeanssources'
              echo 'Get NetBeans sources'
              checkout([$class: 'GitSCM', branches: [[name: 'refs/heads/master']], doGenerateSubmoduleConfigurations: false, extensions: [[$class: 'CloneOption', noTags: false, reference: '', shallow: true], [$class: 'MessageExclusion', excludedMessage: 'Automated site publishing.*'], [$class: 'RelativeTargetDirectory', relativeTargetDir: 'netbeanssources']], submoduleCfg: [], userRemoteConfigs: [[refspec: '+refs/tags/*:refs/remotes/origin/tags/*' , url: 'https://github.com/apache/incubator-netbeans/']]])
          }
      }
      stage('NetBeans Builds') {
          steps {
              dir ('netbeanssources'){
                  withAnt(installation: 'Ant (latest)') {
                      sh 'ant'
                      sh "ant build-javadoc -Djavadoc.web.zip=${env.WORKSPACE}/WEBZIP.zip"
                  }
              }
              archiveArtifacts 'WEBZIP.zip'
            }
      }
   }
   post {
     success {
       slackSend (channel:'#netbeans-builds', message:"SUCCESS: Job '${env.JOB_NAME} [${env.BUILD_NUMBER}]' (${env.BUILD_URL}) ",color:'#00FF00')
     }
     failure {
       slackSend (channel:'#netbeans-builds', message:"FAILED: Job '${env.JOB_NAME} [${env.BUILD_NUMBER}]'  (${env.BUILD_URL})",color:'#FF0000')
     }
   }
}
