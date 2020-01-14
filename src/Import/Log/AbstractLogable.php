<?php

namespace Import\Log;

use Import\Cache;
use Import\Log;

/**
 * Logable
 *
 * Any class that needs to use the logger should extend this base class to
 * inherit basic logging functionality.
 *
 * @package Import\Log
 */
abstract class AbstractLogable extends Cache
{
    /**
     * @var \Monolog\Logger  Logger instance
     */
    private $_logger;

    /**
     * Get logger instance
     * @return \Monolog\Logger
     */
    abstract protected function _getLoggerComponent();

    /**
     * Get logger
     *
     * Returns an instance of the logger initialised using the component name
     * returned by _getLoggerComponent().
     *
     * @return \Monolog\Logger
     */
    protected function _getLogger()
    {
        if ($this->_logger instanceof \Monolog\Logger) {
            return $this->_logger;
        }

        $componentName = $this->_getLoggerComponent();

        if (!is_string($componentName) || preg_match('/[^a-zA-Z\.]/', $componentName)) {
            throw new \UnexpectedValueException("Logging component name is not valid.");
        }

        $this->_logger = Log::getLogger($componentName);

        return $this->_logger;
    }
}
