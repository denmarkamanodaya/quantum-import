<?php

namespace Import\Framework\ETL;

use Import\Framework\ETL\Db\Factory;
use Import\Framework\ETL\Profile\Exception;
use Import\Framework\ETL\Profile\Extract;
use Import\Framework\ETL\Profile\Load;
use Import\Framework\ETL\Profile\Result;
use Import\Framework\ETL\Profile\Transform;

/**
 * ETL Profile
 *
 * The ETL Profile is a high-level container object that allows us to pull from
 * the database a collection of (1) Extract Profile, (1) Transform Profile, and
 * (1) Load Profile.  It also serves as a central interface for interacting with
 * each of those sub-components and further contains all the needed workflow
 * logic for initiating an ETL process.
 *
 * @package Import\Framework\ETL
 */
class Profile
{
    /**
     * @var array|bool Lookup used to find mappings
     */
    protected $_profileLookup = false;

    /**
     * @var \MongoCollection Database connection
     */
    protected $_db;

    /**
     * @var Extract
     */
    protected $_extract;

    /**
     * @var Transform
     */
    protected $_transform;

    /**
     * @var Load
     */
    protected $_load;

    /**
     * @var array
     */
    protected $_rawData;

    /**
     * @var array The underlying mapping profile loaded
     */
    protected $_mappingProfile;

    /**
     * Constructor
     *
     * @param array $config Optional config to set DB connection
     * @throws Exception
     * @throws \MongoConnectionException
     * @throws \MongoConnectionException
     */
    public function __construct($config = array())
    {
        if (!isset($config['db'])) {
            $config['db'] = Factory::getInstance()->get('etl|profiles');
        }

        // Integrity check
        if (!$config['db'] instanceof \MongoDB\Collection) {
            throw new Exception("Cannot set DB connection.  Invalid object.");
        }

        $this->_db = $config['db'];
    }

    /**
     * Load a mapping by Mongo ID
     *
     * This method is preferred since it allows you to more simply pull an ETL
     * mapping.
     *
     * The `loadMapping` was initially used so that you could supply various
     * "external keys" to the ETL.  The ETL would then have to be configured
     * with these internal keys for the match to be made.
     *
     * @param string|\MongoId $id MongoID
     * @return bool
     * @throws Exception
     * @throws \MongoConnectionException
     */
    public function loadMappingById($id)
    {
        if (is_string($id)) {

            // Mongo IDs should be 24 characters long
            if (strlen($id) !== 24) {
                throw new Exception("Invalid Mongo ID.  Expected 24 character MongoID string.");
            }

            $id = new \MongoDB\BSON\ObjectID($id);
        }

        if (!$id instanceof \MongoDB\BSON\ObjectID) {
            throw new Exception("Invalid Mongo ID.  Cannot load mapping.");
        }

        $mapping = $this->_db->findOne(array(
                "_id" => $id
            )
        );

        // Return an empty array rather than NULL if no match found
        if (!is_array($mapping)) {
            $mapping = array();
        }

        return $this->_loadMapping($mapping);
    }


    /**
     * Load a mapping
     *
     * This is the public-facing method for loading a mapping.  Internally we
     * have to find the mapping first via the `Mappings` collection and then
     * load the different ETL components.
     *
     * @param string $type
     * @param array $externalLookup
     * @return bool
     * @throws Exception
     * @throws \MongoConnectionException
     */
    public function loadMapping($type, array $externalLookup)
    {
        $lookup = $this->_buildLegacyLookup($type, $externalLookup);

        $mapping = $this->_findMapping(
            $lookup
        );
        
        $mapping = $mapping->getArrayCopy();

        return $this->_loadMapping($mapping);
    }

    /**
     * Build a mongo query from legacy inputs
     *
     * In 2016 we moved away from having lots of external keys in the main ETL
     * mapping profile.  Now we save the ETL's mongo ID in Item Input and call it
     * directly.
     *
     * This method maps the inputs to the `loadMapping` method into the expected
     * Mongo query array.
     *
     * @deprecated
     * @param string $type
     * @param array $externalLookup
     * @return array
     */
    protected function _buildLegacyLookup($type, array $externalLookup)
    {
        $lookup = array(
            "type" => $type
        );

        // TODO: Make this more flexible for non-AI2 applications
        $lookup['external.type'] = (isset($externalLookup['type']))
            ? $externalLookup['type']
            : 'default';

        // Dynamically build external query
        foreach ($externalLookup as $key => $value) {
            $lookup['external.' . $key] = $value;
        }

        return $lookup;
    }

    /**
     * Find a mapping
     *
     * This method looks up an ELT mapping via the `Mappings` collection.
     *
     * @param array $lookup
     * @return array
     */
    protected function _findMapping(array $lookup)
    {
        $mapping = $this->_db->findOne($lookup);

        $mapping = $mapping->getArrayCopy();

        // Return an empty array rather than NULL if no match found
        if (!is_array($mapping)) {
            return array();
        }

        return $mapping;
    }

    /**
     * Load mapping
     *
     * @param array $mappingArray
     * @return bool
     * @throws Exception
     * @throws \MongoConnectionException
     */
    protected function _loadMapping(array $mappingArray)
    {
        if (count($mappingArray) === 0 || !$this->_validateEtlConfig($mappingArray)) {
            return false;  // Invalid or missing ETL mapping
        }

        // Save a copy of the mapping array so we can inspect the mapping itself
        $this->_mappingProfile = $mappingArray;
        

        // Extract just the profile keys in a backward-compatible way
        $keys = $this->_extractProfileKeys($mappingArray);

        $this->setExtract(
            $keys['extract']
        );

        $this->setTransform(
            $keys['transform']
        );

        $this->setLoad(
            $keys['load']
        );

        return true;
    }

