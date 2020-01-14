<?php

namespace Import\Framework\ETL\Manage\Profile;

use Import\Framework\ETL\Db\Factory;

class Extract extends AbstractProfile
{
    /**
     * ETL Profile type
     * @var string
     */
    protected $_profileType = "Extract";

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
        return Factory::getInstance()->get("etl|extract");
    }
}