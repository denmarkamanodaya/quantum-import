<?php

namespace Import\Framework\AI2\Model;
use Import\Framework\AI2\Model\Source\File;
use Import\Framework\AI2\Model\Source\Exception;

/**
 * Source - Data Model Abstraction
 * @package Import\Framework\AI2
 */
class Source extends AbstractModel
{
    /**
     * @var int
     */
    protected $_id;

    /**
     * @var array
     */
    protected $_dataCache;

    /**
     * @var array Mapping of database column names to cleaner aliases
     */
    static protected $_columnAliasMap = array(
        'input_source_type_id'        => 'typeId',
        'spider_profile_id'           => 'spiderProfileId',
        'input_source_code'           => 'code',
        'input_source_is_enabled'     => 'enabled'
    );


    /**
     * Factory - Get instance by user name and file name
     * @param string $userName
     * @param string $fileName
     * @return Source
     * @throws \Import\Framework\AI2\Model\Exception
     */
    static public function factoryByUserFile($userName, $fileName)
    {
        $db = self::_getDb();
        $sql = "
            SELECT
              src.input_source_id              AS 'id'
            FROM input_source src
            INNER JOIN input_source_file f ON
              f.input_source_id = src.input_source_id
            INNER JOIN spider_profile ON
              spider_profile.spider_profile_id = src.spider_profile_id
            WHERE
              -- Convert * in stored filename to % and use in LIKE
              (
                :filename LIKE REPLACE(f.input_source_filename, '*', '%')
                --OR f.input_source_filename = sys.fn_varbintohexsubstring(0, HASHBYTES('SHA1', :filename), 1, 0)
              ) AND
              spider_profile.spider_name = :username";

        $id = $db->fetchOne(
            $sql,
            array(
                ':filename' => $fileName,
                ':username' => $userName
            )
        );

        return new self($id);
    }

    /**
     * Factory - Create new Source
     *
     * This method accepts an array of aliased source fields.  Those fields are
     * then mapped to the raw column names in the database.  Finally an insert
     * into the DB is made and an instantiated Source object is returned under
     * the newly generated source ID.
     *
     * @param $values
     * @return Source
     * @throws Exception
     * @throws \Import\Framework\AI2\Model\Exception
     */
    static public function factoryNew($values)
    {
        if (!array_key_exists('enabled', $values)) {
            $values['enabled'] = 1;
        }

        $values['enabled'] = ($values['enabled']) ? 1 : 0;

        self::validateInsertData($values);

        $db = self::_getDb();

        $insert = self::_mapAliases(self::$_columnAliasMap, $values, true);

        $id = self::insertIntoTable(
            'input_source',
            $insert,
            $db
        );

        return new self($id);
    }

    /**
     * Update a source
     * @param array $newValues
     * @return bool
     * @throws Exception
     * @throws \Import\Framework\AI2\Model\Exception
     */
    public function update($newValues)
    {
        if (!is_array($newValues)) {
            throw new Exception("Cannot update source.  Invalid values.");
        }

        if (isset($newValues['enabled'])
            && $newValues['enabled'] != 1
            && $newValues['enabled'] != 0) {

            throw new Exception("Invalid enable/disable value.");
        }

        self::updateTable(
            'input_source',
            self::_mapAliases(self::$_columnAliasMap, $newValues),
            "input_source_id",
            $this->_id,
            $this->_db
        );

        return true;
    }


