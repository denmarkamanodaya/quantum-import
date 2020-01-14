<?php

namespace Import\Framework\AI2\Model\Source;

use Import\Framework\AI2\Model\AbstractModel;

class SourceConfig extends AbstractModel
{
    /**
     * @var array Mapping of database column names to cleaner aliases
     */
    static protected $_columnAliasMap = array(
        'input_source_id'           => 'sourceId',
        'input_source_config_name'  => 'name',
        'input_source_config_value' => 'value'
    );

    /**
     * @param $values
     * @return int
     * @throws \Import\Framework\AI2\Model\Exception
     */
    public function update($values)
    {
        $update = self::_mapAliases(self::$_columnAliasMap, $values);

        return self::updateTable(
            'input_source_config',
            $update,
            'input_source_config_id',
            $this->_id,
            self::_getDb()
        );
    }


    public function delete()
    {
        $numAffected = $this->_db->query('
            DELETE FROM input_source_config
            WHERE input_source_config_id = :id',
            array(
                ':id' => $this->_id
            )
        );

        return ($numAffected === 1);
    }

    protected function _getData()
    {
        return $this->_db->fetchRow("
            SELECT 
                input_source_id            AS 'sourceId',
                input_source_config_name   AS 'name',
                input_source_config_value  AS 'value'
            FROM input_source_config
            WHERE
                input_source_config_id = :id",
            array(
                ":id" => $this->_id
            )
        );
    }

    static public function factoryNew($values)
    {
        $insert = self::_mapAliases(self::$_columnAliasMap, $values, true);

        return self::insertIntoTable(
            'input_source_config',
            array (
                'input_source_id'           => $insert['input_source_id'],
                'input_source_config_name'  => $insert['input_source_config_name'],
                'input_source_config_value' => $insert['input_source_config_value']
            ),
            self::_getDb()
        );
    }

    /**
     * Get all file config settings
     * @param int $sourceId Source ID
     * @return array
     */
    static public function getAll($sourceId)
    {
        $sql = "
            SELECT
              input_source_config_name  AS 'name',
              input_source_config_value AS 'value'
            FROM input_source_config
            WHERE
              input_source_id = :id";

        $configData = self::_getDb()->fetchAll(
            $sql,
            array(
                ":id" => $sourceId
            )
        );

        $pivot = array();

        if (is_array($configData)) {
            // Pivot data
            foreach ($configData as $config) {
                $pivot[$config['name']] = $config['value'];
            }
        }

        return $pivot;
    }

    /**
     * Find config
     *
     * @param int $sourceId
     * @param string $configName
     * @return SourceConfig|bool
     * @throws \Import\Framework\AI2\Model\Exception
     */
    static public function findConfig($sourceId, $configName)
    {
        $sql = "
            SELECT input_source_config_id
            FROM input_source_config
            WHERE
                input_source_id = :sourceId
                AND input_source_config_name = :name";

        $id = self::_getDb()->fetchOne($sql, array(
            ':sourceId' => $sourceId,
            ':name'   => $configName
        ));


        if ( ! is_numeric($id)) {
            return false;
        }


        return new self($id);
    }

}