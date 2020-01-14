<?php

namespace Import\Translate;

use Import\Framework\ETL\Profile\Result as EtlResult;
use Import\Framework\Database\Db;
use Import\Cache;
use Import\Input\Adapter\AbstractAdapter;
//use \Import\Monitor;

/**
 * Item
 *
 * This class represents a single item in a file.  One of the key points to bear
 * in mind when you maintain this class is that there is a very tight link
 * between the "Input" (referred to as "file") adapter and this item class.  This
 * item is technically part of the larger file and so there is a tight linkage.
 *
 * You will notice that the constructor here actually accepts the File object
 * instance itself.  There are also several methods that are just wrappers
 * around the File class itself.
 *
 * @todo Reexamine the Source Mapping logic for making source mappings optional
 * @package Import\Translate
 */
class Item extends Cache implements \Serializable
{
    // CRUD operation constants (READ is intentionally omitted)
    const CRUD_NONE     = 0;
    const CRUD_CREATE   = 1;
    const CRUD_UPDATE   = 2;
    const CRUD_DELETE   = 3;
    const CRUD_DIFFERED = 4;  // Evaluation of CRUD status differed

    /**
     * @var AbstractAdapter
     */
    protected $_file;

    /**
     * @var \Zend_Db_Adapter_Pdo_Abstract
     */
    protected $_db;

    /**
     * @var array Item data provided by a SAX parser presumably
     */
    protected $_data;

    /**
     * @var array ETL Results
     */
    protected $_etlResults = array();

    /**
     * @var string Generated item hash
     */
    protected $_hash;

    /**
     * @var int The logged ID for this instance of the item (NOT the item ID)
     */
    protected $_logId;

    /**
     * @var array All possible item statuses (static to save repeat lookups)
     */
    static protected $_allItemStatues = array();

    /**
     * @var int Item ID the universal ID for a item across requests
     */
    protected $_itemId;

    /**
     * @var string Authoritative item GUID
     */
    protected $_itemGuid;

    /**
     * @var array In memory cache of previous data
     */
    protected $_previousData;

    /**
     * @var array All item data (used with Spiders primarily)
     */
    protected $_allData;

    /**
     * @var bool Has this item been reset?
     */
    protected $_isReset = false;

    /**
     * Class constructor
     * @param AbstractAdapter $file
     * @param $dataArray
     * @param array $options DI options
     * @throws Exception
     */
    public function __construct(AbstractAdapter $file, array $dataArray, $options=array())
    {
        // Set the file object
        $this->_file = $file;

        // Set the item data array
        if (!is_array($dataArray)) {
            throw new Exception("Item data must be an array.");
        }

        $this->_data = $this->extractData($dataArray);

        /*
         * In addition to setting the "_data" property we can optionally set an
         * "allData" value.  This is helpful in the case of spiders where we
         * want to distinguish between the item Data and the Spider data.  The
         * Spider Data also contains helpful meta-data that can be used as part
         * of Source Mapping.
         */
        if (isset($options['allData'])) {
            $this->_allData = $options['allData'];
        }
        else {
            $this->_allData = $this->_data;
        }

            // Set the item hash
        $this->_hash = $this->createHash();

        // Set the database handler
        if (!isset($options['db'])) {
            $options['db'] = Db::getSqlConnection();
            $this->_db = $options['db'];
        }

        // Report back to the file object that this item was found in the file
        $this->_file->addItem($this);
    }

    protected function extractData(array $dataArray)
    {
        if (isset($dataArray['item']) && count($dataArray) === 1) {
            return $dataArray['item'];
        }

        if ($this->_file instanceof \Import\Input\Adapter\RssFeed) {
            // Set the item tag to lowercase to properly mapped it to correct node from Item dataArray.
            $itemTag = strtolower($this->_file->getSaxItemTag());

            if (isset($itemTag) && isset($dataArray[$itemTag]) && count($dataArray) === 1) {
                return $dataArray[$itemTag];
            }
        }

        return $dataArray;
    }

    /**
     * Get format version
     *
     * The format version is used internally to signify what format the data
     * stored in Mongo is in.  Over time we will undoubtedly want to change the
     * format, this setting allows us to flag new formats with a different
     * version number.
     *
     * @return string
     */
    public function getFormatVersion()
    {
        return '2.1';
    }

