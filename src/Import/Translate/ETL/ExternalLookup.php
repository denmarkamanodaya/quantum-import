<?php

namespace Import\Translate\ETL;

/**
 * ETL Manager
 *
 * This class is intended to be used as an interface to the ETL system so that
 * ETL mappings can be loaded and processed using a clean interface. This class
 * uses the original lookup method where by the ETL mapping contains in it
 * several different external keys.  We then have to provide all of those keys
 * to be able to load the ETL profile from Mongo.
 *
 * @deprecated
 * @package Prepare
 */
class ExternalLookup extends AbstractMapping
{
    /**
     * @var string ETL Type
     */
    protected $_type = "Source Mapping";

    /**
     * @var string System type
     */
    protected $_systemType = "Spider";

    /**
     * Set the system name
     *
     * Ex: "Spider" / "BulkPost"
     *
     * @param string $systemName
     */
    public function setSystemType($systemName)
    {
        $this->_systemType = $systemName;
    }

    /**
     * Load ETL mapping
     * @param string $key
     */
    public function loadMapping($key)
    {
        $found = $this->_mapping->loadMapping(
            $this->_type,
            array(
                'type'     => $this->_systemType, // Mapping type
                'fileName' => $key
            )
        );

        /*
         * Check to see if we actually found a mapping
         */
        if (false === $found) {
            throw new \UnexpectedValueException(
                "Cannot find matching Source Mapping ETL Profile."
            );
        }
    }
}