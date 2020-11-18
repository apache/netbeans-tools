#!/usr/bin/php
<?php
/**
 * Script for calculation of AU dashboard numbers from pings, users... tables
 * 
 * Some background: dlc+nb logfiles are parsed for IDE callbacks to catalog.xml
 * and those pings are analyzed and inserted into DB, tables pings, users, catalogs, releases...
 * There is enourmous number of these records - hundrets of millions.
 * This script then calculates basic stats from these numbers and saves result 
 * to DB again so we can use them on dashboard later on.
 * 
 * CLI options:
 *  --month=yyyy-mm month for which generate numbers
 *  --debug
 *  --help
 *
 */
// Connection config, edit to match your env
$connection = array(
    'driver' => 'mysqli',
    'host' => 'localhost',
    'username' => 'jchalupa',
    'password' => 'root',
    'database' => 'root',
    'profiler' => TRUE,
);

// manual delay in days for counting data
$delay = 2;

// debug flag
$debug = false;

// lockfile
$lockfile = '/tmp/run.lck';

/*
 * == DO NOT EDIT BELOW THIS LINE UNLESS YOU ARE SURE YOU KNOW WHAT YOU ARE DOING ==
 */
// read params from cli
require_once './lib/Getopt.php';
try {
    $opts = new Zend_Console_Getopt(array('help|h' => 'Show help',
                'month|m=s' => 'Month for which generate stats, format yyyy-mm',
                'debug|d' => 'Show debug output',
                'product|p=s' => 'Product - netbeans, vvm',
                'stat|s=s' => 'Statistic to run - au, au2, countries, distros, packs, stickiness'));
    $opts->parse();
    $month = $opts->getOption('month');
    $debug = $opts->getOption('debug');
    $help = $opts->getOption('help');
    $product = $opts->getOption('product');
    $stat = ($opts->getOption('stat')) ? $opts->getOption('stat') : 'all';
    if ($help) {
        echo $opts->getUsageMessage();
        exit(0);
    }
} catch (Zend_Console_Exception $e) {
    echo $opts->getUsageMessage();
    exit(1);
}

if (!file_exists($lockfile)) {
    // lock the run   
    exec('touch ' . $lockfile);
    echo "Lockfile created\n";
    // use delayed value from today if it's not defined from cli
    if (empty($month))
        $month = date('Y-m', strtotime('-' . $delay . ' day'));
    if (empty($product))
        $product = 'netbeans';

    // let's use DIBI for db abstraction, Uff there is PHP version check failure on nina.cz.oracle.com
    //require_once(dirname(__FILE__) . "/include/dibi.min.php");
    try {
        //dibi::connect($connection);
        require_once './db_connect.php.inc';
        // setup stats params like dates and offsets    
        switch ($product) {
            case 'netbeans':
                $activityOffset = 7;
                $monthStart = $month; // users counted monthly
                break;
            case 'vvm':
                $activityOffset = 7;
                $monthStart = date('Y-m', strtotime('-1 year', strtotime($month . '-01'))); // users counted yearly
                break;
            default:
                $activityOffset = 7;
                $monthStart = $month;
                break;
        }
        // now put together statistics, query DB and insert results
        if ($stat == 'all') {
            au($month, $product, $activityOffset, $monthStart);
            au2($month, $product, $activityOffset, $monthStart);
            //countries($month, $product, $activityOffset, $monthStart);
            //distros($month, $product, $activityOffset, $monthStart);
            //packs($month, $product, $activityOffset, $monthStart);
            //stickiness($month, $product);
        } else {
            $stat($month, $product, $activityOffset, $monthStart);
        }
    } catch (Exception $e) {
        echo $e->getMessage() . "\n";
        unlock();
        exit(1);
    }
    unlock();
    exit(0);
} else {
    echo "Previous run of the script seems to be still running, lockfile found: " . $lockfile . " from " . date('F d Y H:i:s', filemtime($lockfile)) . "\nRemove lockfile first!\n";
}

function unlock() {
    global $lockfile;
    unlink($lockfile);
    echo "\nLockfile removed\n";
}

