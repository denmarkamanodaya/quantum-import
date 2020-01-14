<?php

namespace Import\Transmit\Adapter;

use Import\Log\AbstractLogable;
use \Import\Translate\Item;
use Import\Transmit\Response;

abstract class AbstractAdapter extends AbstractLogable
{
    /**
     * @var \Import\Input\Adapter\AbstractAdapter
     */
    protected $_file;

    /**
     * @var \Import\Transmit\Response
     */
    protected $_response;

    /**
     * Send a job
     * @param Item $Item
     * @param int $position
     * @return int  The CRUD status
     */
    abstract public function send(Item $Item, $position);

    /**
     * Finalize
     *
     * @return array
     */
    abstract public function finalize();


    /**
     * Constructor
     * @param \Import\Input\Adapter\AbstractAdapter $InputFile
     * @param array $config
     */
    public function __construct(\Import\Input\Adapter\AbstractAdapter $InputFile, $config=array())
    {
        $this->_file = $InputFile;

        /*
         * Initialize a response object
         */
        $this->_response = new Response();
        $this->_response->loadInputAdapterData($this->_file);

        $config = $this->_init($config);

        $this->_setOptions($config);
    }

    /**
     * Initialize dependencies
     *
     * Allow implementing classes to inject their own default dependencies.
     *
     * @param array $config
     * @return array
     */
    protected function _init($config)
    {
        return $config;
    }

    /**
     * Set all dependencies
     *
     * Loops over $options array and attempts to find protected methods matching "_setOption[keyName]".  If found, the
     * value is passed to the method.
     *
     * @param  array $options
     * @return bool
     */
    protected function _setOptions($options)
    {
        if (!is_array($options)) {
            return false;
        }

        foreach ($options as $key => $value) {
            $method = '_setOption' . $key;
            if (method_exists($this, $method)) {
                $this->$method($value);
            }
        }

        return true;
    }



    public function logError(\Exception $exception, $position)
    {
        return $this->_response->logError($exception, $position);
    }

    public function getTransmitSummary()
    {
        return $this->_response->asArray();
    }
}