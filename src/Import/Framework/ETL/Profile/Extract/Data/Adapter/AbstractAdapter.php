<?php

namespace Import\Framework\ETL\Profile\Extract\Data\Adapter;


use Import\Framework\ETL\Profile\Extract\Data\Exception;

abstract class AbstractAdapter
{
    const FORMAT_XML = 'xml';
    const FORMAT_JSON = 'json';


    /**
     * @var string Input format
     */
    protected $_inputFormat;

    /**
     * @var array Supported incoming data formats
     */
    protected $_supportedDataFormats;

    /**
     * Has data been loaded yet?
     * @return bool
     */
    abstract public function isDataLoaded();

    /**
     * Find a value
     *
     * @param $path
     * @return array
     */
    abstract public function find($path);

    /**
     * Load data provided in a known format
     * @param mixed $data
     * @throws \Import\Framework\ETL\Exception
     */
    abstract public function loadData($data);

    /**
     * Get supported formats
     * @return array
     */
    public function getSupportedFormats()
    {
        return $this->_supportedDataFormats;
    }

    /**
     * Is the incoming data format set?
     * @return bool
     */
    public function isFormatSet()
    {
        return !empty($this->_inputFormat);
    }

    /**
     * Get the input format name
     * @param mixed $data Data to analyze.
     * @return string
     */
    public function getFormat($data)
    {
        // Just in case we someone already gave us an array...
        if (is_array($data)) {
            return 'array';
        }

        return $this->_inputFormat;
    }


    /**
     * Is a given format supported by this adapter?
     * @param string $format
     * @return bool
     */
    public function isFormatSupported($format)
    {
        // Convert to lower case
        $format = strtolower($format);

        return in_array($format, $this->getSupportedFormats());
    }



    /**
     * Set the format of the incoming data
     * @param string $inputFormat
     * @throws Exception
     */
    public function setInputFormat($inputFormat)
    {
        // Convert to lower case
        $inputFormat = strtolower($inputFormat);

        // Only allow supported data formats
        if (!$this->isFormatSupported($inputFormat)) {
            throw new Exception("Cannot create an Omni object for input format: {$inputFormat}.");
        }

        // Don't allow object reuse
        if ($this->isDataLoaded()) {
            throw new Exception("Cannot set the input format. Data already loaded");
        }

        $this->_inputFormat = $inputFormat;
    }
}