<?php

namespace Import\Framework\AI2\Model\Source;

use Import\Framework\AI2\Model\AbstractModel;

class FileConfig extends AbstractModel
{
    const CONFIG_ETL_SOURCE_MAP = 'etl_source_mapping_id';
    const CONFIG_FILE_NAME      = 'file_name';


    /**
     * @var array Mapping of database column names to cleaner aliases
     */
    static protected $_columnAliasMap = array(
        'input_source_file_id'           => 'fileId',
        'input_source_file_config_name'  => 'name',
        'input_source_file_config_value' => 'value'
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
            'input_source_file_config',
            $update,
            'input_source_config_file_id',
            $this->_id,
            self::_getDb()
        );
    }


    public function delete()
    {
        $numAffected = $this->_db->query('
            DELETE FROM input_source_file_config
            WHERE input_source_config_file_id = :id',
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
                input_source_file_id            AS 'fileId',
                input_source_file_config_name   AS 'name',
                input_source_file_config_value  AS 'value'
            FROM input_source_file_config
            WHERE
                input_source_config_file_id = :id",
            array(
                ":id" => $this->_id
            )
        );
    }

    static public function factoryNew($values)
    {
        $insert = self::_mapAliases(self::$_columnAliasMap, $values, true);

        return self::insertIntoTable(
            'input_source_file_config',
            array (
                'input_source_file_id'           => $insert['input_source_file_id'],
                'input_source_file_config_name'  => $insert['input_source_file_config_name'],
                'input_source_file_config_value' => $insert['input_source_file_config_value']
            ),
            self::_getDb()
        );
    }

    /**
     * Get all file config settings
     * @param int $fileId File ID
     * @return array
     */
    static public function getAll($fileId)
    {
        $sql = "
            SELECT
              input_source_file_config_name  AS 'name',
              input_source_file_config_value AS 'value'
            FROM input_source_file_config
            WHERE
              input_source_file_id = :id";

        $configData = self::_getDb()->fetchAll(
            $sql,
            array(
                ":id" => $fileId
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
     * Is a hash of the file name required?
     *
     * @param string $fileName
     * @return bool
     */
    static public function isFileNameHashNeeded($fileName)
    {
        return (strlen($fileName) > 100);
    }


    /**
     * @param $fileId
     * @param $fileName
     * @throws \Import\Framework\AI2\Model\Exception
     */
    static public function saveFileNameConfig($fileId, $fileName)
    {
        if (self::isFileNameHashNeeded($fileName)) {

            $Config = self::findConfig($fileId, 'file_name');

            // Config does not exist. Insert it.
            if ($Config === false) {
                // Set file name config value
                self::factoryNew(array(
                    'fileId' => $fileId,
                    'name' => 'file_name',
                    'value' => $fileName
                ));
            }
            // Config exists.  Update it.
            else {
                $Config->update(array(
                    'name'  => 'file_name',
                    'value' => $fileName
                ));
            }
        }
        else {
            // Remove old file name config value if it exists
            self::_removeConfig($fileId, 'file_name');
        }
    }

    /**
     * @param $fileId
     * @param string $configName
     * @return bool|FileConfig
     * @throws \Import\Framework\AI2\Model\Exception
     */
    static public function getEtlSourceProfileConfig($fileId, $configName=FileConfig::CONFIG_ETL_SOURCE_MAP)
    {
        return self::findConfig($fileId, $configName);
    }

    /**
     * Remove a config setting by file ID and name
     *
     * @param int $fileId
     * @param string $configName
     */
    static protected function _removeConfig($fileId, $configName)
    {
        $sql = "
            DELETE FROM input_source_file_config
            WHERE
                input_source_file_id = :fileId
                AND input_source_file_config_name = :configName";

        self::_getDb()->query($sql, array(
            ':fileId'     => $fileId,
            ':configName' => $configName
        ));
    }

    /**
     * Find config
     *
     * @param int $fileId
     * @param string $configName
     * @return FileConfig|bool
     * @throws \Import\Framework\AI2\Model\Exception
     */
    static public function findConfig($fileId, $configName)
    {
        $sql = "
            SELECT input_source_config_file_id
            FROM input_source_file_config
            WHERE
                input_source_file_id = :fileId
                AND input_source_file_config_name = :name";

        $id = self::_getDb()->fetchOne($sql, array(
            ':fileId' => $fileId,
            ':name'   => $configName
        ));


        if ( ! is_numeric($id)) {
            return false;
        }


        return new self($id);
    }

}