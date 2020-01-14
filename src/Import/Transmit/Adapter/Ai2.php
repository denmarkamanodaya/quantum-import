<?php

namespace Import\Transmit\Adapter;

use \Import\Log;
//use Import\Monitor;
use \Import\Translate\Item;
use Import\Framework\App\Config;
use \Import\Framework\AI2\Item as Ai2Item;
use Import\Transmit\Adapter\Ai2\DeleteException;
use \Import\Transmit\Exception;


/**
 * AI2 Item Transmitter
 *
 * This class is responsible for taking a \Import\Translate\Item object and submitting it to the AI2 API via the
 * appropriate HTTP REST method.
 */
class Ai2 extends AbstractAdapter
{
    /**
     * @var \Import\Framework\AI2\Item
     */
    protected $_transmitter;

    /**
     * @var \Monolog\Logger Monolog logger
     */
    protected $_logger;

    /**
     * Class constructor
     * @param array $options
     * @return array
     */
    protected function _init($options=array())
    {
        // Set the transmitter
        if (!isset($options['transmitter'])) {
            $options['transmitter'] = Ai2Item::getInstance();
        }

        return $options;
    }



    /**
     * Set the transmitter
     * @param $transmitter
     */
    protected function _setOptionTransmitter($transmitter)
    {
        $this->_transmitter = $transmitter;
    }

    /**
     * Send CRUD request
     *
     * Originally this code performed an HTTP REST call to insert items into
     * Mongo, hence we are treating this as a "transmitter".  Now we are using
     * the AI2 module to directly insert into Mongo.
     *
     * @param Item $Item
     * @param int $position
     * @throws \Import\Transmit\Exception
     * @throws \Exception
     * @return bool
     */
    public function send(Item $Item, $position)
    {
        try {
            $formattedData = $this->format($Item);

            /*
             * To help us monitor slippages in synchronicity, we extract the
             * true Mongo state of the item here so we can compare with our
             * expected status.
             */
            $mongoCrudStatus = $this->_transmitter->verifyItem(
                $Item->getExistingItemGuid(),
                $formattedData
            );

            /*
             * Get verified CRUD status based on actual state of AI2 repo.  This
             * method also will send monitoring events if the two systems are
             * out of sync.
             */
            $verifiedCrudStatus = $this->_getVerifiedCrudStatus(
                $mongoCrudStatus,
                $Item->getCrudState(),
                $Item
            );

            /*
             * Synchronize this item with AI2 repository.
             */
            $syncResult = $this->_transmitter->sync($formattedData);

            /*
             * To limit the amount of memory used, we "reset" the item here to
             * empty out all the cached data in the item object.
             */
            $Item->reset();
        }
        catch (\Exception $e) {


            throw $e; //new Exception("Error syncing data with AI2. (" . $e->getMessage() .')');
        }

        /*
         * If the sync result is TRUE then everything worked
         */
        if (true === $syncResult) {
            $this->_response->logCrudAction($Item, $verifiedCrudStatus);
            return $verifiedCrudStatus;
        }

        throw new Exception("Unknown error syncing item with AI2.");
    }

    /**
     * Delete a item
     *
     * Since deletes typically happen after the fact, we need this separate
     * method for deleting items.  Also, it's worth noting that a delete is a
     * special case where we don't have a concrete Item object because deletes
     * are the ABSENCE of a concrete item.  For this reason, we just accept an
     * array containing 'uniqueId' and 'accountId'
     *
     * @param array $guid
     * @param bool $archive bool Optional. Archive these items?
     * @throws \Exception
     * @return bool
     */
    public function deleteItems($guid, $archive=false)
    {
        try {
            $result = $this->_transmitter->delete($guid, $archive);  
            
            if ($result === false && $this->_transmitter->count($guid) > 0) {
                throw new DeleteException("Error deleting items.");
            }

            $this->_response->logDeleteGuids($guid);

            return $result;
        }
        catch (\Exception $e) {
            // Monitor::getInstance()
            //     ->event('transmit.deleteItem')
            //     ->message("Error deleting items")
            //     ->severity(Monitor::SEVERITY_CRITICAL)
            //     ->send();

            throw $e;
        }
    }




    /**
     * Format a item for use by the AI2 API
     * @param \Import\Translate\Item $Item
     * @return bool|array
     */
    public function format(Item $Item)
    {
        $formattedData = array(
            'version' => $Item->getFormatVersion(),
            'source' => array (
                'id'     => (int) $Item->getOwnerId(),
                'type'   => $Item->getOwnerType(),
                'file'   => $Item->getFileName(),
                'fileId' => $this->_file->getFileId()
            ),
            'internal' => array(
                'lastChange'  => new \MongoDB\BSON\UTCDateTime(),
                'rawDataHash' => $Item->getHash(),
                'importId'    => (int) $Item->getLogId(),
                'guid'        => $Item->getExistingItemGuid()
            ),
            'item' => array(
                'uniqueId' => $Item->getUniqueId(),
                'data'     => $this->_filterMongoKeys($Item->getData())
            ),
            'sourceMap' => array(
                'data' => $this->_filterMongoKeys(
                    $Item->getSourceMappedDataArray()
                ),
                'validationErrors' => $Item->getSourceMappingValidationErrors()
            )
        );

        /*
         * Some values are only set on the initial creation
         */
        if ($Item->getCrudState() === Item::CRUD_CREATE) {
            $formattedData['internal']['received'] = new \MongoDB\BSON\UTCDateTime();
        }

        return $formattedData;
    }