/*
 * Active Users for selected month, by releases
 */

function au($month, $product, $activityOffset, $monthStart) {
    global $dbc, $debug;
    $qr = 'SELECT COUNT(DISTINCT p.user_id) AS count, r.id AS release_id, r.version AS release_version, r.lang
            FROM pings p                        
            INNER JOIN users u
            ON (p.user_id=u.id AND p.ts BETWEEN "' . $monthStart . '-01" AND "' . $month . '-31 23:59:59")
            INNER JOIN catalogs c
            ON (p.path_id=c.id AND p.ts BETWEEN "' . $monthStart . '-01" AND "' . $month . '-31 23:59:59")
            INNER JOIN releases r
            ON (r.catalog_id=c.id AND r.product="' . $product . '")
            WHERE IF(r.delay="Y", DATEDIFF(p.ts,u.since)>=' . $activityOffset . ', TRUE)
            GROUP BY r.id';
    try {
        echo "Number of $product Active Users by release version for month $month\n";
        if ($debug)
            echo "Debug: " . $qr . "\n\n";
        $res = mysqli_query($dbc, $qr);
        echo "Found " . mysqli_num_rows($res) . " items\n";
        while ($r = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
            // we have some data, let's insert into db
            echo "\t" . $r['release_version'] . " " . $r['lang'] . " : " . $r['count'] . " users\n";
            $qr = 'REPLACE INTO results_counts SET  month="' . $month . '", release_id=' . $r['release_id'] . ', results_index_id=1, value="' . $r['count'] . '", product="' . $product . '", pack_signature=0, distro_id=0, country_id=0';
            $res2 = mysqli_query($dbc, $qr);
            if (!$res2) {
                echo "ERR: " . mysqli_error() . $qr . "\n";
            }
        }
    } catch (Exception $e) {
        echo 'Failed Query: ' . $e->getMessage();
    }
}

function au2($month, $product, $activityOffset, $monthStart) {
    global $dbc, $debug;
    $qr = 'SELECT COUNT(DISTINCT p.user2_id) AS count, r.id AS release_id, r.version AS release_version, r.lang
            FROM pings p                        
            INNER JOIN users2 u
            ON (p.user2_id=u.id AND p.ts BETWEEN "' . $monthStart . '-01" AND "' . $month . '-31 23:59:59")
            INNER JOIN catalogs c
            ON (p.path_id=c.id AND p.ts BETWEEN "' . $monthStart . '-01" AND "' . $month . '-31 23:59:59")
            INNER JOIN releases r
            ON (r.catalog_id=c.id AND r.product="' . $product . '")
            WHERE IF(r.delay="Y", DATEDIFF(p.ts,u.since)>=' . $activityOffset . ', TRUE)
            GROUP BY r.id';
    try {
        echo "Number of $product UNIQUE Active Users by release version for month $month\n";
        if ($debug)
            echo "Debug: " . $qr . "\n\n";
        $res = mysqli_query($dbc, $qr);
        echo "Found " . mysqli_num_rows($res) . " items\n";
        while ($r = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
            // we have some data, let's insert into db
            echo "\t" . $r['release_version'] . " " . $r['lang'] . " : " . $r['count'] . " users\n";
            $qr = 'REPLACE INTO results_counts SET month="' . $month . '", release_id=' . $r['release_id'] . ', results_index_id=3, value="' . $r['count'] . '", product="' . $product . '", country_id=0, pack_signature=0, distro_id=0';
            $res2 = mysqli_query($dbc, $qr);
            if (!$res2) {
                echo "ERR: " . mysqli_error() . $qr . "\n";
            }
        }
    } catch (Exception $e) {
        echo 'Failed Query: ' . $e->getMessage();
    }
}

/**
 * Active users for selected month by countries      
 */
