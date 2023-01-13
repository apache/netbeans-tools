<?php

/**
 * Main Runner which handles triggering individual tasks based on
 * the cmdline params specifying what to run for what day
 *
 */
class Runner {

    static $currentDate;
    static $grabbedLogFile;
    static $product;


    /**
     * Run
     * @param  string $source Resource to run
     * @return void
     */
    public static function run($source) {
        $availaleSources = array('dlc', 'nb', 'vvm');
        // prepare the queue of products to run for
        if ($source == 'all') {
            $runSources = $availaleSources;
        } else {
            if (in_array($source, $availaleSources)) {
                $runSources = array($source);
            } else {
                //unknown product
                throw new Exception('Unknown source specified ' . $source);
            }
        }
        self::lock();
        foreach ($runSources as $p) {
            self::procesSource($p);
        }
        self::unlock();
    }

    private static function procesSource($source) {
        Logger::write(Logger::INFO, 'Starting processing soucre: ' . $source);

        if (self::grabSourceLogfile($source) == true) {
            // reset Db counter
            Db::$counter = 0;
            self::parseLogfile($source);
            self::removeLogfile();
            self::incrementRunDate($source);
        }
    }

    //dsd

    private static function parseLogfile($source) {
        $importCounter = 0;
        Logger::write(Logger::INFO, 'Starting parsing of the ' . $source . ' logfile ' . self::$grabbedLogFile);
        $months = array(
            'Jan' => '01',
            'Feb' => '02',
            'Mar' => '03',
            'Apr' => '04',
            'May' => '05',
            'Jun' => '06',
            'Jul' => '07',
            'Aug' => '08',
            'Sep' => '09',
            'Oct' => '10',
            'Nov' => '11',
            'Dec' => '12'
        );
        $handle = fopen(self::$grabbedLogFile, 'r');
        if ($handle) {
            $nl = $nok = 0;
            while (($line = fgets($handle)) !== false) {
                $hit = false;
                // only interested in requests that include the 'unique' identifier as they represent the AU pings
                if (!strstr($line, '?unique=') || strstr($line, '/hotfixes/') || strstr($line, '/thirdparty/')) {
                    $nl++;
                    continue;
                }
                switch ($source) {
                    case 'dlc':
                        $line=str_replace('<%JSON:httpd_access%> ','', $line);
                        $jsonLog = json_decode($line, true);
                        $preg='/^(.+?)\?unique=(unique%3D)?([_A-Z-]+)?(0)?([a-f0-9-]+)(_[a-f0-9-]+)?(.*)?/';
                        $m = array();
                        $hit = preg_match($preg, $jsonLog['request'], $match);
                        if($hit) {
                            $ip = $jsonLog['clientip'];                                    
                            $date = date('Y-m-d', strtotime($jsonLog['time']));
                            $time = date('H:i:s', strtotime($jsonLog['time']));
                            //$timestamp = $match[1].' '.$match[2];
                            $path = $jsonLog['uri'];
                            $product_id = $match[3];
                            $user_id = $match[5];
                            if (isset($match[6]) && substr($match[6], 0, 1) == '_') {
                                $super_id = substr($match[6], 1);
                            } else {
                                $super_id = "";
                            }
                            $response = $jsonLog['status'];
                            $bytes = $jsonLog['bytes'];
                        }
                        break;

                }
                // let's parse it and fill these: $ip,$date $time,$path,$product_id,$user_id,$super_id,$response,$bytes
                if($hit) {                    

                    // check the unique ID format
                    // prior to NB 5.5.1, the ID was just a timestamp (current time in millis),
                    // since NB 5.5.1, the ID is a standard UUID: 01234567-89ab-cdef-0123-456789abcdef
                    if (!preg_match('/^(([0-9]{5,12})|([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}))/', $user_id, $match)) {
                        Logger::write(Logger::DEBUG, self::$grabbedLogFile . ": suspicious user ID '$user_id' in line: $nl - $line");
                    } else {
                        // in 6.5, a truly unique user ID (super ID) has been added,
                        // check the validity of this ID in UUID format, if available
                        if (strlen($super_id) > 0 && !preg_match('/^([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/', $super_id, $match)) {
                            Logger::write(Logger::DEBUG, self::$grabbedLogFile . ": suspicious super ID '$super_id' in line: $nl - $line");
                            // log a warning, but don't stop here, if the super ID is invalid,
                            // just ignore it
                            $super_id = "";
                        }
                        // return data found, ignore invalid requests (404)
                        if (strcmp($response, "404")) {
                            try {
                                // import it to DB
                                if (Importer::import(array('ip' => $ip, 'ts' => $date . " " . $time, 'path' => $path, 'distro' => $product_id, 'user_id' => $user_id, 'user2_id' => $super_id, 'response' => $response, 'size' => $bytes), $source) == true) {
                                    $importCounter++;
                                }
                            } catch (Exception $e) {
                                Logger::write(Logger::ERROR, 'Error happened during log entry import: ' . $e->getMessage());
                            }
                            $nok++;
                        }
                    }

                }
                $nl++;
            }            
            Logger::write(Logger::INFO, 'Parsed ' . $nl . ' lines of the log, used ' . $nok . ' for importing');
            Logger::write(Logger::INFO, 'Really inported into DB were ' . $importCounter . ' AU hits (needed ' . Db::$counter . ' queries)');
        } else {
            throw new Exception('Unable to open decompressed logfile for parsing ' . self::$grabbedLogFile);
        }
        return true;
    }

