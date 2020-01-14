<?php

namespace Import;

use Import\App\Config;
use Import\Framework\Database\Db as Db;

class SourceFile
{    
    const SOURCE_SPIDER = 'Spider';

    const ETL_SOURCE_MAPPING_CONFIG_KEY = 'etl_source_mapping_id';

    /**
     * Mapping of source types to share config names
     *
     * Directory shares are used to link the input server to say, the FTP server
     * over a mounted directory.  The config file contains different values for
     * where the application can find that share in the local filesystem so that
     * remote files can be read.
     *
     * @var array
     */

    protected $_sourceTypeId;

    protected $_fileName;

    /**
     * @var string Batch name for logging
     */
    protected $_batchName;

    /**
     * @var int
     */
    protected $_currentLogId;

    protected $_previousLogId;

    /**
     * @var array Config settings defined in the database
     */
    protected $_configSettings;

    /**
     * @var array Items logged from previous run of this file
     */
    protected $_previousLoggedItems;


    /**
     * @var Db
     */
    protected $_db;

    /**
     * @var array
     */
    protected $_sourceData;

    /**
     * Source constructor
     *
     * @param string $sourceType
     * @param string $fileName
     * @param Db $Db
     */
    public function __construct($sourceType, $fileName)
    {
        echo '===============================' . $fileName;
        echo "\r\n";
        $this->_db = $this->_getDb();
        $this->_sourceTypeId = $this->getSourceTypeIdByName($sourceType);
        $this->_fileName = $fileName;

        // Set batch name to default
        $this->setBatchName();
    }

    protected function _getDb()
    {
        return Db::getSqlConnection();
    }