function countries($month, $product, $activityOffset, $monthStart) {
    global $dbc, $debug;
    $qr = 'SELECT COUNT(DISTINCT p.user_id) AS count, ctr.name AS country_name, ctr.id as country_id
            FROM pings p                        
            INNER JOIN users u
            ON (p.user_id=u.id AND p.ts BETWEEN "' . $monthStart . '-01" AND "' . $month . '-31 23:59:59")
            INNER JOIN catalogs c
            ON (p.path_id=c.id AND p.ts BETWEEN "' . $monthStart . '-01" AND "' . $month . '-31 23:59:59")
            INNER JOIN releases r
            ON (r.catalog_id=c.id AND r.product="' . $product . '")
            INNER JOIN ips i
            ON p.ip_id=i.id AND p.ts BETWEEN "' . $monthStart . '-01" AND "' . $month . '-31 23:59:59"
            INNER JOIN countries ctr
            ON ctr.code=i.country
            WHERE IF(r.delay="Y", DATEDIFF(p.ts,u.since)>=' . $activityOffset . ', TRUE)
            GROUP BY i.country ORDER BY count DESC';
    try {
        echo "\n\nNumber of $product Active users by countries for month $month\n";
        if ($debug)
            echo "Debug: " . $qr . "\n\n";
        $res = mysqli_query($dbc, $qr);
        echo "Found " . count($res) . " items\n";
        while ($r = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
            // we have some data, let's insert into db
            echo "\t" . $r['country_name'] . ": " . $r['count'] . " users\n";
            $qr = 'REPLACE INTO results_counts SET month="' . $month . '", country_id=' . $r['country_id'] . ', results_index_id=2, value="' . $r['count'] . '", product="' . $product . '", release_id=0, distro_id=0, pack_signature=0';
            $res2 = mysqli_query($dbc, $qr);
            if (!$res2) {
                echo "ERR: " . mysqli_error() . $qr . "\n";
            }
        }
    } catch (Exception $e) {
        echo 'Failed Query: ' . $e->getMessage();
    }
}

function distros($month, $product, $activityOffset, $monthStart) {
    global $dbc, $debug;
    $qr = 'SELECT COUNT(DISTINCT p.user2_id) AS count, d.id AS distro_id, d.distro
            FROM pings p                        
            INNER JOIN users2 u
            ON (p.user2_id=u.id AND p.ts BETWEEN "' . $monthStart . '-01" AND "' . $month . '-31 23:59:59")
            INNER JOIN catalogs c
            ON (p.path_id=c.id AND p.ts BETWEEN "' . $monthStart . '-01" AND "' . $month . '-31 23:59:59")
            INNER JOIN releases r
            ON (r.catalog_id=c.id AND r.product="' . $product . '")
            INNER JOIN distros d ON (d.id=p.distro_id AND p.ts BETWEEN "' . $monthStart . '-01" AND "' . $month . '-31 23:59:59") 
            WHERE IF(r.delay="Y", DATEDIFF(p.ts,u.since)>=' . $activityOffset . ', TRUE)
            GROUP BY d.id';
    try {
        echo "Number of $product Active Users by distribution for month $month\n";
        if ($debug)
            echo "Debug: " . $qr . "\n\n";
        $res = mysqli_query($dbc, $qr);
        echo "Found " . mysqli_num_rows($res) . " items\n";
        while ($r = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
            // we have some data, let's insert into db
            echo "\t" . $r['distro'] . " : " . $r['count'] . " users\n";
            $qr = 'REPLACE INTO results_counts SET  month="' . $month . '", distro_id=' . $r['distro_id'] . ', results_index_id=4, value="' . $r['count'] . '", product="' . $product . '", release_id=0, pack_signature=0, country_id=0';
            $res2 = mysqli_query($dbc, $qr);
            if (!$res2) {
                echo "ERR: " . mysqli_error() . $qr . "\n";
            }
        }
    } catch (Exception $e) {
        echo 'Failed Query: ' . $e->getMessage();
    }
}

