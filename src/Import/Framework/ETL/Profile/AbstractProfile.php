<?php

namespace Import\Framework\ETL\Profile;

use Import\Framework\ETL\Db\Factory;

abstract class AbstractProfile
{
    /**
     * @var string Database config setting
     */
    protected $_dbConfigName;

    /**
     * @var string ID of mapping
     */
    protected $_mappingId;

    /**
     * @var array Mapping configuration
     */
    protected $_mappingConfig;

    /**
     * @var \MongoDB\Collection
     */
    protected $_db;


    /**
     * Class constructor
     *
     * @param string $mappingId ID of the mapping to initialize
     * @param array $config DI and optional parameter settings
     * @throws Exception
     * @throws \Import\Framework\ETL\Exception
     * @throws \MongoConnectionException
     * @throws \Import\Framework\ETL\Exception
     */
    public function __construct($mappingId, $config=array())
    {
        $this->_mappingId = $mappingId;

        /*
         * Set database connection
         */
        if (!isset($config['db'])) {
            $config['db'] = Factory::getInstance()->get($this->_getDbConfig());
        }

        // Allow implementing classes to initialize and do their own DI
        $config = $this->_init($config);

        if (!is_array($config)) {
            throw new Exception("Error initializing class.  No DI config returned.");
        }

        $this->_initConfig($config);
    }

    public function setDBCollection(\MongoDB\Collection $collection)
    {
        $this->_db = $collection;
    }

    /**
     * Load a profile from a config array
     *
     * This method can be used to manage an ETL profile without having to load
     * it from the database.  This is particularly helpful when testing a
     * profile.
     *
     * NOTE: This method uses late static bindings
     *   http://php.net/manual/en/language.oop5.late-static-bindings.php
     *
     * @param array $profileConfig
     * @return AbstractProfile
     * @throws Exception
     * @throws \MongoConnectionException
     */
    public static function getProfileFromConfig(array $profileConfig)
    {
        return new static('_MOCK_PROFILE_', array(
            'mappingConfig' => $profileConfig
        ));
    }


    protected function _getDbConfig()
    {
        if (!$this->_dbConfigName) {
            throw new \Import\Framework\ETL\Exception('Database config setting not defined.');
        }

        return $this->_dbConfigName;
    }

    /**
     * Initialize class
     *
     * Allows each child class to perform custom initialization and DI.  Note:
     * you must return an array.
     *
     * @param array $config  DI config object
     * @return array
     */
    protected function _init($config)
    {
        return $config;
    }

    /**
     * Initialize configuration
     *
     * This method loops over the config array passed to the constructor.  For
     * each item in the array it looks for a DI initializer (beginning with
     * "_setConfig") or a standard setter (beginning with "set").
     *
     * @param array $config
     */
    protected function _initConfig($config)
    {
        foreach ($config as $option => $value) {
            /*
             * Support internal '_setConfig' DI methods
             */
            $methodName = '_setConfig' . ucfirst(strtolower($option));
            if (method_exists($this, $methodName)) {
                $this->$methodName($value);
            }
        }
    }

    /**
     * Set the database connection
     * @param \MongoDB\Collection $db
     */
    protected function _setConfigDb(\MongoDB\Collection $db)
    {
        $this->_db = $db;
    }


    /**
     * Set Mapping Configuration (Dependency Injector)
     *
     * This method is used primarily for testing.  Since the main DB lookup
     * query caches its result into $this->_mappingConfig, we can preset a
     * mapping with whatever config we want by simply pre-filling the
     * _mappingConfig property with a properly formatted array.  This is great
     * for unit tests.
     *
     * @param string|array $config Mapping config override
     * @throws Exception
     */
    protected function _setConfigMappingConfig($config)
    {
        if (is_string($config)) {

            if (trim($config) === "") {
                throw new Exception("No mapping config provided.");
            }

            $config = json_decode($config, true);

            if (JSON_ERROR_NONE !== json_last_error()) {
                throw new Exception("Invalid MappingConfig JSON string.");
            }
        }

        if (!is_array($config)) {
            throw new Exception("Invalid MappingConfig DI.  Must be an array.");
        }

        $this->_mappingConfig = $config;
    }


    /**
     * Load the mapping from the database
     *
     * Note: This method relies on the _db property being set correctly prior to
     * being called.
     *
     * @throws Exception
     * @return array Mapping Config
     */
    protected function _loadMapping()
    {
        if (!$this->_mappingId || !is_string($this->_mappingId)) {
            throw new Exception('Invalid mapping ID.  Must be a string.');
        }

        if (!$this->_db instanceof \MongoDB\Collection) {
            throw new Exception("Cannot lookup mapping.  Invalid database connection.");
        }

        /*
         * Find the mapping in Mongo
         *
         * We allow for two ways to find the mapping.
         *   1.) You can use an arbitrary key
         *   2.) You can use the literal MongoId string
         */
        if (preg_match('/[a-z0-9]{24}/', $this->_mappingId)) {
            $lookup = array(
                '_id' => new \MongoDB\BSON\ObjectID($this->_mappingId)
            );
        }
        else {
            $lookup = array(
                'key' => $this->_mappingId
            );
        }

        $data = $this->_db->findOne($lookup);


        if (!$data) {
            throw new Exception("Sorry, no mapping found with the ID '{$this->_mappingId}'.");
        }

        return $data;
    }

    /**
     * Get a mapping config
     *
     * @param string $option Optional sub-element to select in config
     * @return array
     * @throws Exception
     */
    public function getMappingConfig($option=null)
    {
        /*
         * If the config hasn't been loaded yet, do so now
         */
        if (!$this->_mappingConfig) {
            $this->_mappingConfig = $this->_loadMapping();
        }

        /*
         * Optionally allow direct access to top-level config elements
         */
        if ($option) {
            if (isset($this->_mappingConfig[$option])) {
                // Top-level item found
                return $this->_mappingConfig[$option];
            }
            else {
                // Top-level not found, return NULL
                return null;
            }
        }

        /*
         * Default behavior is to return entire config
         */
        return $this->_mappingConfig;
    }

    /**
     * Get ETL component ID
     *
     * This returns the Mongo ID (as a string) for the current ETL component.
     *
     * @return string
     */
    public function getId()
    {
        $id = $this->getMappingConfig("_id");

        if ($id instanceof \MongoDB\BSON\ObjectID) {
            return $id->id;
        }

        return $id;
    }

    /**
     * Get version number
     *
     * Returns a floating point number representation of this profile's version
     * number.  If there is not version number defined by the ETL profile, the
     * version defaults to 1.0.
     *
     * @return float
     */
    public function getVersion()
    {
        $version = $this->getMappingConfig('version');

        if ($version && is_numeric($version)) {
            return (float) $version;
        }

        return 1.0;
    }
}