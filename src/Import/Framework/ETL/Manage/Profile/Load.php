<?php

namespace Import\Framework\ETL\Manage\Profile;

use Import\Framework\ETL\Profile\Load as LoadProfile;
use Import\Framework\ETL\Db\Factory;

class Load extends AbstractProfile
{
    /**
     * ETL Profile type
     * @var string
     */
    protected $_profileType = "Load";


    /**
     * Ensure Indexes
     */
    protected function _ensureIndexes()
    {
        $this->_getCollection()->createIndex(
            array(
                "keys.component" => 1,
                "keys.function"  => 1,
                "keys.id"        => 1
            )
        );
    }

    /**
     * Get the profile's Mongo collection
     * @return \MongoCollection
     * @throws \MongoConnectionException
     * @throws \MongoConnectionException
     */
    protected function _getCollection()
    {
        return Factory::getInstance()->get("etl|load");
    }

    protected function _filterBeforeSave(array $profile)
    {
        /** @var LoadProfile $LoadProfile */
        $LoadProfile = LoadProfile::getProfileFromConfig($profile);

        if ($LoadProfile->getFieldSourceType() == LoadProfile::FIELD_SOURCE_TEMPLATE_API) {
            $profile['fields'] = array();
        }

        return $profile;
    }

    protected function _filterBeforeRead(array $profile)
    {
        /** @var LoadProfile $LoadProfile */
        $LoadProfile = LoadProfile::getProfileFromConfig($profile);

        $fields = $LoadProfile->getFields();

        $profile['fields'] = $fields;

        return $profile;
    }
}