    public function getSourceTypeIdByName($name)
    {
        return (int) $this->_db->fetchOne("
            SELECT input_source_type_id
            FROM input_source_type
            WHERE
                input_source_type_name = ?
        ", $name);
    }

    /**
     * Set the batch name
     *
     * @param string|null $name
     */
    public function setBatchName($name = null)
    {
        // Generate a random batch name
        if ($name === null) {
            $this->_batchName = $this->generateBatchName();
        } // Use the provided batch name
        else {
            $this->_batchName = $name;
        }
    }

    /**
     * Generate a randomized batch name
     *
     * @return string
     */
    public function generateBatchName()
    {
        return sha1(
            $this->getSourceId()
            . time()
            . rand(0, 99999)
        );
    }

    /**
     * Get the registered batch name
     * @return string
     */
    public function getBatchName()
    {
        return $this->_batchName;
    }

    /**
     * Get a specific source data value
     *
     * @param string $value
     * @return mixed
     */
    public function getSourceDataValue($value)
    {
        $data = $this->_getSourceData();

        if (array_key_exists($value, $data)) {
            return $data[$value];
        }

        return null;
    }

    /**
     * Get all source data
     * @return array
     */
    public function getSourceData()
    {
        return $this->_getSourceData();
    }

    /**
     * Get source data
     * @return array
     */
    protected function _getSourceData()
    {
        if (is_array($this->_sourceData)) {
            return $this->_sourceData;
        }

        /*
         * This query selects the `input_source` table data as well as the most
         * recent `input_source_log_id`.  If this file has never been logged
         * before, we return NULL for that value.
         */
        $sql = /** @lang TSQL */
            "
            SELECT
                input_source.input_source_id             AS 'id',
                input_source_file.input_source_file_id   AS 'file_id',
                spider_profile.spider_name               AS 'name',
                input_source.input_source_code           AS 'code',
                input_source.input_source_is_enabled     AS 'enabled',
                input_source_type.input_source_type_name AS 'source_type',
                input_source_type.input_source_type_id   AS 'source_id',
                input_source_type.input_source_type_code AS 'source_code',
                CONCAT(input_source_type.input_source_type_code,
                    '-',
                    input_source.input_source_code,
                    '-')                             AS 'input_code_prefix'
            FROM input_source
            INNER JOIN spider_profile ON
                input_source.spider_profile_id = spider_profile.spider_profile_id
            INNER JOIN input_source_file ON
                input_source_file.input_source_id = input_source.input_source_id
            INNER JOIN input_source_type ON
                input_source_type.input_source_type_id = input_source.input_source_type_id
            WHERE
                input_source_type.input_source_type_id = :sourceType AND
                input_source.input_source_is_enabled = 1 AND
                input_source_file.input_source_file_is_enabled = 1 AND
                (
                    input_source_file.input_source_filename = :fileName
                    /*OR input_source_file.input_source_filename = sys.fn_varbintohexsubstring(0, HASHBYTES('SHA1', :fileName), 1, 0)*/
                )";

        $data = $this->_db->fetchRow(
            $sql,
            [
                ':sourceType' => $this->_sourceTypeId,
                ':fileName' => $this->_fileName
            ]
        );

        if ($data === false) {
            $data = [];
        }

        $this->_sourceData = $data;

        return $this->_sourceData;
    }

    public function getSourceId()
    {
        return $this->getSourceDataValue('id');
    }

    public function getSourceName()
    {
        return $this->getSourceDataValue('name');
    }

    public function isConfigured()
    {
        return is_numeric($this->getSourceId());
    }

    public function getFilename()
    {
        if (is_file($this->_fileName)) {
            return basename($this->_fileName);
        } else {
            return $this->_fileName;
        }
    }


    public function getAbsoluteFilename()
    {
        // If the file name is already the absolute path, then just return it
        if (is_file($this->_fileName)) {
            return $this->_fileName;
        }

        $share = $this->getSharePath();
        $filename = $share . DIRECTORY_SEPARATOR . $this->_fileName;

        if (!is_file($filename)) {
            throw new \RuntimeException("File not found: " . $filename);
        }

        return $filename;
    }

    public function getSharePath()
    {
        return Config::get("SPIDER_SHARE_PATH");
    }


    /**
     * Get configured filename
     *
     * Returns the filename that is actually stored in the database for this
     * file.  Sometimes, particularly when a file might be a reference to a
     * lengthy external URL, the file name is actually stored as a SHA1 hash. In
     * those cases, this will return the hash.  In most other cases this will
     * return the EXACT same value as `getFilename()` but with a hit to DB.
     *
     * @return string
     */
    public function getConfiguredFileName()
    {
        $fileId = $this->getFileId();

        $sql = "
            SELECT input_source_filename
            FROM input_source_file
            WHERE
                input_source_file_id = ?";

        return $this->_db->fetchOne($sql, $fileId);
    }

    public function getSourceTypeId()
    {
        return $this->_sourceTypeId;
    }

    /**
     * Get the configured file id
     *
     * @return bool|int
     */
    public function getFileId()
    {
        $data = $this->getSourceDataValue('file_id');

        if (is_numeric($data)) {
            return (int) $data;
        }

        return false;
    }

    /**
     * Get the item code prefix
     *
     * The item code prefix is a unique identifier for the source and source type
     * of a item.  The prefix code is used when logging a new item and generating
     * its own GUID.
     *
     * @return bool|string
     */
    public function getItemCodePrefix()
    {
        return $this->getSourceDataValue('input_code_prefix');
    }

    /**
     * Log file into database
     * @return int
     * @throws \Zend_Db_Adapter_Exception
     */
    protected function logFile()
    {
        if ($this->getFileId() === false) {
            throw new \RuntimeException("Cannot log file.  Source file is not configured properly.");
        }

        $date = date('Y-m-d H:i:s');
        $sql = "INSERT INTO input_source_file_log (input_source_file_id, input_source_filename, batch_name, received, started, completed, total, total_added, total_updated, total_unchanged, total_deleted) 
        VALUES ({$this->getFileId()}, '{$this->getFilename()}', '{$this->getBatchName()}', '{$date}', '{$date}', '0000-00-00 00:00:00', 0, 0, 0, 0, 0)";
        $this->_db->insert($sql);

        // $this->_db->insert(
        //     'input_source_file_log',
        //     array(
        //         'input_source_file_id'      => $this->getFileId(),
        //         'input_source_filename' => $this->getFilename(),
        //         'batch_name'                => $this->getBatchName(),
        //         'received'                  => $date,
        //         'started'                   => $date
        //     )
        // );

        return (int)$this->_db->lastInsertId();
    }

    /**
     * Get the current file log ID
     *
     * @return bool|int
     */
    public function getCurrentFileLogId()
    {
        return $this->getFileLogId();
    }

    /**
     * Set the file log id
     *
     * NOTE: This should only really be used when you are trying to unserialize
     * a request.
     *
     * @param int $id
     */
    public function setFileLogId($id)
    {
        $this->_currentLogId = (int) $id;
    }

    /**
     * Get the batch file log id
     *
     * This method can be used to detect an incomplete `input_source_file_log`
     * @return bool|int
     */
    public function getFileLogId()
    {
        if ($this->_currentLogId) {
            return $this->_currentLogId;
        }

        $id = $this->_db->fetchOne("
            SELECT
                input_source_file_log_id
            FROM input_source_file_log
            WHERE
                input_source_file_id = :fileId AND
                batch_name = :batchName AND
                completed IS NULL
            ORDER BY
                received DESC
            LIMIT 1",
            array(
                ':fileId' => $this->getFileId(),
                ':batchName' => $this->getBatchName()
            )
        );

        // If we didn't get an ID from the lookup, log a new one and return that
        if (!is_numeric($id)) {
            $id = $this->logFile();
        }

        $this->_currentLogId = (int)$id;

        return $this->_currentLogId;
    }

    /**
     * Get the previous log id
     *
     * Get the last file log ID for this file.  This helps us detect changes
     * from one transmission to another.
     *
     * NOTE: If this is the first time a file has been ran, we will return
     *  FALSE.
     *
     * @return bool|int
     */
    public function getPreviousFileLogId()
    {
        if (is_int($this->_previousLogId)) {
            return $this->_previousLogId;
        }

        $sql = "
            SELECT
              input_source_file_log.input_source_file_log_id AS 'id'
            FROM input_source_file
            LEFT JOIN input_source_file_log ON
                input_source_file_log.input_source_file_id = input_source_file.input_source_file_id
            WHERE
                input_source_file.input_source_file_id = :fileId AND
                input_source_file_log.batch_name != :batchName AND
                input_source_file_log.completed IS NOT NULL
            ORDER BY
              received DESC
            LIMIT 1";


        $previousLogId = $this->_db->fetchOne(
            $sql,
            array(
                ':fileId' => $this->getFileId(),
                ':batchName' => $this->getBatchName()
            )
        );

        if (is_numeric($previousLogId)) {
            $this->_previousLogId = (int)$previousLogId;
        } else {
            $this->_previousLogId = false;
        }

        return $this->_previousLogId;
    }

    /**
     * Mark a file as complete
     *
     * Once marked as complete any new items encountered from this file will be
     * considered from a new transmission.  Very important that this isn't
     * called until all processing is actually complete.
     *
     * @return \Zend_Db_Statement_Pdo
     */
    public function markFileComplete()
    {
        return $this->_db->query("
            UPDATE input_source_file_log
            SET
              completed = NOW()
            WHERE
              input_source_file_log_id = :logId",
            array(
                ':logId' => $this->getFileLogId()
            )
        );
    }

    /**
     * Get logged items
     *
     * This method can be used to see what items have thusfar been committed to
     * the database for the current file.
     *
     * @return array
     */
    public function getCurrentLoggedItemsUniqueIds()
    {
        $itemNames = $this->_db->fetchAll("
            SELECT item_unique_id
            FROM input_source_item_log
            WHERE
              input_source_file_log_id = ?",
            $this->getFileLogId()
        );

        $result = array();
        foreach ($itemNames as $row) {
            $result[] = $row['item_unique_id'];
        }

        return $result;
    }


    /**
     * Get previously logged items
     *
     * @return array loggedItems
     */
    public function getPreviousLoggedItems()
    {
        // Check the cache first
        if (is_array($this->_previousLoggedItems)) {
            return $this->_previousLoggedItems;
        }

        $logId = $this->getPreviousFileLogId();

        // No log ID.  Let's assume this is just the first time we've ever seen
        // this file
        if (!$logId) {
            return array();  // Do not cache this
        }

        /*
         * Returns:
         *  id                input_source_item_log.input_source_item_log_id
         *  uniqueId          input_source_item_log.item_unique_id
         *  hash              input_source_item_log.item_hash_value
         *  createdTimestamp  input_source_item_log.created_date (as Unix timestamp)
         *  guid              input.item_guid
         */
        $sql = "CALL gaukmedi_auctions.usp_iteminput_itemguid_getrecent(?)";
        $items = $this->_db->fetchAll($sql, $logId);

        if (!is_array($items)) {
            $items = array();
        }

        // Convert result array to a one dimensional associative array with the
        // uniqueId as the key
        $this->_previousLoggedItems = $this->indexPreviousItemsArray($items);

        return $this->_previousLoggedItems;
    }

    /**
     * Index previous items
     *
     * Minor array work here aimed at speeding up future access to the previous
     * item data.  Item data is typically accessed by uniqueId so here we generate
     * a new array with the same data but using the uniqueId as the key for
     * faster lookups.
     *
     * @param array $previousItems
     * @return array
     */
    protected function indexPreviousItemsArray(array $previousItems)
    {
        $result = [];

        foreach ($previousItems as $item) {
            if (array_key_exists('uniqueId', $item)) {
                $uniqueId = $item['uniqueId'];
                $result[$uniqueId] = $item;
            }
        }

        return $result;
    }

    /**
     * Get the unique IDs of the previously logged items
     * @return array
     */
    public function getPreviousLoggedItemsUniqueIds()
    {
        return array_keys($this->getPreviousLoggedItems());
    }

    public function getItemDataFromPreviousFile($uniqueId, $key = null)
    {
        $items = $this->getPreviousLoggedItems();

        if ($key !== null) {
            if (isset($items[$uniqueId][$key])) {
                return $items[$uniqueId][$key];
            } else {
                return null;
            }
        } elseif (isset($items[$uniqueId])) {
            return $items[$uniqueId];
        }

        return null;
    }

    /**
     * Get items to be deleted
     *
     * This method should be called ONLY once we've finished extracting all items
     * from a file.  As item are extracted from the file, they will call the
     * addItem() method which logs their uniqueId into the _itemsInFile property.
     * That property is then compared in this method to the items logged in the
     * db last time we received this file.  Items in the old file but not in the
     * current version are considered "deletes"
     *
     * @return array
     */
    public function getItemsToDelete()
    {
        // An array of the unique IDs of items in previous file
        $allPreviousUniqueIds = $this->getPreviousLoggedItemsUniqueIds();

        // An array of the unique IDs of items in the current file
        $allCurrentUniqueIds = $this->getCurrentLoggedItemsUniqueIds();

        /*
         * Look for all files that were in previous file but not found in the
         * current one.  If they are missing in the current file, that means
         * they were deleted.
         */
        $uniqueIdsToDelete = array_diff(
            $allPreviousUniqueIds,
            $allCurrentUniqueIds
        );

        /*
         * Using the unique IDs identified for deletion above, compile an array
         * with more complete item info
         */
        $itemsToDelete = [];
        foreach ($uniqueIdsToDelete as $uniqueId) {
            $itemsToDelete[] = array(
                'uniqueId' => $uniqueId,
                'accountId' => $this->getSourceId(),
                'guid' => $this->getItemDataFromPreviousFile($uniqueId, 'guid')
            );
        }

        return $itemsToDelete;
    }


    /**
     * Log CRUD totals
     *
     * This method logs CRUD totals from a transmitAll() call.  The purpose is
     * to be able to provide valuable reporting data on the activity of the
     * AI2 system.
     *
     * 'noAction' => 0,
     * 'update'   => 0,
     * 'delete'   => 0,
     * 'create'   => 0,
     * 'errors'   => 0,
     * 'total'    => 0
     *
     * @see \Import\Translate\Adapter\AbstractAdapter::transmitAll
     * @param array $totals
     * @return int
     */
    public function logCrudTotals($totals)
    {

        /*
         * Below we map the database columns to the expected values in the
         * $totals array and build an update statement that only updates the
         * values we have.  This helps ensure we leave as NULL any values for
         * which we don't actually have a values for.
         */
        $map = array(
            'total' => 'total',
            'total_added' => 'create',
            'total_updated' => 'update',
            'total_unchanged' => 'noAction',
            'total_deleted' => 'delete'
        );
        $updateColumns = array();
        foreach ($map as $dbCol => $arrayIndex) {
            if (array_key_exists($arrayIndex, $totals)) {
                $updateColumns[] = $this->_db->quoteInto($dbCol . ' = ?', $totals[$arrayIndex]);
            }
        }

        $sql = "
            UPDATE input_source_file_log
            SET
                " . implode(",\n", $updateColumns) . "
            WHERE
                input_source_file_log_id = :logId";

        $this->_db->query(
            $sql,
            array(
                ':logId' => $this->getFileLogId()
            )
        );
    }


    /**
     * Get all config setting
     * @return array
     */
    public function getAllConfigSettings()
    {
        if (is_array($this->_configSettings)) {
            return $this->_configSettings;
        }

        $this->_configSettings = array_merge(
            $this->getAllFileConfigSettings(),
            $this->getAllSourceConfigSettings()
        );

        return $this->_configSettings;
    }

    /**
     * Get all file config setting
     * @return array
     */
    public function getAllFileConfigSettings()
    {
        $fileId = $this->getFileId();

        $sql = "
            SELECT
                input_source_file_config_name  AS 'name',
                input_source_file_config_value AS 'value'
            FROM input_source_file_config
            WHERE
                input_source_file_id = ?";

        $settings = $this->_db->fetchAll($sql, $fileId);

        // Pivot data
        $pivotedSettings = array();
        foreach ($settings as $nameValue) {
            $pivotedSettings[$nameValue['name']] = $nameValue['value'];
        }

        return $pivotedSettings;;
    }

    /**
     * Get all source config setting
     * @return array
     */
    public function getAllSourceConfigSettings()
    {
        $sourceId = $this->getSourceId();

        $sql = "
            SELECT
                input_source_config_name  AS 'name',
                input_source_config_value AS 'value'
            FROM input_source_config
            WHERE
                input_source_id = ?";

        $settings = $this->_db->fetchAll($sql, $sourceId);

        // Pivot data
        $pivotedSettings = array();
        foreach ($settings as $nameValue) {
            $pivotedSettings[$nameValue['name']] = $nameValue['value'];
        }

        return $pivotedSettings;;
    }

    /**
     * Get a specific file config setting
     * @param string $name
     * @param string $default
     * @return string
     */
    public function getConfigSetting($name, $default = null)
    {
        $settings = $this->getAllConfigSettings();

        if (array_key_exists($name, $settings)) {
            return $settings[$name];
        }

        return $default;
    }


    public function getEtlSourceMappingId()
    {
        return $this->getConfigSetting(self::ETL_SOURCE_MAPPING_CONFIG_KEY);
    }

    /**
     * Get the notify recipients for this file
     *
     * Note: A notify recipient is really just a Gearman worker name until we
     * adopt some other messaging platform.
     *
     * @return array
     */
    public function getItemNotifyRecipients()
    {
        $sourceId = $this->getSourceId();

        $sql = "
            SELECT recipient_name
            FROM input_source_notify
            WHERE
                input_source_id = ?";

        return $this->_db->fetchAll($sql, $sourceId);
    }

    public function logItem()
    {
        if ($this->getLogId()) {
            throw new Exception("Cannot save item twice.");
        }

        $sql = "INSERT INTO input_source_item_log (input_source_file_log_id, item_unique_id, item_hash_value) 
        VALUES ({$this->getFileLogId()}, '{$this->getUniqueId()}', '{$this->getHash()}')";
        $this->_db->insert($sql);

        // $this->_db->insert(
        //     'input_source_item_log',
        //     array(
        //         'input_source_file_log_id' => $this->getFileLogId(),
        //         'item_unique_id'               => $this->getUniqueId(),
        //         'item_hash_value'         => $this->getHash()
        //     )
        // );

        $logId = $this->_db->lastInsertId();

        $this->_setLogId($logId);
        $this->_logNewItemAuthority($logId);

        return $logId;
    }


    public function getSourceApiType($apiSourceTypeId)
    {
        $sql = "
            SELECT input_source_api_type_name
            FROM input_source_api_type
            WHERE
                input_source_api_type_id = ?";

        return $this->_db->fetchRow($sql, $apiSourceTypeId);
    }
}