function packs($month, $product, $activityOffset, $monthStart) {
    global $dbc, $debug;
    $signatures = array('CND', 'CRE', 'ENT', 'MOB', 'PROF', 'CDC', 'CLDC', 'JAVA', 'JAVASE', 'JAVAEE', 'JAVAME', 'WEBEE', 'PROFILER',
        'PHP', 'RUBY', 'MOBILITY', 'UML', 'SOA', 'GLASSFISH', 'SJSAS', 'TOMCAT', 'VISUALWEB', 'JDK', 'MYSQL',
        'GROOVY', 'GFMOD', 'JAVAFX', 'WEBCOMMON', 'FX', 'PY', 'JC', 'WEBLOGIC');
    $qr = 'SELECT COUNT(DISTINCT p.user2_id) AS count, cf.id AS config_id, cf.signature
            FROM pings p                        
            INNER JOIN users2 u
            ON (p.user2_id=u.id AND p.ts BETWEEN "' . $monthStart . '-01" AND "' . $month . '-31 23:59:59")
            INNER JOIN catalogs c
            ON (p.path_id=c.id AND p.ts BETWEEN "' . $monthStart . '-01" AND "' . $month . '-31 23:59:59")
            INNER JOIN releases r
            ON (r.catalog_id=c.id AND r.product="' . $product . '")
            INNER JOIN configs cf ON (cf.id=p.config_id AND p.ts BETWEEN "' . $monthStart . '-01" AND "' . $month . '-31 23:59:59") 
            WHERE IF(r.delay="Y", DATEDIFF(p.ts,u.since)>=' . $activityOffset . ', TRUE)
            GROUP BY cf.id';
    try {
        echo "Number of $product Active Users by packs for month $month\n";
        if ($debug)
            echo "Debug: " . $qr . "\n\n";
        $res = mysqli_query($dbc, $qr);
        echo "Found " . mysqli_num_rows($res) . " items\n";
        while ($r = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
            // we have some data, let's count - parse the signature for packs and increment each pack according to it             
            $packs = explode(',', $r['signature']);
            foreach ($packs as $pack) {
                @$packsHits[$pack]+=$r['count'];
            }
        }
        foreach ($packsHits as $pack => $hits) {
            $qr = 'REPLACE INTO results_counts SET pack_signature="' . $pack . '", month="' . $month . '", results_index_id=5, value="' . $hits . '", product="' . $product . '", distro_id=0, release_id=0, country_id=0';
            $res2 = mysqli_query($dbc, $qr);
            echo "\t" . $pack . " : " . $hits . " users\n";
            if (!$res2) {
                echo "ERR: " . mysqli_error() . $qr . "\n";
            }
        }
    } catch (Exception $e) {
        echo 'Failed Query: ' . $e->getMessage();
    }
}

function stickiness($month, $product) {
    global $dbc, $debug;
    // find all final releases and for each get the stickiness distribution
    $releases = array();
    $qr = 'SELECT * FROM releases WHERE product="' . $product . '" AND stable="Y"';
    $res = mysqli_query($dbc, $qr);
    while ($r = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
        $releases[$r['version']][$r['id']] = $r['catalog_id'];
    }
    //die(var_dump($releases));
    if (!empty($releases)) {
        echo "Found " . count($releases) . " stable releases\n";
        foreach ($releases as $version => $catalogs) {
            // now query for for stickiness distribution
            $qr = 'SELECT delay, count(id) as users FROM users WHERE catalog_id in (' . implode(',', $catalogs) . ') AND delay<90 and last_seen>='.strtotime('-90 days').' GROUP BY delay ORDER BY delay';
            $res2 = mysqli_query($dbc, $qr);
            $data = array();
            while ($r2 = mysqli_fetch_array($res2, MYSQLI_ASSOC)) {
                $data[$r2['delay']] = $r2['users'];
            }
            if(!empty($data)) {                
                // store it
                echo 'Storing results for release '.$version."\n";
                $qr = 'REPLACE INTO results_counts SET month="' . $month . '", results_index_id=6, value="' . addslashes(serialize($data)) . '", product="' . $product . '", distro_id=0, release_id='.  key($catalogs).', country_id=0, pack_signature="0"';
                $res2 = mysqli_query($dbc, $qr);
            }
        }
    } else {
        echo "No stable releases found\n";
    }
}
?>