    /**
     * Get all files
     * @return array
     */
    public function getAllFiles()
    {
        $sql = "
            SELECT
                sf.input_source_file_id                   AS 'id',
                input_source_filename                     AS 'fileName',
                input_source_file_is_enabled                              AS 'enabled',
                sfc_file.input_source_file_config_value   AS 'altFileName',
                sfc_etl.input_source_file_config_value    AS 'etlSourceMap',
                sfc_etl_file_tag.input_source_file_config_value    AS 'fileTag',
                sfc_etl_item_tag.input_source_file_config_value    AS 'itemTag',
                sfc_new_file_tag.input_source_file_config_value    AS 'newFileTag'
            FROM input_source_file sf
            LEFT JOIN input_source_file_config sfc_file ON 
                sfc_file.input_source_file_id = sf.input_source_file_id
                AND sfc_file.input_source_file_config_name = 'file_name'
            LEFT JOIN input_source_file_config sfc_etl ON 
                sfc_etl.input_source_file_id = sf.input_source_file_id
                AND sfc_etl.input_source_file_config_name = 'etl_source_mapping_id'
            LEFT JOIN input_source_file_config sfc_etl_file_tag ON 
                sfc_etl_file_tag.input_source_file_id = sf.input_source_file_id
                AND sfc_etl_file_tag.input_source_file_config_name = 'xml_file_tag'
            LEFT JOIN input_source_file_config sfc_etl_item_tag ON 
                sfc_etl_item_tag.input_source_file_id = sf.input_source_file_id
                AND sfc_etl_item_tag.input_source_file_config_name = 'xml_item_tag'
            LEFT JOIN input_source_file_config sfc_new_file_tag ON 
                sfc_new_file_tag.input_source_file_id = sf.input_source_file_id
                AND sfc_new_file_tag.input_source_file_config_name = 'new_file_tag'
            WHERE
              input_source_id = :id";

        $files = $this->_db->fetchAll($sql, array(
            ":id" => $this->_id
        ));

        return $this->_convertResultIdsToInt($files);
    }


    /**
     * Get file
     * @param string $fileName
     * @return File
     * @throws \Import\Framework\AI2\Model\Exception
     */
    public function getFile($fileName)
    {
        $sql = "
            SELECT
              input_source_file_id
            FROM input_source_file
            WHERE
              -- Convert * in stored filename to % and use in LIKE
              (
                :filename LIKE REPLACE(input_source_file.input_source_filename, '*', '%')
                --OR input_source_file.input_source_filename = sys.fn_varbintohexsubstring(0, HASHBYTES('SHA1', :filename), 1, 0)
              ) AND
              input_source_id = :sourceId";

        $id = $this->_db->fetchOne(
            $sql,
            array(
                ':filename' => $fileName,
                ':sourceId' => $this->_id
            )
        );


        return new File($id);
    }

    /**
     * Enable this source
     */
    public function enable()
    {
        return $this->_db->query("
            UPDATE input_source
            SET 
              input_source_is_enabled   = 1,
              updated_date = NOW()
            WHERE
              input_source_id = :id",
            array(
                ":id" => $this->_id
            )
        );
    }

    /**
     * Disable this source
     */
    public function disable()
    {
         $this->_db->query(
            /** @lang TSQL */ "
            UPDATE input_source
            SET
                input_source_is_enabled = 0,
                updated_date = NOW()
            WHERE
              input_source_id = :id",
            array(
                    ":id" => $this->_id
            )
        );
    }

    /**
     * Get source data
     * @return array
     */
    protected function _getData()
    {
        $sql = "
            SELECT
                input_source_id               AS 'id',
                js.input_source_type_id       AS 'typeId',
                jst.input_source_type_name    AS 'typeName',
                spider.profile.spider_name    AS 'name',
                input_source_code             AS 'code',
                input_source_description      AS 'description',
                input_source_user_id          AS 'userId',
                input_source_user_name        AS 'userName',
                input_source_is_enabled       AS 'enabled',
                js.created_by                 AS 'createdBy',
                js.created_date               AS 'createdDate',
                js.updated_by                 AS 'updatedBy',
                js.updated_date               AS 'updatedDate',
                js.is_icims_api               AS 'icimsApiEnabled',
                js.icims_site_id              AS 'icimsCustId'
            FROM input_source js
            JOIN input_source_type jst ON
                js.input_source_type_id = jst.input_source_type_id
            JOIN spider_profile ON
                spider_profile.spider_profile_id = js.spider_profile_id
            WHERE
              input_source_id = :id";

        return $this->_db->fetchRow(
            $sql,
            array(
                ":id" => $this->_id
            )
        );
    }