    /**
     * Create a data hash
     * @return string
     */
    public function createHash()
    {
        $sourceMapping = $this->getSourceMappedDataArray();

        $dataArray = array_merge(
            // New version should trigger update
            array(
                'format' => $this->getFormatVersion(),
            ),
            // Changes to mapping should trigger update
            $this->_data,
            $sourceMapping
        );

        $sortedData = $this->sortAllArrayKeys($dataArray);

        return sha1(json_encode($sortedData));
    }

    /**
     * Sort all array keys (recursive)
     * @param array $data
     * @return array
     */
    public function sortAllArrayKeys($data)
    {
        ksort($data);
        foreach($data as $key => $value) {
            if(is_array($value)) {
                $data[$key] = $this->sortAllArrayKeys($value);
            }
        }

        return $data;
    }

    /**
     * Get the file name this item belonged to
     * @return string
     */
    public function getFileName()
    {
        return $this->_file->getFileName();
    }

    /**
     * Get the FTP user ID associated with this item
     * @return mixed
     */
    public function getOwnerId()
    {
        return $this->_file->getOwnerId();
    }

    /**
     * Get the type of owner for this item
     *
     * Ex: "spider", "bulkpost"
     *
     * @return string
     */
    public function getOwnerType()
    {
        return $this->_file->getOwnerType();
    }

    /**
     * Get the item data
     * @throws Exception
     * @return array
     */
    public function getData()
    {
        if ($this->_isReset) {
            throw new Exception("Cannot access item data.  Item is reset.");
        }

        return $this->_data;
    }

    /**
     * Get the item meta-data
     * @throws Exception
     * @return array
     */
    public function getAllData()
    {
        if ($this->_isReset) {
            throw new Exception("Cannot access all item data.  Item is reset.");
        }

        // Return the standard item data property if _allData not defined.
        if (!is_array($this->_allData)) {
            return null;
        }

        return $this->_allData;
    }

    /**
     * Get source-mapped data
     *
     * This method gets the final result of source mapping.
     *
     * @return EtlResult|null
     */
    public function getSourceMappedData()
    {
        // A little in-memory caching to prevent repeat work
        if (! isset($this->_etlResults['sourceMapping'])) {
            $this->_etlResults['sourceMapping'] = $this->_file->getEtlMapping()->run($this);
        }

        return $this->_etlResults['sourceMapping'];
    }


    public function getSourceMappedDataArray()
    {
        $Data = $this->getSourceMappedData();

        if ($Data instanceof EtlResult) {
            return $Data->toArray();
        }

        return [];
    }

    /**
     * Get source mapping field
     *
     * Use this method to access an output field's value without having to deal
     * with the potentially complex result of the source mapping itself.
     *
     * @param string $name
     * @return string|int
     */
    public function getSourceMappingField($name)
    {
        $Data = $this->getSourceMappedData();

        if ($Data instanceof EtlResult) {
            return $Data->getMappedValue($name);
        }

        return null;
    }

    /**
     * Get source mapping validation errors
     * @return array
     */
    public function getSourceMappingValidationErrors()
    {
        $Data = $this->getSourceMappedData();

        if ( ! $Data instanceof EtlResult) {
            return [];
        }

        $errors = $Data->validationErrors;

        $cleanErrors = array();

        foreach($errors as $error) {
            if (is_array($error) && count($error) === 0) {
                continue;
            }

            $cleanErrors[] = $error;
        }

        return $cleanErrors;
    }

    /**
     * Get the generated item hash for the *current* version of this item
     * @return string
     */
    public function getHash()
    {
        return $this->_hash;
    }

    /**
     * Get the unique item ID
     *
     * This should be the <name> field in the XML typically.
     *
     * @throws Exception
     * @return string|bool
     */
    public function getUniqueId()
    {
        if ($this->_simpleCacheCheck(__METHOD__)) {
            return $this->_simpleCache(__METHOD__);
        }

        // Look for the unique ID as defined by the source mapping
        $id = $this->getSourceMappingField('uniqueId');


        // If source mapping does not return a value, use the old lookups which
        // check a couple places in the data for a unique ID
        if ( ! $id) {
            $id = $this->_getUniqueIdLegacy();
        }


        // Cache and return the unique ID if found.
        if ($id) {
            return $this->_simpleCache(__METHOD__, $id);
        }

        // Unique ID are a must have so we throw exception here if not found.
        throw new Exception("Cannot locate required unique ID for item.");
    }

