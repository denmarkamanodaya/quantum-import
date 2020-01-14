<?php

namespace Import\Input\Adapter;

use Import\Log\AbstractLogable;
use Import\SourceFile;
use Import\Framework\Database\Db as Db;
use Import\Translate\ETL\AbstractMapping as EtlMappingAbstract;
use Import\Translate\ETL\ExternalLookup as EtlMappingExternal;
use Import\Translate\ETL\ProfileKey as EtlMappingKey;

/**
 * Interface for file inputs
 */
abstract class AbstractAdapter extends AbstractLogable implements \Serializable
{
    /**
     * @var \Import\SourceFile
     */
    protected $_sourceFile;

    /**
     * @var \Zend_Db_Adapter_Pdo_Abstract
     */
    protected $_db;

    /**
     * @var bool Has this file been logged yet?
     */
    protected $_fileHasBeenLogged = false;

    /**
     * @var int Log ID of this file the last time we received it
     */
    protected $_previousLogId;

    /**
     * @var int Current log ID of this file
     */
    protected $_logId;

    /**
     * @var array Items that we discovered in this file
     */
    protected $_fileItems = array();

    /**
     * @var EtlMapping
     */
    protected $_etlMapping;


    /**
     * @var \Import\Translate\Adapter\AbstractAdapter
     */
    protected $_translator;

    /** --- ABSTRACT METHODS --- **/

    /**
     * Get the file name
     * @deprecated
     * @return string
     */
    public function getFileName()
    {
        return $this->_sourceFile->getFilename();
    }

    /**
     * Get the original request data
     *
     * This is used to help facilitate object serialization.
     *
     * @return array;
     */
    abstract public function getOriginalRequestData();


    /** --- CONCRETE METHODS --- **/


    /**
     * Constructor
     * @param SourceFile $SourceFile
     * @param array $configOptions
     */
    public function __construct(SourceFile $SourceFile, array $configOptions=array())
    {
        $this->_sourceFile = $SourceFile;

        // DI...
        if (!isset($configOptions['db'])) {
            $configOptions['db'] = Db::getSqlConnection();
        }

        $configOptions = $this->_setDefaultDependencies($configOptions);
        $this->_setOptions($configOptions);

        if ( ! $this->isValid()) {
            throw new \RuntimeException("File adapter could not be validated.");
        }
    }

    /**
     * Set additional default dependencies
     *
     * @param array $configOptions
     * @return array
     */
    protected function _setDefaultDependencies(array $configOptions = [])
    {
        return $configOptions;
    }

