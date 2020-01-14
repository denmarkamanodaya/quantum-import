<?php

namespace Import\Framework\ETL\Manage;

class Validate
{
    /**
     * @var string Profile type to validate
     */
    protected $_type;

    /**
     * @var array Valid types
     */
    protected $_validTypes = array(
        "Mapping",
        "Extract",
        "Transform",
        "Load"
    );

    /**
     * Class constructor
     * @param string $type
     * @throws Exception
     */
    public function __construct($type)
    {
        if (!in_array($type, $this->_validTypes)) {
            throw new Exception("Invalid profile type: " . $type);
        }

        $this->_type = $type;
    }

    /**
     * Convert an array to a validate-able object
     * @param array $array
     * @return mixed
     */
    public function arrayToObject($array)
    {
        if (!is_array($array)) {
            $array = (array) $array;
        }

        $array = $this->_serializeMongoObjects($array);

        return json_decode(json_encode($array));
    }


    /**
     * Serialize common Mongo classes
     *
     * Since JSON validation doesn't work if you have Mongo objects mixed into
     * your data, we use this method to serialize some common Mongo objects
     * so that they can be included and validated.
     *
     * @param array $array
     * @return array
     */
    protected function _serializeMongoObjects($array)
    {
        foreach ($array as &$item) {
            if (is_array($item)) {
                $item = $this->_serializeMongoObjects($item);
            }

            if ($item instanceof \MongoDB\BSON\ObjectID) {
                $item = array("bsonObject" => "MongoId", "value" => $item->{'$id'});
            }
            elseif ($item instanceof \MongoDB\BSON\UTCDateTime) {
                $item = array("bsonObject" => "MongoDate", "value" => $item->sec);
            }
        }

        return $array;
    }

    /**
     * Validate an ETL profile
     * @param $profile
     * @throws Exception
     * @return array
     */
    public function validateProfile($profile)
    {
        $profile    = $this->arrayToObject($profile);
        $jsonSchema = $this->_getProfileValidationSchema();

        // No reference resolver used

        $Validator  = new \JsonSchema\Validator();
        $Validator->check($profile, $jsonSchema);

        if ($Validator->isValid()) {
            return array(
                'isValid' => true,
                'violations' => array()
            );
        }
        else {
            return array(
                'isValid' => false,
                'violations' => $Validator->getErrors()
            );
        }
    }

    /**
     * Get profile JSON validation schema
     * @return object
     * @throws Exception
     */
    protected function _getProfileValidationSchema()
    {
        $profileType = $this->_type;

        $baseDir = __DIR__ . '/../configs/schemas/';
        if (!is_dir($baseDir)) {
            throw new Exception("Cannot locate ETL profile validation schema directory.");
        }

        $fileName = $baseDir . $profileType . 'Profile.json';
        if (!is_readable($fileName)) {
            throw new Exception("Cannot locate validation schema for " . $profileType);
        }

        $Retriever = new \JsonSchema\Uri\UriRetriever();
        return $Retriever->retrieve("file://" . realpath($fileName));
    }
}