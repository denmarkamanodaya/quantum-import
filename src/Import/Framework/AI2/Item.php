<?php

namespace Import\Framework\AI2;

use Import\Framework\Database\Db as Ai2Db;

/**
 * AI2 Model
 *
 * This class can be used to perform CRUD operations on a single item in the AI2
 * repository.  This class assumes that you already have the GUID of the item
 * you want to operate on before hand.
 *
 * @package Import\Framework\AI2
 */
class Item
{
    /**
     * @var Item Singleton instance
     */
    static protected $_instance;

    const VERIFY_NOT_EXIST = 0;
    const VERIFY_MATCH     = 1;
    const VERIFY_DIFFERENT = 2;
    const VERIFY_DUPLICATE = 3;
    const VERIFY_UNKNOWN   = 4;

    const RESULT_INSERT    = 5;
    const RESULT_UPDATE    = 6;
    const RESULT_DELETE    = 7;
    const RESULT_ERROR     = 8;
    const RESULT_SUCCESS   = 9;


    /**
     * Item storage connection
     * @var \MongoCollection
     */
    protected $_dbStorage;

    /**
     * Item purge connection
     * @var \MongoCollection
     */
    protected $_dbPurge;

    /**
     * @var array
     */
    protected $_cache = array();

    /**
     * @var int Count of known database failures
     */
    protected $_totalDbFailures = 0;

    /**
     * @var int Max retries per query (default is 5)
     */
    protected $_maxRetries = 5;

    /**
     * Constructor
     * @throws \MongoConnectionException
     */
    protected function __construct()
    {
        $this->_dbStorage = Ai2Db::getMongoConnection('itemStorage');
        $this->_dbPurge  = Ai2Db::getMongoConnection('itemPurge');

        // Get max retries from config. Default to 1 if something goes wrong
        $this->_maxRetries = 3;
        if ($this->_maxRetries === 0) {
            $this->_maxRetries = 1;
        }

        // Ensure indexes here in the constructor so that we only run it once
        $this->_ensureIndexes();
    }

