#!/usr/bin/env sh
cd /usr/share/netbeans-javadoc

/usr/bin/php /usr/share/netbeans-javadoc/update-netbeans-javadoc.php > /var/log/netbeans-javadoc/php-out.log 2>&1

cd /usr/share/netbeans-javadoc
/usr/bin/php /usr/share/netbeans-javadoc/update-netbeans-html4j-javadoc.php > /var/log/netbeans-javadoc/php-html4j-out.log 2>&1

DL=/usr/share/netbeans-javadoc/download-if-required.sh
EX=/usr/share/netbeans-javadoc/extract-if-required.sh

# Maven nbm-plugin-maven

$DL "https://builds.apache.org/job/Netbeans/job/netbeans-maven-TLP/job/netbeans-mavenutils-nbm-maven-plugin/job/master/lastSuccessfulBuild/artifact/WEBSITE.zip" "/var/tmp/netbeans-mavenutils-nbm-maven-pluginsite.zip"
$EX "/var/tmp/netbeans-mavenutils-nbm-maven-pluginsite.zip" "/var/www/bits.netbeans.org/mavenutilities/nbm-maven-plugin" 

#Maven nb-repository-plugin
$DL "https://builds.apache.org/job/Netbeans/job/netbeans-maven-TLP/job/netbeans-mavenutils-nb-repository-plugin/job/master/lastSuccessfulBuild/artifact/WEBSITE.zip" "/var/tmp/netbeans-mavenutils-nb-repository-pluginsite.zip"
$EX "/var/tmp/netbeans-mavenutils-nb-repository-pluginsite.zip" "/var/www/bits.netbeans.org/mavenutilities/nb-repository-plugin" 

# Maven nbm-shared
$DL "https://builds.apache.org/job/Netbeans/job/netbeans-maven-TLP/job/netbeans-mavenutils-nbm-shared/job/master/lastSuccessfulBuild/artifact/WEBSITE.zip" "/var/tmp/netbeans-mavenutils-nbm-sharedsite.zip"
$EX "/var/tmp/netbeans-mavenutils-nbm-sharedsite.zip" "/var/www/bits.netbeans.org/mavenutilities/nbm-shared" 

# Maven utilities parent
$DL "https://builds.apache.org/job/Netbeans/job/netbeans-maven-TLP/job/netbeans-mavenutils-parent/job/master/lastSuccessfulBuild/artifact/WEBSITE.zip" "/var/tmp/netbeans-mavenutils-parentsite.zip"
$EX "/var/tmp/netbeans-mavenutils-parentsite.zip" "/var/www/bits.netbeans.org/mavenutilities/parent" 

# Maven utilities harness
$DL "https://builds.apache.org/job/Netbeans/job/netbeans-maven-TLP/job/netbeans-mavenutils-nbm-maven-harness/job/master/lastSuccessfulBuild/artifact/WEBSITE.zip" "/var/tmp/netbeans-mavenutils-nbm-maven-harnesssite.zip"
$EX "/var/tmp/netbeans-mavenutils-nbm-maven-harnesssite.zip" "/var/www/bits.netbeans.org/mavenutilities/nbm-maven-harness" 

# build index.html
cat part/index.html.header /var/tmp/jindex.txt part/index.html.section1 /var/tmp/jdochtml4jindex.txt part/index.html.footer > /var/www/bits.netbeans.org/index.html
