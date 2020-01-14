<?php

namespace Import\Framework\ETL\Profile;

/**
 * ETL Result
 *
 * This class is just a bare-bones abstraction of an ETL result.  There's no
 * magic here except that by passing in your ETL profile, this class will run
 * the ETL and populate some simple public properties with the result of the
 * ETL.
 *
 * @package Import\Framework\ETL\Profile
 */
class Result
{
    /**
     * @var string
     */
    public $mapping;

    /**
     * @var array
     */
    public $fields;

    /**
     * @var array
     */
    public $validationErrors = array();

    /**
     * @var \Import\Framework\ETL\Profile
     */
    protected $_profile;

    /**
     * Constructor
     * @param \Import\Framework\ETL\Profile $Profile
     * @throws Exception
     * @throws Exception
     */
    public function __construct(\Import\Framework\ETL\Profile $Profile)
    {
        $this->_profile = $Profile;

        if ( $this->_profile->isReady() ) {
            $this->mapping          = $this->_profile->run();
            $this->fields           = $this->_profile->getLoad()->getLastRenderFields();;
            $this->validationErrors = $this->_profile->getLoad()->getLastValidationErrors();
        }
    }

    /**
     * Get field
     *
     * Gets a field value after checking that the field name exists first so
     * undefined index warnings are not thrown.
     *
     * @param $name
     * @return null
     */
    public function getField($name)
    {
        if (!$this->_profile->isReady()) {
            return null;
        }

        if (is_array($this->fields) && isset($this->fields[$name])) {
            return $this->fields[$name];
        }
        return null;
    }


    public function getMappedValue($name)
    {
        if (!$this->_profile->isReady()) {
            return null;
        }

        if (is_array($this->mapping) && isset($this->mapping[$name])) {
            return $this->mapping[$name];
        }
        return null;
    }

    /**
     * "Magic" To String Method
     *
     * This magic method allows you to do something like `echo $Result` and have
     * the final result of the ETL mapping to be echoed.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->mapping;
    }

    public function toArray()
    {
        if (!$this->_profile->isReady()) {
            return array();
        }


        if (is_array($this->mapping)) {
            return $this->mapping;
        }

        /*
         * Convert "struct" and "json" types to array via json_decode
         */
        if (in_array($this->_profile->getLoad()->getType(), array('json', 'struct'))) {

            $result = @json_decode($this->mapping, true);

            if (is_array($result)) {
                return $result;
            }
        }

        return array(
            $this->mapping
        );
    }

}