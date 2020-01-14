<?php

namespace Import\Framework\ETL\Manage\Profile;

use Import\Framework\ETL\Db\Factory;

class Mapping extends AbstractProfile
{
    /**
     * ETL Profile type
     * @var string
     */
    protected $_profileType = "Mapping";

    /**
     * Ensure Indexes
     */
    protected function _ensureIndexes()
    {

        $this->_getCollection()->createIndex(
            array(
                "external.type"     => 1
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
        return Factory::getInstance()->get("etl|profiles");
    }
}