    private static function grabSourceLogfile($source) {
        // get the current date
        $currentDate = strtotime('+1 day', self::getLastRunDate($source));
        Logger::write(Logger::INFO, 'Current date set to ' . date('Y-m-d', $currentDate));
        // put together the path to the source logfile
        switch ($source) {
            case 'nb': // CONSTANT to avoid typo!
                $filename = 'access_' . date('Ymd', $currentDate);
                $url = URL_PREFIX_NB . date('Y_m', $currentDate) . '/' . $filename . '.gz';
                break;
            case 'vvm':
                $next_date = mktime(0, 0, 0, date("m", $currentDate), date("d", $currentDate) + 1, date("Y", $currentDate));
                $filename = date('Ymd', $currentDate) . "-" . date('Ymd', $next_date) . ".log";
                $url = URL_PREFIX_VVM . $filename . ".gz";
                break;
            case 'dlc':
                $filename = 'netbeans-vm.apache.org_access.log_' . date('Ymd', $currentDate);
                $url = URL_PREFIX_DLC . date('Y_m', $currentDate) . '/' . $filename . '.gz';
                break;
            default:
                throw new Exception('Unknown source: ' . $source);
        }
        self::$grabbedLogFile = $filename;
        self::$currentDate = $currentDate;
        Logger::write(Logger::INFO, 'Going to download the source logfile: ' . $url);
        // grab it using wget
        system("wget --quiet $url", $returnVal);
        if ($returnVal === 0) {
            Logger::write(Logger::INFO, 'Source logfile downloaded');
            // decompress it
            system("gzip -fd $filename.gz", $returnVal);
            if ($returnVal === 0) {
                Logger::write(Logger::INFO, 'Source logfile decompressed');
                return true;
            }
        } else {
            Logger::write(Logger::INFO, 'Source logfile not available');
            return false;
        }
    }

    private static function getLastRunDate($source) {
        $ld = file(LAST_DATE_FILE_PREFIX . $source);
        if ($ld) {
            Logger::write(Logger::INFO, 'Last run date identified as ' . trim($ld[0], "\n"));
            return strtotime(trim($ld[0], "\n"));
        } else {
            throw new Exception('Unable to get the last run date from ' . LAST_DATE_FILE_PREFIX . $source);
        }
    }

    private static function incrementRunDate($source) {
        if (file_put_contents(LAST_DATE_FILE_PREFIX . $source, date('Y-m-d', self::$currentDate)) != false) {
            Logger::write(Logger::INFO, 'Setting the last run date to ' . date('Y-m-d', self::$currentDate));
        } else {
            throw new Exception('Unable to set the last run date into ' . LAST_DATE_FILE_PREFIX . $source);
        }
    }

    private static function lock() {
        if (file_exists(LOCKFILE)) {
            throw new Exception('Previous run still runnig, lockfile ' . LOCKFILE . ' from ' . date("F d Y H:i:s.", filemtime(LOCKFILE)) . ". Remove lockfile first\n");
        }
        system('touch ' . LOCKFILE, $retval);
        if ($retval === 0) {
            Logger::write(Logger::INFO, 'Lockfile created');
        } else {
            throw new Exception('Can\'t create lockfile ' . LOCKFILE . "\n");
        }
    }

    private static function unlock() {
        if (unlink(LOCKFILE) == true) {
            Logger::write(Logger::INFO, 'Lockfile removed');
        } else {
            throw new Exception('Not possible to remove lockfile ' . LOCKFILE);
        }
    }

    private static function removeLogfile() {
        if (unlink(self::$grabbedLogFile) == true) {
            Logger::write(Logger::INFO, 'Source logfile removed');
        } else {
            throw new Exception('Not possible to remove source logfile ' . self::$grabbedLogFile);
        }
    }

}

?>
