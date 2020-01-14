<?php

namespace Import\Framework\AI2\Model\Source;

use Import\Framework\AI2\Model\AbstractModel;
use Import\Framework\AI2\Model\Source\FileConfig;

class File extends AbstractModel
{
    /**
     * @var array
     */
    protected $_configCache;

    /**
     * @var array Mapping of database column names to cleaner aliases
     */
    static protected $_columnAliasMap = array(
        'input_source_id'         => 'sourceId',
        'input_source_filename'   => 'fileName',
        'input_source_is_enabled' => 'enabled'
    );

    /**
     * Get a file config value
     * @param string $name
     * @return string
     */
    public function getConfigValue($name)
    {
        $data = $this->getAllConfig();
        if (isset($data[$name])) {
            return $data[$name];
        }
        return null;
    }

    /**
     * Get all file config settings
     * @return array
     */
    public function getAllConfig()
    {
        // Simple caching
        if (is_array($this->_configCache)) {
            return $this->_configCache;
        }

        $this->_configCache = FileConfig::getAll($this->_id);

        return $this->_configCache;
    }

    /**
     * Update file
     *
     * @param array $values
     * @throws Exception
     * @throws \Import\Framework\AI2\Model\Exception
     */
    public function update($values)
    {
        if (!is_array($values)) {
            throw new Exception("Cannot update file.  Invalid values.");
        }

        $update = self::_mapAliases(self::$_columnAliasMap, $values);

        // File names over 100 characters will be truncated.  Use the SHA1 Hash
        // but be sure to also log the full file name in the file config so we
        // have a reference.
        if (FileConfig::isFileNameHashNeeded($values['fileName'])) {
            $update['input_source_filename'] = sha1($update['input_source_filename']);
        }

        self::updateTable(
            'input_source_file',
            $update,
            'input_source_file_id',
            $this->_id,
            $this->_db
        );

        // Update the file config for hashing.
        FileConfig::saveFileNameConfig($this->_id, $values['fileName']);
    }

    /**
     * Get file data
     * @return array
     */
    protected function _getData()
    {
        $sql = "
            SELECT
                sf.input_source_file_id                 AS 'id',
                input_source_id                         AS 'sourceId',
                input_source_filename                   AS 'fileName',
                input_source_file_is_enabled            AS 'enabled',
                created_by                              AS 'createdBy',
                created_date                            AS 'createdDate',
                updated_by                              AS 'updatedBy',
                updated_date                            AS 'updatedDate',
                sfc_file.input_source_file_config_value AS 'altFileName',
                sfc_etl.input_source_file_config_value  AS 'etlSourceMap'
            FROM input_source_file sf
            LEFT JOIN input_source_file_config sfc_file ON 
                sfc_file.input_source_file_id = sf.input_source_file_id
                AND sfc_file.input_source_file_config_name = 'file_name'
            LEFT JOIN input_source_file_config sfc_etl ON 
                sfc_etl.input_source_file_id = sf.input_source_file_id
                AND sfc_etl.input_source_file_config_name = 'etl_source_mapping_id'
            WHERE
                sf.input_source_file_id = :id";

        return $this->_db->fetchRow(
            $sql,
            array(
                ":id" => $this->_id
            )
        );
    }

    /**
     * Factory - Create a new file
     * @param $values
     * @return bool
     * @throws Exception
     * @throws \Import\Framework\AI2\Model\Exception
     */
    static public function factoryNew($values)
    {
        if (!is_array($values)) {
            throw new Exception("Cannot create new file.  Invalid data.");
        }

        $insert = self::_mapAliases(self::$_columnAliasMap, $values, true);

        // File names over 100 characters will be truncated.  Use the SHA1 Hash
        // but be sure to also log the full file name in the file config so we
        // have a reference.
        if (strlen($values['fileName']) > 100) {
            $insert['input_source_filename'] = sha1($insert['input_source_filename']);
        }

        $db = self::_getDb();

        $id = self::insertIntoTable(
            'input_source_file',
            $insert,
            $db
        );

        if ($id === false) {
            return false;
        }

        FileConfig::saveFileNameConfig($id, $values['fileName']);

        return $id;
    }

    /**
     * Enable a file
     */
    public function enable()
    {
        $this->_db->query(
            /** @lang TSQL */ "
            UPDATE input_source_file
            SET
              input_source_file_is_enabled = 1,
              updated_date = NOW()
            WHERE
              input_source_file_id = :id",
            array(
                ":id" => $this->_id
            )
        );
    }

    /**
     * Disable a file
     */
    public function disable()
    {
        $this->_db->query(
            /** @lang TSQL */ "
            UPDATE input_source_file
            SET 
              input_source_file_is_enabled = 0,
              updated_date = NOW()
            WHERE 
              input_source_file_id = :id",
            array(
                ":id" => $this->_id
            )
        );
    }


    static public function findAllRSSFiles()
    {
        $sql ="
            SELECT
                js.input_source_id                    AS 'id',
                js.input_source_type_id               AS 'typeId',
                jst.input_source_type_name            AS 'typeName',
                spider.profile.spider_name            AS 'name',
                js.input_source_is_enabled            AS 'sourceEnabled',
                jsf.input_source_filename             AS 'fileName',
                CASE
                    WHEN jsfc.input_source_file_config_value IS NULL THEN jsf.input_source_filename
                ELSE jsfc.input_source_file_config_value 
                END AS 'feedUrl', 
                (
                    SELECT MAX(started)
                    FROM input_source_file_log
                    WHERE
                        input_source_file_id = jsf.input_source_file_id
                ) AS 'lastStarted',
                (
                    SELECT MAX(completed)
                    FROM input_source_file_log
                    WHERE
                        input_source_file_id = jsf.input_source_file_id
                ) AS 'lastCompleted',
                (
                    SELECT MAX(jsjl.created_date)
                    FROM input_source_file_log jsfl
                    JOIN  input_source_item_log jsjl ON
                        jsfl.input_source_file_log_id = jsjl.input_source_file_log_id
                    WHERE
                        jsfl.input_source_file_id = jsf.input_source_file_id
                ) AS 'lastItemLogged'
            FROM input_source js
            JOIN input_source_type jst ON
                js.input_source_type_id = jst.input_source_type_id
            JOIN spider_profile ON
                spider_profile.spider_profile_id = js.spider_profile_id
            JOIN input_source_file jsf ON
                jsf.input_source_id = js.input_source_id
            LEFT JOIN input_source_file_config jsfc ON
                jsf.input_source_file_id = jsfc.input_source_file_id
                AND jsfc.input_source_file_config_name = 'file_name'
            WHERE
                jst.input_source_type_name = 'RSS'
            ORDER BY
                spider.profile.spider_name,
                jsfc.input_source_file_config_value";

        return self::_getDb()->fetchAll($sql);
    }
}