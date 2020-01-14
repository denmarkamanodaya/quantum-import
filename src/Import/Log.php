<?php

namespace Import;

use Import\App\Config;
use Monolog\Formatter\LogstashFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger as MonoLogger;

class Log
{
    static protected $_loggers = array();

    static protected function _getConfig()
    {
        return Config::get("INPUT_LOGGING");
    }

    /**
     * Get Logger
     * @param string $componentName
     * @return MonoLogger
     */
    static public function getLogger($componentName)
    {
        // Simple cache lookup
        if (isset(self::$_loggers[$componentName])) {
            return self::$_loggers[$componentName];
        }

        $filePath = self::_getLogPath();

        $Logger = new MonoLogger($componentName);

        $Logger->pushHandler(self::_getLogstashHandler(
            $filePath . '/log-logstash.txt',
            $componentName
        ));

        $Logger->pushHandler(
            self::_getOutputHandler()
        );

        self::$_loggers[$componentName] = $Logger;

        return self::$_loggers[$componentName];
    }

    static protected function _getLogPath()
    {
        $config   = self::_getConfig();

        if (!isset($config)) {
            throw new \UnexpectedValueException("Cannot initialize logger.  Path not configured.");
        }

        return $config;
    }

    /**
     * Get a Monolog Handler with Logstash formatting
     * @param string $fileName
     * @param string $applicationName
     * @return StreamHandler
     */
    static protected function _getLogstashHandler($fileName, $applicationName)
    {
        $Handler = new StreamHandler(
            $fileName,
            MonoLogger::DEBUG
        );

        // Use logstash format
        $Handler->setFormatter(new LogstashFormatter(
            $applicationName,
            "importer"
        ));

        return $Handler;
    }

    /**
     * Get a Monolog Handler that outputs to terminal
     * @return StreamHandler
     */
    static protected function _getOutputHandler()
    {
        return new StreamHandler(
            'php://stdout',
            MonoLogger::DEBUG
        );
    }
}