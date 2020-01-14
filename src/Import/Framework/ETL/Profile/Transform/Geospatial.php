<?php

namespace Import\Framework\ETL\Profile\Transform;

use Import\Framework\ETL\Db\Factory;

class Geospatial extends AbstractTransform
{
    /**
     * @var \MongoCollection
     */
    protected $_db;

    protected $_supportedMethods = array(
        'findZipByStateCity'
    );

    public function __construct($config = array())
    {
        if (!isset($config['db'])) {
            $config['db'] = Factory::getInstance()->get('etl|geospatial');
        }

        $this->_db = $config['db'];
    }

    /**
     * Get transformation methods
     *
     * Returns an array of supported transformation methods
     *
     * @return array
     */
    public function getMethods()
    {
        return $this->_supportedMethods;
    }


    /**
     * Find a USA zip code by state and city
     *
     * @Param string State name
     * @Param string City name
     *
     * @param string $state
     * @param string $city
     * @return array|null
     */
    public function findZipByStateCity($state, $city)
    {
        /*
         * If $state is 2 characters long, treat it as a state code
         */
        $stateFieldName = "state_name";
        if (strlen($state) == 2) {
            $stateFieldName = "state_code";
            $state = strtoupper($state);  // State codes have to be uppercase
        }

        $zipCode = $this->_db->findOne(
            array(
                $stateFieldName => trim($state),
                // Our zip DB has uppercase city names
                'city' => trim(strtoupper($city))
            ),
            array(
                'postal_code' => 1
            )
        );

        // Return an empty string if we didn't find a zip code
        if (!$zipCode || !isset($zipCode['postal_code'])) {
            return "";
        }

        // Convert to string
        $zipCode = (string) $zipCode['postal_code'];

        // If zip is less than 5 digits, pad with 0's on the left
        if (strlen($zipCode) < 5) {
            $zipCode = str_pad($zipCode, 5, '0', STR_PAD_LEFT);
        }

        return $zipCode;
    }
}