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
6) to become admin user add your google email into module/Application/config/module.config.php: pp3->admin property 
7) set write access to data/, vendor/ and public/data folders so app can store files (user uploads, app cache etc...
8) open pp3/public/ folder in teh browser to see the application

