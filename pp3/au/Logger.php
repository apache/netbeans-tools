<?php

/**
 * Simple logger class
 *
 */
class Logger {
    
    const INFO='INFO';
    const ERROR='ERROR';
    const DEBUG='DEBUG';
    const FINE='FINE';

    /**
     * Sigleton instance
     * @var Logger 
     */
    private static $instance;

    /**
     * Info log file handle
     * @var file handle
     */
    private $_logFileHandler;

    /**
     * Log info level message and echo it as well
     * @param string $message 
     */
    public static function write($level, $message) {
        if (self::$instance) {
            fwrite(self::$instance->_logFileHandler, date('Y-m-d H:i:s') . ' - [' . strtoupper($level) . '] - ' . $message . "\n");
        }
        if ($level!= self::DEBUG && $level!=self::FINE) {
            // write out only info+error
            echo date('Y-m-d H:i:s') . ' - [' . strtoupper($level) . '] - ' . $message . "\n";
        }
    }

    private function __construct($logFile) {
        $this->_logFileHandler = fopen($logFile, 'w');
        if ($this->_logFileHandler == false) {
            throw new Exception('Unable to create the info logfile ' . $logFile);
        }
    }

    public function __destruct() {
        // close file handlers when the object is going down
        fclose($this->_logFileHandler);
    }

    /**
     * Singleton init method
     * @param string $logFile Path of the logfile
     * @return Logger 
     */
    public static function init($logFile) {
        if (!isset(self::$instance)) {
            self::$instance = new Logger($logFile);
        }
        return self::$instance;
    }

    public function __clone() {
        trigger_error('Clone is not allowed.', E_USER_ERROR);
    }

    public function __wakeup() {
        trigger_error('Unserializing is not allowed.', E_USER_ERROR);
    }

}

?>