    /**
     * Get the item's unique ID via legacy (JB BulkPost) schema locations.
     * @deprecated
     * @return string|null
     */
    protected function _getUniqueIdLegacy()
    {
        /*
         * Backup (legacy) Look-ups
         */
        if (isset($this->_data['name'])) {
            return $this->_data['name'];
        }

        if (isset($this->_data['item_info']['name'])) {
            return $this->_data['item_info']['name'];
        }

        return null;
    }

    /**
     * Get this item's log ID
     *
     * If the item ID was explicitly set with setLogId(), that value will always be returned.  Otherwise this
     * method just aliases getPreviousItemLogId().
     *
     * @return int
     */
    public function getLogId()
    {
        if (is_int($this->_logId) && $this->_logId > 0) {
            return $this->_logId;
        }

        return false;
    }



    /**
     * Set the log ID
     *
     * This is typically called after we insert a NEW item into the database.  In this case, the item will not
     * have a previous item ID until set here.
     *
     * @param int $id
     * @throws Exception
     */
    protected function _setLogId($id)
    {
        if (!is_numeric($id)) {
            throw new Exception("Cannot set log ID to non-numeric value.");
        }
        $this->_logId = (int) $id;
    }

    /**
     * Get the CRUD state
     *
     * Since this item could be a new one, and update to an existing one, or (potentially?) a deletion of an existing item
     * we need some way to determine what we're being asked to do.
     *
     * @return int
     */
    public function getCrudState()
    {
        if ($this->_simpleCacheCheck(__METHOD__)) {
            return $this->_simpleCache(__METHOD__);
        }

        $previousHash = $this->getPreviousHash();
        //print_r(' ' . $previousHash);

        // If there isn't a previous hash for this file, treat it as a CREATE
        if ($previousHash === false) {
            return $this->_simpleCache(__METHOD__, self::CRUD_CREATE);
        }

        // If the previous hash matches the current hash, we have no work to do
        if ($previousHash == $this->getHash()) {
            return $this->_simpleCache(__METHOD__, self::CRUD_NONE);
        }

        // So, the previous has DOES exist and it DOESN'T match the current hash. It's an UPDATE.
        return $this->_simpleCache(__METHOD__, self::CRUD_UPDATE);
    }

    /**
     * Get the "incremental" CRUD state
     *
     * Some client integrations (like CareerBuilder) will explicitly tell us
     * what CRUD operation we should perform.  These integrations are called
     * "incremental" because each time we get a batch of items from them that
     * batch contains only the changes to a collection of items rather than ALL
     * the items for that client as is the case with "full" integrations like
     * BulkPost.
     *
     * @return int|null
     */
    public function getIncrementalCrudState()
    {
        if ($this->_simpleCacheCheck(__METHOD__)) {
            return $this->_simpleCache(__METHOD__);
        }

        $mappedData = $this->getSourceMappedDataArray();

        // Get the source-mapped string value
        $op = (isset($mappedData['incrementalOp']))
            ? $mappedData['incrementalOp']
            : '';

        // Convert the string to our class constant values
        switch ($op) {
            case "CREATE":
                $op = self::CRUD_CREATE;
                break;

            case "UPDATE":
                $op = self::CRUD_UPDATE;
                break;

            case "DELETE":
                $op = self::CRUD_DELETE;
                break;

            default:
                $op = null;
        }

        return $this->_simpleCache(__METHOD__, $op);
    }

    /**
     * Get the CRUD state as a string
     * @return string
     */
    public function getCrudStateString()
    {
        $crudState = $this->getCrudState();
        return $this->convertCrudState($crudState);
    }


    /**
     * Converts a CRUD state constant to a string
     * @param int $state
     * @return string
     */
    public function convertCrudState($state)
    {
        $states = array(
            self::CRUD_NONE   => 'none',
            self::CRUD_CREATE => 'create',
            self::CRUD_UPDATE => 'update',
            self::CRUD_DELETE => 'delete'
        );

        if (array_key_exists($state, $states)) {
            return $states[$state];
        }

        return 'unknown';
    }

