<?php

namespace Import\Framework\AI2\Model\Source;

use Import\Framework\AI2\Model\AbstractModel;

/**
 * Source Type
 *
 * This is a simple wrapper around the source type table.
 *
 * @package Import\Framework\AI2\Source
 */
class Type extends AbstractModel
{
    /**
     * @var array Mapping of database column names to cleaner aliases
     */
    static protected $_columnAliasMap = array(
        'input_source_type_name'        => 'name',
        'input_source_type_code'        => 'code',
        'input_source_type_description' => 'description'
    );

    /**
     * Factory - Create new source type
     * @param array $values
     * @return Type
     * @throws Exception
     * @throws \Import\Framework\AI2\Model\Exception
     */
    static public function factoryNew($values)
    {
        if (!is_array($values)) {
            throw new Exception("Cannot create source type.  Invalid data.");
        }

        $db = self::_getDb();

        $insert = self::_mapAliases(self::$_columnAliasMap, $values);

        $id = self::insertIntoTable(
            'input_source_type',
            $insert,
            $db
        );

        return new self($id);
    }


    /**
     * Get all source types
     * @return array
     */
    static public function findAll()
    {
        $sql = "
            SELECT
                input_source_type_id          AS 'id',
                input_source_type_name        AS 'name',
                input_source_type_description AS 'description',
                input_source_type_code        AS 'code'
            FROM input_source_type
            ORDER BY input_source_type_name";

        $results = self::_getDb()->fetchAll($sql);

        $pivoted = array();

        foreach ($results as $type) {
            $pivoted[ (int) $type['id'] ] = $type;
        }

        return $pivoted;
    }


    /**
     * Get all source API types
     * @return array
     */
    static public function findAllApiTypes()
    {
        $sql = "
            SELECT
                input_source_api_type_id          AS 'id',
                input_source_api_type_name        AS 'name',
                input_source_api_type_description AS 'description',
                input_source_api_type_code        AS 'code'
            FROM input_source_api_type
            ORDER BY input_source_api_type_name";

        $results = self::_getDb()->fetchAll($sql);

        $pivoted = array();

        foreach ($results as $type) {
            $pivoted[ (int) $type['id'] ] = $type;
        }

        return $pivoted;
    }


    protected function _getData()
    {
        $sql = "
            SELECT
                input_source_type_id          AS 'id',
                input_source_type_name        AS 'name',
                input_source_type_code        AS 'code',
                input_source_type_description AS 'description',
                created_by                    AS 'createdBy',
                created_date                  AS 'createdDate',
                updated_by                    AS 'updatedBy',
                updated_date                  AS 'updatedDate'
            FROM input_source_type
            WHERE
                input_source_type_id = :id";

        return $this->_db->fetchRow($sql, array(":id" => $this->_id));
    }

    /**
     * Update source type
     * @param array $values
     * @return bool
     * @throws Exception
     * @throws \Import\Framework\AI2\Model\Exception
     */
    public function update($values)
    {
        if (!is_array($values)) {
            throw new Exception("Unable to update.  Invalid values.");
        }

        $update = self::_mapAliases(self::$_columnAliasMap, $values);

        $numAffected = self::updateTable(
            'input_source_type',
            $update,
            'input_source_type_id',
            $this->_id,
            $this->_db
        );

        return $numAffected == 1;
    }
}