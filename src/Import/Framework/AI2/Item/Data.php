<?php


namespace Import\Framework\AI2\Item;


class Data implements \ArrayAccess
{
    protected $data;
    
    
    public function __construct($data)
    {
        if ($data instanceof Data) {
            $data = $data->getItemData();
        }
        
        if ( ! is_array($data)) {
            throw new \InvalidArgumentException("Item data must be an array.");
        }
        
        $this->data = $data;
    }

    /**
     * Factory 
     * 
     * This allows arrays to be easily you to pass either a Data instance or an
     * array and know you will always have the correct Data instance as the 
     * result.  
     * 
     * @param Data|array $itemData
     * @return Data
     */
    public static function factory($itemData)
    {
        if ($itemData instanceof self) {
            return $itemData;
        }
        elseif (is_array($itemData)) {
            return new self($itemData);
        }
        
        throw new \InvalidArgumentException("Invalid item data provided.");
    }
    
    public function getItemData($removeMongoId = false)
    {
        $data = $this->data;

        if ($removeMongoId) {
            unset($data['_id']);
        }

        return $data;
    }
    
    public function getMetaData()
    {
        return array(
            "sourceId"       => $this->data['source']['id'],
            "sourceFileName" => $this->data['source']['file'],
            "rawDataHash"    => $this->data['internal']['rawDataHash'],
            "uniqueId"       => $this->data['item']['uniqueId']
        );
    }
    
    public function doesMetaDataMatch(Data $ItemData)
    {
        return (
            sha1(json_encode($this->getMetaData())) == 
            sha1(json_encode($ItemData->getMetaData()))
        ); 
    }
    
    public function isVerifiable()
    {
        return (
            isset($this->data['source']['id']) &&
            isset($this->data['source']['file']) &&
            isset($this->data['internal']['rawDataHash']) &&
            isset($this->data['item']['uniqueId'])
        );
    }

    /**
     * Get the item GUID
     * @return string|bool
     */
    public function getGuid()
    {
        if (isset($this->data['internal']['guid'])) {
            return $this->data['internal']['guid'];
        }

        return false;
    }

    /**
     * Get the file info metadata
     * @return array|bool
     */
    public function getFileInfo()
    {
        if (is_array($this->data['source'])
            && array_key_exists('id',   $this->data['source'])
            && array_key_exists('file', $this->data['source'])
            && array_key_exists('type', $this->data['source'])) {

            return array(
                'sourceId' => $this->data['source']['id'],
                'fileName' => $this->data['source']['file'],
                'type'     => $this->data['source']['type']
            );
        }

        return false;
    }


    /**
     * Validate Item GUID
     *
     * @param string $guid
     * @return bool
     */
    static public function isValidGuid($guid)
    {
        if (!is_string($guid)) {
            return false;
        }

        /*
         * Regex pattern match
         * Example GUID: "FTP1-PARALLON-0000000000000026777"
         * @see http://www.phpliveregex.com/p/2AG
         */
        return (bool) preg_match(
            '/[A-Z0-9]{4}\-[A-Z0-9]{3,10}-[\d]{19}/',
            $guid
        );
    }

    /**
     * Whether a offset exists
     * @param mixed $offset
     * @return boolean true on success or false on failure.
     * The return value will be casted to boolean if non-boolean was returned.
     * @since 5.0.0
     */
    public function offsetExists($offset)
    {
        return isset($this->data[$offset]);
    }

    /**
     * Offset to retrieve
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset <p>
     * The offset to retrieve.
     * </p>
     * @return mixed Can return all value types.
     * @since 5.0.0
     */
    public function offsetGet($offset)
    {
        return $this->data[$offset];
    }

    /**
     * Offset to set
     * @param mixed $offset
     * @param mixed $value
     * @return void
     * @since 5.0.0
     */
    public function offsetSet($offset, $value)
    {
        $this->data[$offset] = $value;
    }

    /**
     * Offset to unset
     * @param mixed $offset
     * @return void
     * @since 5.0.0
     */
    public function offsetUnset($offset)
    {
        unset($this->data[$offset]);
    }
}