    /**
     * Get the previous hash for this item as logged in the database
     * @return string|bool
     */
    public function getPreviousHash()
    {
        if ($this->_simpleCacheCheck(__METHOD__)) {
            //print_r('test1');
            return $this->_simpleCache(__METHOD__);
        }

        //print_r('test2');
        return $this->_simpleCache(__METHOD__, $this->getPreviousLogData('hash'));
    }


    /**
     * Get previous log data
     *
     * Returns a single value or all values for the previous logged occurrence
     * of this item.  Returns false if you ask for a value that doesn't exist or
     * there is no previously logged data.
     *
     * @see \Import\Input\AdapterInterface::getPreviousLoggedItems()
     * @param null $value  Optional value to return.
     * @return bool
     */
    public function getPreviousLogData($value=null)
    {
        $uniqueId = $this->getUniqueId();
        if (!is_array($this->_previousData)) {
            $this->_previousData = $this->_file->getItemDataFromPreviousFile(
                $uniqueId
            );
        }

        // No previous value
        if (!is_array($this->_previousData)) {
            return false;
        }


        // Is a specific value requested?
        if ($value) {
            // Does it exist?
            if (array_key_exists($value, $this->_previousData)) {
                return $this->_previousData[$value];
            }
            else {
                return false;
            }
        }
        // No specific value requested return all data points for this item
        else {
            return $this->_previousData;
        }
    }

    /**
     * Log a item
     *
     * Logs item into the `input_source_item_log` table
     *
     * @throws Exception
     * @return int  New log ID
     */
    public function logItem()
    {
        if ($this->getLogId()) {
            throw new Exception("Cannot save item twice.");
        }

        $sql = "INSERT INTO input_source_item_log (input_source_file_log_id, item_unique_id, item_hash_value) 
        VALUES ({$this->_file->getLogId()}, '{$this->getUniqueId()}', '{$this->getHash()}')";
        $this->_db->insert($sql);

        // $this->_db->insert(
        //     'input_source_item_log',
        //     array(
        //         'input_source_file_log_id' => $this->_file->getLogId(),
        //         'item_unique_id'           => $this->getUniqueId(),
        //         'item_hash_value'          => $this->getHash()
        //     )
        // );

        $logId = $this->_db->lastInsertId();
        
        $this->_setLogId($logId);
        $this->_logNewItemAuthority($logId);

        return $logId;
    }

    /**
     * Save item
     *
     * @deprecated  Use logItem() instead
     * @return string
     */
    public function save()
    {
        return $this->logItem();
    }


    /**
     * Generate item prefix
     * @param int $logId Optional log id
     * @return string
     * @throws Exception
     */
    public function generateGuid($logId=null)
    {
        if ($logId === null) {
            $logId = $this->getLogId();
        }

        if (!is_numeric($logId)) {
            throw new Exception("Cannot generate GUID. Invalid item log ID provided.");
        }

        // Create a GUID
        // TODO: This should be done by SQL Server in the future
        $guid = $this->_file->getItemCodePrefix();

        if (!$guid) {
            $guid = "??-";
        }

        /*
         * Append the item ID to the GUID and pad it to the left with 0 until we
         * use 38 characters for the ID portion of the GUID.
         */
        
        $guid .= str_pad($logId, 19, '0', STR_PAD_LEFT);

        return $guid;
    }

