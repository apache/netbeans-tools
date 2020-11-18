#!/usr/bin/php
<?php
ini_set('memory_limit', '560M');

require_once 'config.php';
require_once 'lib/Getopt.php';
require_once 'lib/geoip.inc';
require_once 'Logger.php';
require_once 'Db.php';
require_once 'Runner.php';
require_once 'Importer.php';

// start logger, use it's static methods ::info($msg), ::error($msg) anywhere to log status 
Logger::init(LOGFILE_PATH . '/au-import-info-' . date('Ymd') . '.log');

// parse cmdline for product which AU log we want to import: netbeans|dlc|vvm .. default to all
$opts = new Zend_Console_Getopt(array(
            'help|h' => 'Show help',
            'source|s=s' => 'Which AU log to import, possible valules: dlc, nb, vvm'));
try {
    $opts->parse();
    $source = ($opts->getOption('source')) ? $opts->getOption('source') : 'all';
    $help = $opts->getOption('help');
    if ($help) {
        echo $opts->getUsageMessage();
        exit(0);
    }
} catch (Exception $e) {
    // some illegal option specified, show help and exit
    echo $opts->getUsageMessage();
    exit(1);
}

// here it happens
try {
    // invoke runner   
    Logger::write(Logger::INFO, 'Starting import, log specified: ' . $source);
    Runner::run($source);
    Logger::write(Logger::INFO, 'Import finished');
    exit(0);
} catch (Exception $e) {
    // if any exception is thrown, send it in email to the system owner
    mail(ADMIN_EMAIL, 'AU-IMPORT - exception occured', "Hello,\nthis is to inform you about exception during the AU-IMPORT procedure.\n\n" . date('Y-m-d H:i:s') . "\n\nException:\n" . $e->getMessage() . "\n\n" . $e->getTraceAsString());
    // also print it
    echo $e->getMessage();
    exit(1);
}
?>