    /**
     * Singleton constructor
     * @return Item
     * @throws \MongoConnectionException
     */
    static public function getInstance()
    {
        if (!self::$_instance instanceof self) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Ensure MongoDB indexes
     *
     * Create/ensure Mongo indexes on key document items to help speed later
     * look ups.  Remember, MongoDB uses B-Tree indexes so the order of the
     * items in an index matter.
     */
    protected function _ensureIndexes()
    {
        /*
         * This index ensures we can quickly find a item
         */
        $this->_dbStorage->createIndex(
            array(
                'internal.guid' => 1,
            )
        );

        /*
         * This index ensures we can quickly find all items in a file. Note that
         * keys in index are ordered from most general to most specific to make
         * use of b-tree indexing.
         */
        $this->_dbStorage->createIndex(
            array(
                'source.fileId' => 1,
                'item.uniqueId' => 1,
                'internal.lastChange' => -1
            )
        );

        $this->_dbStorage->createIndex(
            array(
                'source.id' => 1,
                'source.type' => 1,
                'item.uniqueId' => 1,
                'internal.importId' => 1,
                'internal.guid' => 1
            )
        );


        $this->_dbStorage->createIndex(
            array(
                'source.id' => 1,
                'source.file' => 1,
                'source.tye' => 1
            )
        );

        /**
         * This index mirrors the preceding index to allow us to look up items
         * in the archive
         */
        $this->_dbPurge->createIndex(
            array(
                'source.fileId' => 1,
                'item.uniqueId' => 1,
                'internal.lastChange' => -1
            )
        );

        /**
         * This index is used only so that we can have archive data
         * automatically expire.
         */
        $this->_dbPurge->createIndex(
            array(
                'internal.lastChange' => -1
            ),
            array(
                // Expire archive entries after 5 weeks (3,024,000 seconds)
                "expireAfterSeconds" => 3024000
            )
        );
    }


    /**
     * Get GUID query
     *
     * This accepts a GUID string and then returns an array than can be used as
     * a MongoDB criteria.
     *
     * @todo Add support for versions when needed
     * @param string$guid
     * @return array
     */
    protected function _getGuidQuery($guid)
    {        
        // Allow for bulk queries
        if (is_array($guid)) {
            $guid = array('$in' => array_values($guid));
        }

        return array(
            "internal.guid" => $guid
        );
    }


    /**
     * GUID exists in database?
     *
     * @param string $guid
     * @return bool
     */
    public function guidExists($guid)
    {
        return $this->_dbStorage->count(
            $this->_getGuidQuery($guid)
        );
    }

    /**
     * Insert/Update (upsert) a item
     *
     * This method is used by both the insert and update methods in an effort to
     * keep out duplicates.
     *
     * Note: This method is recursive.  In the event of a DB failure this method
     * will increment the $attempts counter and call itself again. If $attempts
     * is greater than _maxRetries we stop trying and call it a failure.
     *
     * @param string $guid
     * @param array $itemData
     * @param int $attempts
     * @throws Item\Exception
     * @throws \Exception
     * @return bool|int
     */
    protected function _upsert($guid, $itemData, $attempts=0)
    {
        if (!self::isValidGuid($guid)) {
            throw new Item\Exception("Cannot insert/update GUID {$guid}.  Invalid item GUID.");
        }

        // In theory we should never hit this but I hate infinite loops :)
        if ($attempts >= $this->_maxRetries) {
            return false;
        }

        // We cannot update with the MongoId in place
        unset($itemData['_id']);

        try {
            $result = $this->_dbStorage->updateOne(
                $this->_getGuidQuery($guid), // Item GUID criteria
                array(
                    '$set' => $itemData
                ),
                array(
                    'w'       => 1,    // Write concern enabled
                    'upsert'  => true, // If no existing doc found an insert is made
                    'multiple'=> true  // Update all items matching GUID
                )
            );
        }
        catch (\Exception $e) {

            // Increment the number of attempts made
            $attempts += 1;

            /*
             * We tried and failed up to the max.  Now we alert and throw
             * exceptions for real.
             */
            if ($attempts >= $this->_maxRetries) {
                throw $e;
            }

            // Sleep for 0.5 seconds to let external systems breathe a little
            usleep(500000);

            /*
             * We tried and failed but NOT up the max.  Let's try again but
             * with $attempts incremented.
             */
            return $this->_upsert($guid, $itemData, $attempts);
        } catch (\MongoDB\Driver\Exception\BulkWriteException $e) {
            return self::RESULT_ERROR;
        }
        
        if ($result->getUpsertedCount() === 1) {
            return self::RESULT_INSERT;
        }

        if ($result->getModifiedCount() === 1) {
            return self::RESULT_UPDATE;
        }

        return self::RESULT_ERROR;
    }

    /**
     * Find a item by GUID
     *
     * This method returns a Mongo cursor for all items that match the provided
     * GUID.
     *
     * @param $guid
     * @return \MongoCursor
     * @throws Item\Exception
     */
    protected function _find($guid)
    {
        if (!self::isValidGuid($guid)) {
            throw new Item\Exception("Cannot find item for GUID '{$guid}'.  Invalid GUID.");
        }

        return $this->_dbStorage->find(
            $this->_getGuidQuery($guid)
        );
    }


    /**
     * Get a single item by it's GUID
     *
     * @param string $guid
     * @return array|bool $data
     * @throws Item\Exception
     * @throws \MongoConnectionException
     * @throws \MongoCursorException
     * @throws \MongoCursorTimeoutException
     */
    public function find($guid)
    {
        $options = array(
            "sort"  => array("_id" => -1),
            "limit" => 1
        );
        $query = $this->_dbStorage->find(
            $this->_getGuidQuery($guid),
            $options
        );

        $data = $query->toArray()[0];

        if (is_array($data)) {
            return $data;
        }

        return false;
    }


    public function findGuidInFile($uniqueId, $fileId)
    {
        $record = $this->_dbStorage->findOne(
            array(
                "source.fileId" => (int) $fileId,
                "item.uniqueId" => $uniqueId
            ),
            array(
                "internal.guid" => true
            ));

        if (is_array($record) && isset($record['internal']['guid'])) {
            return $record['internal']['guid'];
        }

        return null;
    }


    /**
     * Count the items matching a GUID
     * @param string $guid
     * @return int
     * @throws Item\Exception
     */
    public function count($guid)
    {
        return count($this->_find($guid)->toArray());
    }

    /**
     * Get a file's items
     *
     * @param int $sourceId
     * @param int $fileId
     * @param string $type
     * @return \MongoCursor
     */
    public function getFileItems($sourceId, $fileId, $type)
    {
        return $this->_dbStorage->find(array(
            'source.id'     => (int) $sourceId,
            'source.fileId' => (int) $fileId,
            'source.type'   => $type
        ));
    }

    /**
     * Delete all items in a file
     *
     * @param integer $sourceId
     * @param string $fileName
     * @param string $type
     * @return int
     * @throws Exception
     */
    public function deleteAllFileItems($sourceId, $fileName, $type)
    {
        try {
            $result = $this->_dbStorage->deleteMany(
                array(
                    'source.id'   => (int) $sourceId,
                    'source.file' => $fileName,
                    'source.type' => $type
                ),
                array(
                    'w'       => 1
                )
            );

            $numAffected = $result->getDeletedCount();

            return $numAffected;
        }
        catch(\MongoDB\Driver\Exception\BulkWriteException $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Validate Item GUID
     *
     * @param string $guid
     * @return bool
     */
    static public function isValidGuid($guid)
    {
        // Recursive array validation of GUIDs
        if (is_array($guid)) {
            foreach ($guid as $oneGuid) {
                $result = self::isValidGuid($oneGuid);
                if (!$result) {
                    return false;
                }
            }
            return true;
        }

        if (!is_string($guid)) {
            return false;
        }

        // Must be all uppercase letters
        // ...hence converting to upper should have no effect
        if (strtoupper($guid) !== $guid) {
            return false;
        }

        /*
         * Regex pattern match
         * Example GUID: "FTP1-PARALLON-0000000000000026777"
         * @see http://www.phpliveregex.com/p/2AG
         */
        return !!preg_match(
            '/[a-z0-9]{4}\-[a-z0-9]{3,10}-[\d]{19}/i',
            $guid
        );
    }

    /**
     * Sync a item
     *
     * This method can be used to "sync" a item.  This means that we will examine
     * the item data and GUID provided and compare it with what is in Mongo. If
     * there are differences we detect them and make the appropriate update to
     * Mongo.
     *
     * For example, if you provide a item that exists in Mongo but has different
     * values, we will update the item in mongo to match the new item data.
     *
     * DUPLICATES DELETION
     * It's also important to note that we enforce GUID as unique here by first
     * detecting if there are more than one items in the database matching that
     * GUID.  If there are, we delete those items and then insert this new item.
     *
     * @param array $itemData
     * @param bool $isRetry
     * @return bool
     * @throws Exception
     * @throws Item\Exception
     * @throws \MongoConnectionException
     * @throws \MongoCursorTimeoutException
     * @throws \MongoException
     */
    public function sync($itemData, $isRetry=false)
    {
        $guid = $this->extractGuid($itemData);

        $verifyResult = $this->verifyItem($guid, $itemData);

        switch ($verifyResult) {
            case self::VERIFY_DIFFERENT:
                return $this->update($guid, $itemData);

            case self::VERIFY_NOT_EXIST:
                return $this->insert($guid, $itemData);

            case self::VERIFY_UNKNOWN:
                throw new Exception("Error syncing item.  Invalid item data.");

            case self::VERIFY_DUPLICATE:
                // Don't do this more than once
                if (!$isRetry) {
                    $this->delete($guid);
                    return $this->sync($itemData, true);
                }
                throw new Item\Exception("Error syncing item.  Too many deletion retries");

            case self::VERIFY_MATCH:
                return true;  // Do nothing on match
                break;
        }

        return false;
    }

    /**
     * Insert a item
     * @param string $guid
     * @param array $itemData
     * @throws \Exception
     * @return bool true if updated, false if not updated
     */
    public function insert($guid, $itemData)
    {
        return ($this->_upsert($guid, $itemData) === self::RESULT_INSERT);
    }


    /**
     * Update a item
     * @param string $guid
     * @param array $itemData
     * @return bool true if updated, false if not updated
     * @throws Item\Exception
     */
    public function update($guid, $itemData)
    {
        return ($this->_upsert($guid, $itemData) === self::RESULT_UPDATE);
    }


    /**
     * Delete a item by GUID
     *
     * Note: This will delete ALL items matching the GUID provided.
     *
     * @param $guid
     * @param bool $archive Archive these items? Optional.
     * @return bool true if deleted, false if not deleted
     * @throws Exception
     * @throws Item\Exception
     * @throws \MongoException
     */
    public function delete($guid, $archive=false)
    {
        try {
            if ($archive) {
                $this->_archiveItem($guid);
            }
            
            $result = $this->_dbStorage->deleteMany(
                $this->_getGuidQuery($guid),
                array(
                    'w'       => 1,
                    'justOne' => false  // Delete all matching GUIDs
                )
            );

            $numAffected = $result->getDeletedCount();

            if ($numAffected >= 1) {
                return true;
            };
        }
        catch(\MongoDB\Driver\Exception\BulkWriteException $e) {
            throw new Exception($e->getMessage());
        }

        return false;
    }

    /**
     * Archive item
     *
     * Sometimes we do not want items to really be deleted but we do need to
     * remove them from the primary item storage.  This will "move" the item to
     * a separate archive collection that will expire the items after 5 weeks.
     *
     * @param string $guid
     * @throws Item\Exception
     * @throws \MongoCursorException
     * @throws \MongoCursorTimeoutException
     * @throws \MongoException
     */
    protected function _archiveItem($guid)
    {
        $item = $this->find($guid);

        // Only try to archive it if we find something
        if (is_array($item)) {

            // Update 'lastChange' to be the current date
            $item['internal']['lastChange'] = new \MongoDB\BSON\UTCDateTime();

            $this->_dbPurge->insert($item);
        }
    }

    /**
     * Verify a item
     *
     * This is a somewhat expensive method but it should allow us to be sure the
     * data stored in Mongo is in sync with the data we have in hand.
     *
     * When provided a GUID and the item's data, this method will lookup the item
     * in the repository and verify some basic key fields.  The returned result
     * will be one of these class constants:
     *
     *  Item::VERIFY_NOT_EXISTS - The item doesn't exist in Mongo
     *  Item::VERIFY_UNKNOWN - The item exists, but we can't verify it (bad data)
     *  Item::VERIFY_DUPLICATE - There are more than one items with this GUID
     *  Item::VERIFY_MATCH - One item exists and matches the key item data
     *  Item::VERIFY_DIFFERENT - One item exists and it has different data
     *
     * @param string $guid Item's unique GUID
     * @param array $data Item data
     * @return int
     * @throws Exception
     * @throws \MongoConnectionException
     * @throws \MongoCursorTimeoutException
     */
    public function verifyItem($guid, $data)
    {
        if (!is_array($data)) {
            throw new Exception("Cannot verify item. Data must be an array.");
        }

        // Do we have the data we need to verify the item?
        if (!isset($data['source']['id']) ||
            !isset($data['source']['file']) ||
            !isset($data['internal']['rawDataHash']) ||
            !isset($data['item']['uniqueId'])) {

            return self::VERIFY_UNKNOWN;
        }

        /*
         * Find matching items
         *
         * We use find() instead of findOne() here so that we can access the
         * cursor's count() method later and see if there were duplicates in the
         * database.
         */ 
        $options = array(
            'source.id'            => 1,
            'source.file'          => 1,
            'internal.rawDataHash' => 1,
            'job.uniqueId'         => 1
        );
        $verifyItemCount = $this->_dbStorage->count(
            $this->_getGuidQuery($guid),
            $options
        );
        
        //print_r(", " . $guid . "=" . $verifyItemCount);
        if ($verifyItemCount === 0) {
            // Nothing found
            //print_r(' VERIFY_NOT_EXIST ');
            return self::VERIFY_NOT_EXIST;
        }

        if ($verifyItemCount > 1) {
            // More than one found
            //print_r(' VERIFY_DUPLICATE ');
            return self::VERIFY_DUPLICATE;
        }

        $result = $this->_find($guid)->toArray()[0];

        // Do all key fields match?
        if ($data['source']['id'] == $result['source']['id']
            && $data['source']['file'] == $result['source']['file']
            && $data['internal']['rawDataHash'] == $result['internal']['rawDataHash']
            && $data['item']['uniqueId'] == $result['item']['uniqueId'] ) {
            //print_r(' VERIFY_MATCH ');
            return self::VERIFY_MATCH;
        }
        else {

            // There's just one, and its key fields are different
            //print_r(' VERIFY_DIFFERENT ');
            return self::VERIFY_DIFFERENT;
        }
    }

    /**
     * Convert verify status to string
     * @param int $status
     * @return string
     */
    public function convertVerifyStatus($status)
    {
        $statusLookup = array(
            self::VERIFY_NOT_EXIST => 'notExist',
            self::VERIFY_DIFFERENT => 'different',
            self::VERIFY_MATCH     => 'match',
            self::VERIFY_UNKNOWN   => 'unknown',
            self::VERIFY_DUPLICATE => 'duplicate'
        );

        if (array_key_exists($status, $statusLookup)) {
            return $statusLookup[$status];
        }

        return 'verifyError';
    }

    /**
     * Extract a item GUID from item data
     * @param array $itemData
     * @return string|bool
     */
    public function extractGuid($itemData)
    {
        if (isset($itemData['internal']['guid'])) {
            return $itemData['internal']['guid'];
        }

        return false;
    }

    /**
     * Extract file info from item data
     * @param array $itemData
     * @return array|bool
     */
    public function extractFile($itemData)
    {
        if (is_array($itemData['source'])
            && array_key_exists('id', $itemData['source'])
            && array_key_exists('file', $itemData['source'])
            && array_key_exists('type', $itemData['source'])) {

            return array(
                'sourceId' => $itemData['source']['id'],
                'fileName' => $itemData['source']['file'],
                'fileId'   => isset($itemData['source']['fileId']) ? $itemData['source']['fileId'] : null,
                'type'     => $itemData['source']['type']
            );
        }

        return false;
    }
}