    /**
     * Log a new item authority
     *
     * This inserts the primary "Item ID" which will be used to track a item over
     * multiple receipts.
     *
     * @param int $logId
     * @return int|bool
     */
    protected function _logNewItemAuthority($logId)
    {

        $guid = $this->generateGuid($logId);
    

        /*
         * If this is a new item, create a new Item ID and save the GUID
         */
        if ($this->getCrudState() === Item::CRUD_CREATE) {
            $sql = "INSERT INTO input_item (input_source_item_log_id, item_guid) 
            VALUES ({$logId}, '{$guid}')";
            $this->_db->insert($sql);

            // $this->_db->insert(
            //     'input_item',
            //     array(
            //         'input_source_item_log_id' => $logId,
            //         'item_guid'   => $guid
            //     )
            // );

            // Get the newly created item ID
            $this->_itemId   = $this->_db->lastInsertId();
            $this->_itemGuid = $guid;

            /*
             * This return value is technically ignored but we provide it here
             * to remain consistent with the return value from getItemAuthority()
             */
            return array(
                'id'   => $this->_itemId,
                'GUID' => $this->_itemGuid
            );
        }
        /*
         * If this is an existing item, update the item log ID to be the most
         * recent value.
         */
        else if ($this->getCrudState() === Item::CRUD_UPDATE) {
            $this->_db->query("
                UPDATE input_item
                SET
                  input_source_item_log_id = :logId
                WHERE
                item_guid = :guid",
                array(
                    ':logId' => $logId,
                    ':guid'  => $this->getExistingItemGuid()
                )
            );

            /*
             * This return value is technically ignored but we provide it here
             * to remain consistent with the return value from getItemAuthority()
             */
            return array(
                'id'   => $this->getExistingItemId(),
                'GUID' => $this->getExistingItemGuid()
            );
        }


        return false;
    }


    /**
     * Get the item authority id and GUID
     *
     * Returns an array containing "id" and "GUID" elements.  These 2 data
     * points should be used to uniquely identify a item that we receive over
     * time.  Once a item is missing from a transmission, we consider the item ID
     * and GUID to be invalidated/deactivated and a new pair will be regenerated
     * if the same item is ever received again.  For this reason, we call these a
     * "item authority" for lack of a better term.
     *
     * @return array
     * @throws Exception
     */
    public function getItemAuthority()
    {
        if (isset($this->_itemId) && isset($this->_itemGuid)) {
            //print_r(array(
            //    'id'   => $this->_itemId,
            //    'GUID' => $this->_itemGuid
            //));
            return array(
                'id'   => $this->_itemId,
                'GUID' => $this->_itemGuid
            );
        }

        /*
         * Since the item insertion process should have filled the _itemId and
         * _itemGuid properties checked above, we can assume here if this item is
         * considered a "new" item, it has not yet been inserted into the
         * authority table and will ultimately fail if we try to do a lookup.
         */
        if ($this->getCrudState() === self::CRUD_CREATE) {
            throw new Exception('You cannot get the item authority before it is created.');
        }
        
        //print_r(' getUniqueId ' . $this->getUniqueId());
        $previousGuid = $this->_file->getGuidFromPreviousFile(
            $this->getUniqueId()
        );

        if ($previousGuid) {
            $this->_itemGuid = $previousGuid;
            //print_r(" prev " . $previousGuid);
        }
        else {
            // Monitor::getInstance()->logError(
            //     'Error looking up authority data.  Could not find GUID from last file.',
            //     __METHOD__,
            //     $this->getData()
            // );
            throw new Exception("Error looking up previous item authority");
        }


        $sql = "
            SELECT
                item.input_item_id     AS 'id'
            FROM input_item     AS item
            WHERE
               item.item_guid = :guid
            LIMIT 1";

        $data = $this->_db->fetchRow(
            $sql,
            array(
                ':guid' => $this->_itemGuid
            )
        );

        
        // Did something go wrong looking up the data?
        if (!$data || !isset($data['id'])) {
            // Monitor::getInstance()->logError(
            //     'Error looing up authority data.',
            //     __METHOD__,
            //     $this->getData()
            // );
            throw new Exception("Error looking up previous item authority");
        }

        // Save to properties checked at the top of method to limit DB hits
        $this->_itemId   = $data['id'];
 
        return array(
            'id'   => $this->_itemId,
            'GUID' => $this->_itemGuid
        );
    }

    /**
     * Get an existing item ID
     *
     * This method gets the existing Item ID (as defined by `item.item_id`).
     * Note that if you call this method before a item has been logged an
     * exception will be thrown.
     *
     * This method wraps getItemAuthority() and returns only the item ID portion
     * of that method's result.
     *
     * @return int|bool
     */
    public function getExistingItemId()
    {
        $results = $this->getItemAuthority();

        if (isset($results['id'])) {
            return (int) $results['id'];
        }

        return false;
    }

