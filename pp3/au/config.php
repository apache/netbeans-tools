<?php

/**
 * Config variables 
 */
DEFINE('LOGFILE_PATH', './tmp');
DEFINE('ADMIN_EMAIL', 'jan.pirek@oracle.com');
DEFINE('LOCKFILE', './tmp/run.lck');

// last dates file prefix
DEFINE('LAST_DATE_FILE_PREFIX', './last-date-');

// DB connection
DEFINE('DB_USER', 'root');
DEFINE('DB_PASSWORD', '');
DEFINE('DB_HOST', '127.0.0.1');
DEFINE('DB_NAME', 'jchalupa');

// Path to logfiles served by local web server
DEFINE('URL_PREFIX_DLC', 'http://localhost/au/logs/');