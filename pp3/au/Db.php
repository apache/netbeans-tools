<?php

/**
 * Super simple mysql wrapper
 *
 */
class Db {
    public static $counter=0;

    private static $_isConnected = false;
    private static $_insData=array();
    private static $_insCounter=0;

    private static function connect() {
        $link = mysql_connect(DB_HOST, DB_USER, DB_PASSWORD);
        if (!$link) {
            throw new Exception('Can\'t connect to DB: '.DB_USER.':*****@'.DB_HOST);
        }
        $db_selected = mysql_select_db(DB_NAME, $link);
        if (!$db_selected) {
            throw new Exception('Can\'t select DB: '.DB_USER.':*****@'.DB_HOST.'/'.DB_NAME);
        }
        self::$_isConnected = true;
    }

    /**
     * Simple mysql query wrapper which will log the SQL errors for us
     * @param string $sql SQL statement to perform
     * @return resource 
     */
    public static function query($sql) {
        if (self::$_isConnected == false) {
            self::connect();
        }
        $result = mysql_query($sql);
        if ($result != false) {
            self::$counter++;
            return $result;
        } else {
            // log error
            Logger::write(Logger::DEBUG, 'SQL error for: '.$sql.'; ERR: '.mysql_error());
            return false;
        }
    }
    
    public static function deferredPingsInsert($data) {
        self::$_insCounter++;
        self::$_insData[]=$data;
        if(0==(self::$_insCounter%1000)) {
            $ret=self::query('INSERT INTO pings (ip_id, ts, path_id, distro_id, config_id, user_id, user2_id, response, size) VALUES '.implode(',', self::$_insData));
            self::$_insCounter=0;
            self::$_insData=array();
            return $ret;
        }
        return true;
    }

}

?>