    /**
     * Initialize the file log
     *
     *
     *
     * @throws \Exception
     * @return array
     */
    public function initializeFileLog()
    {
        try {
            if ( ! $this->isValid()) {

                $this->_getLogger()->error("Invalid request.", array(
                    'file' => $this->getFileName()
                ));

                throw new Exception("Request is not valid or cannot be verified.");
            }

            /*
             * Hook to allow implementing classes to make changes before we
             * begin to log anything into the database.
             */
            $this->_prepareToSave();

            /*
             * Log the file and check to see if it's part of a collection of
             * files.  This will generate or lookup "File Log ID" which is used
             * to link all the items found in a file back to the source file.
             */
            $this->_sourceFile->getFileLogId();

        }
        catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Set all dependencies
     *
     * Loops over $options array and attempts to find protected methods matching
     * "_setOption[keyName]".  If found, the value is passed to the method.
     *
     * @param  array $options
     * @return bool
     */
    protected function _setOptions($options)
    {
        if (!is_array($options)) {
            return false;
        }

        foreach ($options as $key => $value) {
            $method = '_setOption' . $key;
            if (method_exists($this, $method)) {
                $this->$method($value);
            }
        }

        return true;
    }

    /**
     * Is valid file?
     *
     * @deprecated
     * @return bool
     */
    public function isValid()
    {
        return $this->_sourceFile->isConfigured();
    }

    /**
     * Get the owner of these items
     *
     * @deprecated
     * @return bool|string
     */
    public function getOwnerId()
    {
        return $this->getInputId();  // Duplicate lookup
    }

    /**
     * Get the file source type name
     *
     * This is used to help enrich the Mongo meta-data so we can log the type
     * of source a item came from.
     *
     * @deprecated
     * @return string
     */
    public function getOwnerType()
    {
        return $this->_sourceFile->getSourceDataValue('source_type');
    }

    /**
     * Get absolute filename
     *
     * Returns the complete path to the file so it can be read from disk. By
     * default this is just an alias of getFileName().
     *
     * @return string
     */
    public function getAbsoluteFileName()
    {
        return $this->_sourceFile->getFilename();
    }

    /**
     * Get the size of the input file
     *
     * By default we return null here since not all inputs are necessarily files
     *
     * @return int
     */
    public function getFileSize()
    {
        return null;
    }

    /**
     * Save file
     *
     * Checks to see if this file is part of a larger file that is being
     * delivered in batches.  If we detect an incomplete batch, we just look up
     * the ID of that batch and run with it.  Otherwise we insert a new file log
     * entry and use that primary key.
     *
     * @deprecated Use `initializeFileLog` instead.
     * @throws Exception
     */
    public function save()
    {
        return $this->initializeFileLog();
    }

    /**
     * Get final file name
     *
     * This method is provided as a work around to allow certain adapters the
     * leeway to rename the original file and have that renamed file be returned
     * here.  The bulkpost adapter is the most concrete example of this.  By
     * default this method is just a wrapper around the core getFileName()
     * method.
     *
     * Note: This is the filename that is saved into item_source_file_log but as
     *   of 10/2/2013, it's not really used anywhere else.
     *
     * @return string
     */
    public function getFinalFileName()
    {
        return $this->getFileName();
    }

    /**
     * Get configured filename
     *
     * Returns the glob-style filename that we store in the database.  This
     * value can be used by things such as Source Mapping.
     *
     * @deprecated
     * @return string
     */
    public function getConfiguredFileName()
    {
        return $this->_sourceFile->getConfiguredFileName();
    }

    /**
     * Complete this file log entry
     *
     * This is typically called after the deletion processing completes.  This
     * essentially closes a file--particularly important when we receive files
     * in batches and not all at once.  When a file is incomplete, we consider
     * any new items received to be part of a larger batch.
     *
     * @deprecated
     */
    public function completeFile()
    {
        return $this->_sourceFile->markFileComplete();
    }

    /**
     * Get the user ID
     *
     * Returns the internal Prepare API ID of the user/entity that is uploading
     * items to us.  This will be the `item_source_id` out of the `item_source`
     * table.
     *
     * @deprecated
     * @return bool|int
     */
    public function getInputId()
    {
        return $this->_sourceFile->getSourceDataValue('id');
    }

    /**
     * Get the file id
     *
     * We will receive new versions of this file over and over.  This is the ID
     * of every/all versions of the file so we can link everything back to a
     * single user. This will be the `item_source_file_id` out of the
     * `item_source_file` table.
     *
     * @deprecated
     * @return bool|int
     */
    public function getFileId()
    {
        return $this->_sourceFile->getFileId();
    }

    /**
     * Set the log ID
     *
     * This is called as part of unserialization.
     *
     * @deprecated
     * @param int $id
     * @throws Exception
     */
    protected function _setLogId($id)
    {
        $this->_sourceFile->setFileLogId($id);
    }

    /**
     * Get file's CURRENT log ID
     *
     * It's important to note that the log ID is only generated after save() is
     * called.  That method performs an insert that actually logs this current
     * instance of the file.
     *
     * Also, note that getPreviousLogId() will return the log ID of the file
     * received immediately proceeding this one.
     *
     * @deprecated
     * @return int|bool
     */
    public function getLogId()
    {
        return $this->_sourceFile->getFileLogId();
    }

    /**
     * Get previously logged items
     *
     * @deprecated
     * @throws Exception
     * @return array loggedItems
     */
    public function getPreviousLoggedItems()
    {
        $this->_sourceFile->getPreviousLoggedItems();
    }

    /**
     * Get the item GUID from the previous file data
     * @param string $uniqueId
     * @return string|null
     */
    public function getGuidFromPreviousFile($uniqueId)
    {
        $previousItem = $this->getItemDataFromPreviousFile($uniqueId);

        return (isset($previousItem['guid']))
            ? $previousItem['guid']
            : null;
    }

    /**
     * Get item data from the _previous_ file using the unique ID as a lookup
     *
     * @param string $uniqueId
     * @return array
     */
    public function getItemDataFromPreviousFile($uniqueId)
    {
        $items = $this->_sourceFile->getPreviousLoggedItems();

        return (isset($items[$uniqueId]))
            ? $items[$uniqueId]
            : [];
    }

    /**
     * Get items to be deleted
     *
     * This method should be called ONLY once we've finished extracting all items
     * from a file.  As items are extracted from the file, they will call the
     * addItem() method which logs their uniqueId into the _itemsInFile property.
     * That property is then compared in this method to the items logged in the
     * db last time we received this file.  Items in the old file but not in the
     * current version are considered "deletes"
     *
     * @deprecated
     * @return array
     */
    public function getItemsToDelete()
    {
        return $this->_sourceFile->getItemsToDelete();
    }


    /**
     * Add item
     *
     * This method should be called automatically by the Item object when it is
     * instantiated.  In this way each item that is parsed out of the XMl/JSON
     * file "phones home" so we can log its unique ID.  Once we have compiled all of
     * the unique IDs we can then tell which items are missing as compared to the
     * previous file.  Missing items are treated as deleted items.
     *
     * @param \Import\Translate\Item $Item
     */
    public function addItem(\Import\Translate\Item $Item)
    {
        $this->_fileItems[] = $Item->getUniqueId();
    }



    /**
     * Get the item code prefix
     *
     * The item code prefix is a unique identifier for the source and source type
     * of a item.  The prefix code is used when logging a new item and generating
     * its own GUID.
     *
     * @deprecated
     * @return bool|string
     */
    public function getItemCodePrefix()
    {
        return $this->_sourceFile->getItemCodePrefix();
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
     * @deprecated
     * @see \Import\Translate\Adapter\AbstractAdapter::transmitAll
     * @param array $totals
     * @return int
     */
    public function logCrudTotals($totals)
    {
        $this->_sourceFile->logCrudTotals($totals);
    }

    /**
     * Prepare to save hook
     *
     * Allow implementing classes to perform actions prior to a file from being
     * saved.
     */
    protected function _prepareToSave()
    {}

    /**
     * Get a specific config setting
     *
     * @deprecated
     * @param string $name
     * @param string $default
     * @return string
     */
    public function getConfigSetting($name, $default=null)
    {
        return $this->_sourceFile->getConfigSetting($name, $default);
    }

    /**
     * String representation of object
     * @link http://php.net/manual/en/serializable.serialize.php
     * @return string the string representation of the object or null
     */
    public function serialize()
    {
        $config = array(
            'origRequest'   => $this->getOriginalRequestData(),
            'logId'         => $this->_sourceFile->getFileLogId(),
            'previousLogId' => $this->_sourceFile->getPreviousFileLogId()
        );

        return json_encode($config);
    }

    /**
     * Constructs the object
     * @link http://php.net/manual/en/serializable.unserialize.php
     * @param string $serialized <p>
     * The string representation of the object.
     * </p>
     * @return void
     */
    public function unserialize($serialized)
    {
        $config = json_decode($serialized, true);

        $this->__construct($config['origRequest']);
        $this->_setLogId($config['logId']);
        $this->_previousLogId = $config['previousLogId'];
    }

    /**
     * Is source mapping required?
     *
     * At present, not all input types require source mapping.  Using this
     * method, each adapter can indicate whether source mappings should be
     * required or not.
     *
     * @return bool
     */
    public function isSourceMappingRequired()
    {
        return true;
    }

    /**
     * Get ETL
     * @return EtlMappingAbstract
     */
    public function getEtlMapping()
    {
        if ( ! $this->isSourceMappingRequired()) {
            return new \Import\Translate\ETL\Blackhole(null);
        }

        if ($this->_etlMapping instanceof EtlMappingAbstract) {
            return $this->_etlMapping;
        }

        $etlProfileId = $this->_sourceFile->getEtlSourceMappingId();

        if ($etlProfileId) {
            $this->_etlMapping = new EtlMappingKey($etlProfileId);
        }
        else {
            $this->_etlMapping = new EtlMappingExternal(
                $this->_sourceFile->getConfiguredFileName()
            );
            $this->_etlMapping->setSystemType($this->getOwnerType());
        }

        return $this->_etlMapping;
    }
}
