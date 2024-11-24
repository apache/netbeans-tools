Licensed to the Apache Software Foundation (ASF) under one or more contributor
license agreements; and to You under the Apache License, Version 2.0.


Local Setup Instructions:

0) required software: php >= 7.2 cli, web server with php >= 7.2 (apache, nginx...), mysql >=5.7, composer
1) run pear install Console_GetoptPlus
2) clone project
3) in root folder run $ composer install
4) copy over config .dist files and set proper values there
	config/autoload/
	module/Application/config/
5) setup local database with config/pp3.sql script
6) set write access to data/, vendor/ and public/data folders so app can store files (user uploads, app cache etc...
7) open pp3/public/ folder in teh browser to see the application


Nodes for work with docker:

  - docker run -it --publish 8080:80 -v <basepath_to_netbeans-tools_checkout>/pp3:/mnt ubuntu:focal
  - apt update
  - apt install libapache2-mod-php mysql-server vim php-mysql php-simplexml openjdk-21-jdk-headless postfix
  - Modify /etc/apache2/sites-enabled/000-default.conf:

    DocumentRoot should point to /mnt/public

    Apache needs to be allowed to acces:

     <Directory /mnt/public>
             Options Indexes FollowSymLinks
             AllowOverride All
             Require all granted
     </Directory>

  - Enable mode rewrite: ln -s /etc/apache2/mods-available/rewrite.load /etc/apache2/mods-enabled/
  - Start apachectl start
  - Start mysql /etc/init.d/mysql start
  - Start postfix /etc/init.d/postfix start
  - Create Database: echo "CREATE DATABASE pp3; CREATE USER pp3@'%' IDENTIFIED BY 'pp3'; GRANT ALL on pp3.* TO pp3@'%'" | mysql; mysql pp3 < /mnt/config/pp3.sql
  - In PP3: Create NB Versions in (Admin -> NetBeans versions)
  - Comment out the http -> https redirects in public/.htaccess