<?php

namespace Import\Framework\ETL\Manage\Profile;

use Import\Framework\ETL\Manage\Exception;

abstract class AbstractProfile
{
    /**
     * Profile type (required)
     * @var string
     */
    protected $_profileType = null;

    /**
     * @var array Keys to return in search results
     */
    protected $_searchResultKeys = array(
        'name'        => 1,
        'key'         => 1,
        'type'        => 1,
        'description' => 1,
        'comments'    => 1, // Older profiles use "comments" not "description",
        'created'     => 1,
        'modified'    => 1
    );

    abstract protected function _ensureIndexes();

    /**
     * Get the profile's Mongo collection
     * @return \MongoCollection
     */
    abstract protected function _getCollection();

    /**
     * Get the key that should be used to identify this profile
     * @param array $profile
     * @return array
     */
    protected function _getProfileQuery($profile)
    {
        // Use the _id primary key by default
        if (isset($profile['_id'])) {

            // If we already have a mongo ID object just use it
            if ($profile['_id'] instanceof \MongoDB\BSON\ObjectID) {
                array(
                    '_id' => $profile['_id']
                );
            }

            // Create a MongoID object from string ID
            return array(
                '_id' => new \MongoDB\BSON\ObjectID($profile['_id'])
            );
        }

        if (isset($profile['key'])) {
            return array(
                'key' => $profile['key']
            );
        }

        return false;
    }

    /**
     * Get profile type
     * @return string
     * @throws Exception
     */
    public function getType()
    {
        if (!isset($this->_profileType)) {
            throw new Exception("Missing core profile type.");
        }

        if (!in_array($this->_profileType, array("Extract", "Transform", "Load", "Mapping"))) {
            throw new Exception("Invalid core profile type.");
        }

        return $this->_profileType;
    }

    /**
     * Validate a profile
     * @param array $profile
     * @return array
     * @throws Exception
     */
    public function validateProfile($profile)
    {
        $Validator = new \Import\Framework\ETL\Manage\Validate($this->getType());
        $validate = $Validator->validateProfile($profile);

        return $validate;
    }


    public function getProfile($query)
    {
        $profile = $this->_getCollection()->findOne($query);
        $profile['test'] = __CLASS__;
        return $this->_filterBeforeRead($profile);
    }

    /**
     * Save a profile
     * @param array $profile
     * @return bool
     * @throws Exception
     * @throws \Import\Framework\ETL\Manage\Validate\Exception
     * @throws \MongoCursorException
     * @throws \MongoCursorTimeoutException
     * @throws \MongoException
     * @throws \MongoCursorException
     */
    public function saveProfile($profile)
    {
        $profile = $this->_setDefaults($profile);

        $validationResults = $this->validateProfile($profile);

        if (!$validationResults['isValid']) {
            throw new \Import\Framework\ETL\Manage\Validate\Exception(
                "Error validating profile",
                406,
                $validationResults['violations']
            );
        }

        $collection = $this->_getCollection();

        $query = $this->_getProfileQuery($profile);

        if ($query) {
            $result = $collection->update(
                $query,
                $profile,
                array(
                    'upsert' => 1
                )
            );
        }
        else {
            $result = $collection->insert(
                $profile
            );
        }

        return $this->_readQueryResult($result, 'saving');
    }

    public function deleteProfile($profile)
    {
        $query = $this->_getProfileQuery($profile);

        if (!$query) {
            throw new Exception("Cannot determine document ID or key for deletion.");
        }

        $result = $this->_getCollection()->remove(
            $query
        );

        return $this->_readQueryResult($result, 'deleting');
    }


    public function searchProfiles($query=array())
    {
        $cursor = $this->_getCollection()->find(
            $query,
            $this->_searchResultKeys
        );

        $cursor->sort(array(
            'name' => 1
        ));

        $results = array();

        foreach ($cursor as $profile) {
            $profile['_id'] = (string) $profile['_id'];
            $profile['created'] = (isset($profile['created']) && $profile['created'] instanceof \MongoDB\BSON\UTCDateTime)
                ? date("m/d/Y g:i:s a", $profile['created']->sec)
                : '-';
            $profile['modified'] = (isset($profile['modified']) && $profile['modified'] instanceof \MongoDB\BSON\UTCDateTime)
                ? date("m/d/Y g:i:s a", $profile['modified']->sec)
                : '-';
            $results[] = $profile;
        }

        return $results;
    }


    /**
     * @param $result
     * @param $action
     * @return bool
     * @throws Exception
     */
    protected function _readQueryResult($result, $action)
    {
        if (!is_array($result) || !array_key_exists('ok', $result)) {
            throw new Exception("An unknown error occurred {$action} this document.");
        }

        if (isset($result['err'])) {
            throw new Exception("Error {$action} document: '" . $result['errmsg'] . "'");
        }

        if ($result['ok'] == 1) {
            return true;
        }
        return false;
    }

    /**
     * Set defaults
     *
     * This method sets some default values and does some minor conversion of
     * the _id to allow for JSON formatting of the MongoID object.
     *
     * @param $profile
     * @return mixed
     */
    protected function _setDefaults($profile)
    {
        if (isset($profile['_id']['$id'])) {
            $profile['_id'] = new \MongoDB\BSON\ObjectID($profile['_id']['$id']);
        }

        if (!isset($profile['created'])) {
            $profile['created'] = new \MongoDB\BSON\UTCDateTime();
        }
        elseif (isset($profile['created']['sec'])) {
            $profile['created'] = new \MongoDB\BSON\UTCDateTime(
                $profile['created']['sec'],
                $profile['created']['usec']
            );
        }

        $profile['modified'] = new \MongoDB\BSON\UTCDateTime();

        return $this->_filterBeforeSave($profile);
    }


    protected function _filterBeforeSave(array $profile)
    {
        return $profile;
    }

    protected function _filterBeforeRead(array $profile)
    {
        return $profile;
    }
}