    /**
     * Get type
     * @return Source\Type
     * @throws \Import\Framework\AI2\Model\Exception
     */
    public function getType()
    {
        $typeId = (int) $this->getDataValue('typeId');
        return new Source\Type($typeId);
    }

    /**
     * Find all sources
     * @return array
     */
    static public function findAll()
    {
        return self::find();
    }

    /**
     * Find source by ID
     *
     * @param string $name
     * @param string $type
     * @param bool $active
     * @param int $pageNumber
     * @param int $pageSize
     * @return array
     */
    static public function find($name = null, $type = null, $active = null, $pageNumber = 1, $pageSize = 50)
    {

        $sql = "
            EXEC usp_source_search 
                :name,
                :type,
                :active, 
                :pageNumber,
                :pageSize";

        $activeParam = null;
        if ($active !== null) {
            $activeParam = ($active) ? 'y' : 'n';
        }

        return self::_getDb()->fetchAll(
            $sql,
            array(
                ':name'       => $name,
                ':type'       => $type,
                ':active'     => $activeParam,
                ':pageNumber' => (int) $pageNumber,
                ':pageSize'   => (int) $pageSize
            )
        );
    }


    static public function findAllBasicByType($type)
    {
        $sql = "
            SELECT
                input_source_id               AS 'id',
                js.input_source_type_id       AS 'typeId',
                jst.input_source_type_name    AS 'typeName',
                spider_profile.spider_name    AS 'name',
                input_source_user_name        AS 'userName',
                input_source_is_enabled       AS 'enabled'
            FROM input_source js
            JOIN spider_profile AS js ON
              spider_profile.spider_profile_id = js.spider_profile_id
            JOIN input_source_type jst ON
                js.input_source_type_id = jst.input_source_type_id
            WHERE
                jst.input_source_type_name = :type
            ORDER BY
                pider_profile.spider_name";

        return self::_getDb()->fetchAll($sql, array(
            ':type' => $type
        ));
    }

    /**
     * Validate a "code"
     *
     * Returns TRUE if valid and if invalid, the validation error is returned as
     * a string.
     *
     * @param string $code
     * @return bool|string
     */
    static public function validateCode($code)
    {
        if (!is_string($code)) {
            return "Invalid code.  Not a string.";
        }

        if (strlen($code) !== 10) {
            return "Code must be exactly 10 characters long.";
        }

        if (preg_match('/^[A-Z0-9]{10}$/', $code) !== 1) {
            return "Code must contain only capitol characters and numbers.";
        }

        $count = self::_getDb()->fetchOne("
            SELECT COUNT(*)
            FROM input_source
            WHERE
              input_source_code = :code",
            array(
                ":code" => $code
            )
        );

        if ($count > 0) {
            return "Code already exists.";
        }

        return true;
    }

    /**
     * Validation data to be inserted
     *
     * @param array $data
     * @return bool
     * @throws Source\Exception
     */
    static public function validateInsertData($data)
    {
        if (!is_array($data)) {
            throw new Exception("Invalid data.");
        }

        $requiredKeys = array_values(self::$_columnAliasMap);

        if (!is_array($requiredKeys) || !is_array($data)) {
            throw new Exception("Cannot validate insertion data.  Data is not in array format.");
        }

        $hasRequiredKeys = (0 === count(array_diff($requiredKeys, array_keys($data))));

        if(! $hasRequiredKeys) {
            throw new Exception("Missing one or more required fields.");
        }

        $codeValidation = self::validateCode($data['code']);
        if ($codeValidation !== true) {
            throw new Exception($codeValidation);
        }

        return true;
    }

    /**
     * Get all notify recipients
     * @return array
     */
    public function getAllNotify()
    {
        $sql = "
            SELECT
              input_source_notify_id  AS 'id',
              recipient_name        AS 'name'
            FROM input_source_notify
            WHERE
              input_source_id = :id";

        $notifyData = $this->_db->fetchAll(
            $sql,
            array(
                ":id" => $this->_id
            )
        );

        foreach($notifyData as &$row) {
            $row['id'] = (int) $row['id'];
        }

        return $notifyData;
    }
}