    /**
     * Strip restricted characters from keys
     *
     * @see http://docs.mongodb.org/manual/reference/limits/#Restrictions-on-Field-Names
     * @param array $values
     * @return array
     */
    protected function _filterMongoKeys($values)
    {
        if (!is_array($values)) {
            return $values;
        }

        $restricted = array(".", '$', "\0");
        $filtered = array();

        foreach ($values as $key => $value) {
            $filteredKey = str_replace($restricted, '_', $key);
            if (is_array($value)) {
                // Recursive call to also filter any nested arrays
                $value = $this->_filterMongoKeys($value);
            }
            $filtered[$filteredKey] = $value;

            // Log keys that are filtered so we know about them
            if ($filteredKey != $key) {
                // MonitorMonitor::getInstance()
                //     ->event('transmit.filterMongoKeys')
                //     ->message("Invalid Mongo Key")
                //     ->severity(Monitor::SEVERITY_WARN)
                //     ->data(
                //         array(
                //             'originalKey' => $key,
                //             'filteredKey' => $filteredKey
                //         )
                //     )
                //     ->send();
            }
        }

        return $filtered;
    }

    /**
     * Get "verified" CRUD status
     *
     * This method reconciles the crud status of a item with the expected status.
     *
     * @param int $mongoStatus
     * @param int $sqlStatus
     * @param \Import\Translate\Item $Item
     * @return bool|int
     */
    protected function _getVerifiedCrudStatus($mongoStatus, $sqlStatus, Item $Item)
    {
        // Get string versions of the status constants
        $mongoString = Ai2Item::getInstance()->convertVerifyStatus($mongoStatus);
        $sqlString   = $Item->convertCrudState($sqlStatus);

        /*
         * Convert the Mongo constants to the SQL values to compare apples to
         * apples
         */
        $normalizedStatus = false;
        switch ($mongoStatus) {
            case Ai2Item::VERIFY_DIFFERENT:
                $normalizedStatus = Item::CRUD_UPDATE;
                break;

            case Ai2Item::VERIFY_MATCH:
                $normalizedStatus = Item::CRUD_NONE;
                break;

            case Ai2Item::VERIFY_NOT_EXIST:
                $normalizedStatus = Item::CRUD_CREATE;
                break;
        }

        /*
         * If the Mongo status is unknown, something bad is up
         */
        if ($mongoStatus === Ai2Item::VERIFY_UNKNOWN) {

            // TODO: Add logging here
        }

        /*
         * If we want to delete the item we just confirm the item really is in
         * Mongo as we expect.
         */
        if ($sqlStatus == Item::CRUD_DELETE) {

            // Item should be one of these in Mongo if all is well
            $existsInMongo = array(
                Ai2Item::VERIFY_MATCH,
                Ai2Item::VERIFY_DIFFERENT
            );

            // If not what we would expect, log it
            if (!in_array($mongoStatus, $existsInMongo)) {

                // TODO: Add logging here
            }

            return $sqlStatus;
        }

        /*
         * Here the 2 systems are in sync and all is good
         */
        if ($normalizedStatus === $sqlStatus) {
            return $normalizedStatus;
        }


        // TODO: Add logging here

        return $normalizedStatus;
    }

    /**
     * Finalize transmissions
     *
     * This method is responsible for performing any needed deletes.  When
     * finished this returns an array of the item guids we deleted.
     *
     * @return array
     */
    public function finalize()
    {
        $itemsDeleted = $this->_deleteAllMissingItems();

        $this->_file->completeFile();
        $this->_file->logCrudTotals($this->_response->getCrudTotals());

        return $itemsDeleted;
    }

    /**
     * Delete all missing items
     * @throws \Exception
     * @return array GUIDs deleted
     */
    protected function _deleteAllMissingItems()
    {
        /*
         * Get items flagged for deletion.
         *
         * Note: the array we get back here will be an array of arrays with
         * these indexes:
         *  - uniqueId
         *  - accountId
         *  - guid
         */
        $itemsToDelete = $this->_file->getItemsToDelete();

        // GUIDs to delete
        $guids = array();

        foreach($itemsToDelete as $itemData) {
            if (isset($itemData['guid']) && $itemData['guid']) {
                $guids[] = $itemData['guid'];
            }
        }


        try {
            // Transmit the DELETE action to the AI2 API
            $this->deleteItems($guids);
        }
        catch (\Exception $e) {
            // Monitor::getInstance()
            //     ->event('translate.finalize')
            //     ->message("Error deleting item(s)")
            //     ->severity(Monitor::SEVERITY_ERROR)
            //     ->data(
            //         array(
            //             'guids'            => $guids,
            //             'file'             => $this->_file->getFileName(),
            //             'exceptionMessage' => $e->getMessage()
            //         )
            //     )->send();

            throw $e;
        }

        return $guids;
    }


    protected function _getLogger()
    {
        if ($this->_logger instanceof \Monolog\Logger) {
            return $this->_logger;
        }

        $this->_logger = Log::getLogger('ai2.transmit');

        return $this->_logger;
    }

    /**
     * Get logger instance
     * @return \Monolog\Logger
     */
    protected function _getLoggerComponent()
    {
        return "transmit.ai2";
    }
}