    /**
     * Extract profile keys
     *
     * This method provides backward compatibility with older profiles that save
     * the IDs of the ETL profiles in a property called `etl`.  Newer profiles
     * created by the ETL Admin will use the property `profileKey`.
     *
     * If neither `etl` or `profileKeys` are defined, we return FALSE rather
     * than throwing an exception.
     *
     * @param array $mapping
     * @return bool|array
     */
    protected function _extractProfileKeys(array $mapping)
    {
        if (isset($mapping['profileKeys'])) {
            return $mapping['profileKeys'];
        }
        elseif (isset($mapping['etl'])) {
            return $mapping['etl'];
        }

        return false;
    }


    /**
     * Is the profile ready to run?
     * @return bool
     */
    public function isReady()
    {
        return (
            $this->_extract instanceof Extract &&
            $this->_transform instanceof Transform &&
            $this->_load instanceof Load
        );
    }

    /**
     * Validate an ETL config
     *
     * @param array $config
     * @return bool
     * @throws Exception
     */
    protected function _validateEtlConfig($config)
    {
        if (!is_array($config)) {
            throw new Exception('Supplied config is not an array.');
        }

        /*
         * Throw exceptions everywhere since they would indicate bad data,
         * which we want to spot right away.
         */
        $keys = $this->_extractProfileKeys($config);

        if ($keys === false) {
            throw new Exception('No valid ETL definition found.');
        }


        $requiredEtlKeys = array(
            'extract',
            'transform',
            'load'
        );

        if (! $this->arrayContainsAllRequiredKeys($requiredEtlKeys, $keys)) {
            throw new Exception('Missing required ETL settings.');
        }

        return true;
    }


    /**
     * Set source data
     * @param array $data
     * @throws Extract\Exception
     * @throws Import\Framework\ETL\Exception
     */
    public function setSourceData($data)
    {
        $this->_rawData = $data;
        $this->_applySourceDataToExtract();
    }

    /**
     * Apply source data
     *
     * Takes the source data and applies it to the ETL Extract component
     *
     * @return bool
     * @throws Extract\Exception
     * @throws \Import\Framework\ETL\Exception
     */
    protected function _applySourceDataToExtract()
    {
        if ($this->_extract instanceof Extract && $this->_rawData) {
            $this->_extract->setData($this->_rawData);
            return true;
        }

        return false;
    }

    /**
     * Set ETL Extract
     * @param string|Extract $Extract
     * @throws Exception
     * @throws \MongoConnectionException
     * @throws \MongoConnectionException
     */
    public function setExtract($Extract)
    {
        if (is_string($Extract)) {
            $Extract = new Extract($Extract);
        }

        if (!$Extract instanceof Extract) {
            throw new Exception("Cannot add Extract profile.  Invalid extractor provided.");
        }

        $this->_extract = $Extract;

        $this->_applySourceDataToExtract();
    }

    /**
     * Set ETL Transform
     * @param string|Transform $Transform
     * @throws Exception
     * @throws \MongoConnectionException
     * @throws \MongoConnectionException
     */
    public function setTransform($Transform)
    {
        if (is_string($Transform)) {
            $Transform = new Transform($Transform);
        }

        if (!$Transform instanceof Transform) {
            throw new Exception("Cannot add Transform profile.  Invalid transformer provided.");
        }
        $this->_transform = $Transform;
    }

    /**
     * Set ETL Load
     * @param string|Load $Load
     * @throws Exception
     * @throws \MongoConnectionException
     * @throws \MongoConnectionException
     */
    public function setLoad($Load)
    {
        if (is_string($Load)) {
            $Load = new Load($Load);
        }

        if (!$Load instanceof Load) {
            throw new Exception("Cannot add Load profile.  Invalid loader provided.");
        }
        $this->_load = $Load;
    }

    /**
     * Get transform
     * @return Transform
     */
    public function getTransform()
    {
        return $this->_transform;
    }

    /**
     * Get extract
     * @return Extract
     */
    public function getExtract()
    {
        return $this->_extract;
    }

    /**
     * Get load
     * @return Load
     */
    public function getLoad()
    {
        return $this->_load;
    }

    /**
     * Get Result
     * @return Result
     */
    public function getResult()
    {
        return new Result($this);
    }

    /**
     * Run profile
     * @return string
     * @throws Exception
     * @throws Exception
     * @throws Exception
     */
    public function run()
    {
        if (!$this->isReady()) {
            throw new Exception("Cannot run ETL.  Profile not configured/ready.");
        }

        $this->getTransform()
            ->setExtract(
                $this->getExtract()
            );

        $this->getLoad()
            ->setTransform(
                $this->getTransform()
            );

        return $this->getLoad()
            ->getLoadedData();
    }

    /**
     * Array contains all required keys?
     *
     * Simple validation check to see if a given array contains every required key.  Note that we
     * don't do anything more here than just check that the array key exists.
     *
     * @param array $reqKeys
     * @param array $testArray
     * @return bool
     */
    protected function arrayContainsAllRequiredKeys($reqKeys, $testArray)
    {
        if (!is_array($reqKeys) || !is_array($testArray)) {
            return false;
        }

        return (0 === count(array_diff($reqKeys, array_keys($testArray))));
    }
}