    /**
     * Get existing item GUID
     *
     * This method gets the existing Item GUID (as defined by `item.guid`).  Note
     * that if you call this method before a item has been logged, an exception
     * will be thrown.
     *
     * This method wraps getItemAuthority() and returns only the item GUID portion
     * of that method's result.
     *
     * @return string|bool
     */
    public function getExistingItemGuid()
    {
        $results = $this->getItemAuthority();

        if (isset($results['GUID'])) {
            return $results['GUID'];
        }

        return false;
    }

    /**
     * Get the input adapter
     *
     * This method is provided primarily for cases where we are processing items
     * one at a time and need access to the input adapter of a given item we've
     * been asked to process.
     *
     * @return AbstractAdapter
     */
    public function getInputAdapter()
    {
        return $this->_file;
    }


    /**
     * Get this item's GUID authority value
     *
     * @deprecated  Use getExistingItemGuid() instead
     * @return string|bool
     */
    public function getGuid()
    {
        return $this->getExistingItemGuid();
    }


    /**
     * Get the new item ID
     *
     * This returns the unique item ID of this item *only* if it is new.  Updates
     * and deletes will not return a usable value.
     *
     * @todo See what it would take to always return the right value
     * @return int
     */
    public function getNewItemId()
    {
        return $this->_logId;
    }

    /**
     * Reset this item
     *
     * In order to conserve memory, this method can be called to "reset" the
     * item.  When the item is reset all the item data is cleared out and is no
     * longer accessible.  Be aware that this may break a lot of things.  You
     * should only call this once a item has been successfully transmitted.
     */
    public function reset()
    {
        $this->_isReset = true;
        unset($this->_data);
        unset($this->_allData);
        $this->_simpleCacheReset();
    }

    /**
     * Serialize the item
     * @link http://php.net/manual/en/serializable.serialize.php
     * @return string the string representation of the object or null
     */
    public function serialize()
    {
        $config = array(
            'data' => $this->_data,
            'logId' => $this->_logId,
            'itemId' => $this->_itemId,
            'itemGuid' => $this->_itemGuid,
            'inputAdapter' => serialize($this->_file)
        );

        /*
         * If _allData set, check to see if it matches _data.  If NOT, then
         * add that to the config.  If it DOES we don't need to send it over the
         * wire.
         */
        if (is_array($this->_allData) &&
            md5(json_encode($this->_allData)) != md5(json_encode($this->_data))) {
            $config['allData'] = $this->_allData;
        }

        $json = json_encode($config);

        return base64_encode(gzcompress($json));
    }

    /**
     * Construct the Item
     * @link http://php.net/manual/en/serializable.unserialize.php
     * @param string $serialized
     * @throws Exception
     * @throws \Import\Exception
     * @return void
     */
    public function unserialize($serialized)
    {
        $config = json_decode(gzuncompress(base64_decode($serialized)), true);

        // Set the file object
        $this->_file = unserialize($config['inputAdapter']);

        $dataArray = $config['data'];

        $this->_itemId      = $config['itemId'];
        $this->_logId      = $config['logId'];
        $this->_itemGuid    = $config['itemGuid'];

        // ------------
        // Below this is basically a copy/paste from the constructor
        // TODO: Should we just call the constructor outright?
        // ------------

        // Set the item data array
        if (!is_array($dataArray)) {
            throw new Exception("Item data must be an array.");
        }

        if (isset($dataArray['item']) && count($dataArray) === 1) {
            $this->_data = $dataArray['item'];
        }
        else {
            $this->_data = $dataArray;
        }

        /*
         * In addition to setting the "_data" property we can optionally set an
         * "allData" value.  This is helpful in the case of spiders where we
         * want to distinguish between the Item Data and the Spider data.  The
         * Spider Data also contains helpful meta-data that can be used as part
         * of Source Mapping.
         */
        if (isset($config['allData'])) {
            $this->_allData = $config['allData'];
        }
        else {
            $this->_allData = $this->_data;
        }

        // Set the item hash
        $this->_hash = $this->createHash();

        // Set the database handler
        if (!isset($options['db'])) {
            $options['db'] = Db::getSqlConnection();
        }
        $this->_setDb($options['db']);


        // Report back to the file object that this Item was found in the file
        $this->_file->addItem($this);
    }
}
