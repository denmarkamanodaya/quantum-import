<?php

namespace Import;

/**
 * Monitor
 *
 * This class is a simple singleton wrapper around the \JT\Monitor class which
 * pre-populates the component name to 'ai2.input' and allows you to call
 * getInstance() to get an instance of the monitor adapter.
 *
 * @package Import
 */
class Monitor extends \JT\Monitor
{
    /**
     * @var \Import\Monitor;
     */
    static protected $_instance;

    /**
     * @var string Component name
     */
    protected $_componentName = 'ai2.input';

    /**
     * Static constructor
     *
     * @return Monitor
     */
    static public function getInstance()
    {
        if (!self::$_instance instanceof self) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
}