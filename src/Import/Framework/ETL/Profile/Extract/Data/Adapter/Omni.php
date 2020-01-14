<?php

namespace Import\Framework\ETL\Profile\Extract\Data\Adapter;

use Import\Framework\ETL\Profile\Extract\Data\DataInterface;
use Import\Framework\ETL\Profile\Extract\Data\Exception as Exception;

/**
 * Omni Data
 *
 * This is the internal "Omni" data object that all data is extracted *into* and
 * loaded *from*.  The purpose of this class is to abstract away any
 * implementation-specific accessor methods.  Both XML and JSON documents are
 * represented using the ArrayAccess and Interator interfaces.
 *
 * @package Map\Data\Adapter
 */
class Omni extends AbstractAdapter implements DataInterface
{
    /**
     * @var array Data array
     */
    protected $_data = array();

    /**
     * @var string Format of the incoming data
     */
    protected $_inputFormat;

    /**
     * @var string Delimiter used when finding an array element
     */
    protected $_pathDelimiter = '/';

    /**
     * @var array Supported incoming data formats
     */
    protected $_supportedDataFormats = array(
        'array',
        'json'
    );


    public function __construct($format, $options=array())
    {
        $this->setInputFormat($format);
    }


    /**
     * Load data provided in a known format
     * @param mixed $data
     * @throws Exception
     */
    public function loadData($data)
    {
        if (!$this->isFormatSet()) {
            throw new Exception("Cannot load data.  Format not specified.");
        }


        switch ($this->getFormat($data)) {
            case 'json':
                $this->_data = $this->parseDocumentJson($data);
                break;

            case 'array':
                if (!is_array($data)) {
                    throw new Exception("Cannot load data as array. Invalid array.");
                }
                $this->_data = $data;
                break;

            default:
                throw new Exception("Data import module missing supported format support.");
        }
    }

    /**
     * Has data been loaded yet?
     * @return bool
     */
    public function isDataLoaded()
    {
        return count($this->_data) > 0;
    }

    /**
     * Parse a JSON document
     * @param $data
     * @throws \Import\Framework\ETL\Profile\Extract\Data\Exception
     * @return mixed
     */
    public function parseDocumentJson($data)
    {
        if (!is_string($data) || $data === "") {
            throw new Exception("Cannot parse JSON data.  Value provided is invalid.");
        }
        $data = json_decode($data, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Error parsing JSON document.");
        }

        return $data;
    }


    /**
     * Find a value
     *
     * @param $path
     * @return array
     */
    public function find($path)
    {
        $path = trim($path, '/');

        $location = $this->explodePath($path);

        $data = $this->_data;

        /*
         * Loop over the provided location path and try to reduce the internal
         * data array until we arrive at the requested value.
         *
         * Note: we return null if the value isn't found *or* if the path is
         *  invalid
         * @todo Find a more efficient way to do this
         */
        foreach($location as $key) {
            // Base case: we're looking for a single value
            if (is_array($data) && array_key_exists($key, $data)) {
                $data = $data[$key];
            }
            else {
                return null;
            }
        }

        return $data;
    }

    /**
     * Explode path
     *
     * Breaks down the supplied path into an array indicating a traversal path
     * within the subject data structure.
     *
     * By default we use "/" as the path delimiter.  If, by chance a array key
     * contains a "/" in it you can will need to escape it with a backslash.
     *
     * @param string $location
     * @return array
     */
    public function explodePath($location)
    {

        $items = explode('/', trim($location, $this->_pathDelimiter));

        $mapped = array_map('urldecode', $items);


        return $mapped;
    }

    /**
     * Set the path delimiter
     *
     * Default is "/"
     *
     * @param string $delimiter
     */
    public function setPathDelimiter($delimiter)
    {
        $this->_pathDelimiter = $delimiter;
    }



    //
    // ITERATOR INTERFACE SUPPORT:
    //

    /**
     * Rewind pointer - Iterator interface
     * @link http://www.php.net/manual/en/iterator.rewind.php
     */
    function rewind() {
        reset($this->_data);
    }

    /**
     * Get current value at pointer - Iterator interface
     * @link http://www.php.net/manual/en/iterator.current.php
     * @return mixed
     */
    function current() {
        return current($this->_data);
    }

    /**
     * Get the key at the current pointer - Iterator interface
     * @link http://www.php.net/manual/en/iterator.key.php
     * @return int|mixed
     */
    function key() {
        return key($this->_data);
    }

    /**
     * Increment the pointer to the next element - Iterator interface
     * @link http://www.php.net/manual/en/iterator.next.php
     */
    function next() {
        next($this->_data);
    }

    /**
     * Is there a value at the current pointer? - Iterator interface
     * @link http://www.php.net/manual/en/iterator.valid.php
     * @return bool
     */
    function valid() {
        return array_key_exists($this->key(), $this->_data);
    }




    //
    // ARRAY ACCESS INTERFACE SUPPORT
    //

    /**
     * Data offset exists? - ArrayAccess interface
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset An offset to check for.
     * @return boolean
     */
    public function offsetExists($offset)
    {
        return isset($this->_data[$offset]);
    }

    /**
     * Offset to retrieve - ArrayAccess interface
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset The offset to retrieve.
     * @return mixed Can return all value types.
     */
    public function offsetGet($offset)
    {
        if ($this->offsetExists($offset)) {
            return $this->_data[$offset];
        }

        return null;
    }

    /**
     * Offset to set - ArrayAccess interface
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     * @param mixed $offset The offset to assign the value to.
     * @param mixed $value The value to set.
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        // THIS METHOD IS INTENTIONALLY EMPTY
        // We do not support setting values at this time
    }

    /**
     * Offset to unset - ArrayAccess interface
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset The offset to unset.
     * @return void
     */
    public function offsetUnset($offset)
    {
        // THIS METHOD IS INTENTIONALLY EMPTY
        // We do not support un-setting values at this time
    }
}