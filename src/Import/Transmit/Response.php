<?php

namespace Import\Transmit;


use Import\App\Config;
use Import\Input\Adapter\AbstractAdapter as AbstractInputAdapter;
use Import\Translate\Item;

/**
 * Response class
 *
 * This class is used to log actions taken during the processing of items and to
 * return back a consistent data format.
 *
 * @package Import\Transmit
 */
class Response
{
    /**
     * @var array
     */
    protected $_summary = array();

    /**
     * @var bool
     */
    public $verbose = true;


    /**
     * @var \Monolog\Logger
     */
    protected $_logger;

    /**
     * Class constructor
     */
    public function __construct()
    {
        $this->_summary = array(
            'totals' => array(
                'differed' => 0,
                'noAction' => 0,
                'update'   => 0,
                'delete'   => 0,
                'create'   => 0,
                'errors'   => 0,
                'total'    => 0
            ),
            'batch' => array(
                'name'       => "",
                'finalName'  => "",
                'sourceId'   => "",
                'sourceType' => "",
                'logId'      => ""
            ),
            'items' => array(),
            'errors' => array()
        );

        if (Config::get('INPUT_LOGGING') &&
            Config::get('INPUT_LOGGING_VERBOSE')) {
            $this->verbose = true;
        }
    }

    /**
     * Load input adapter data automatically
     * @param AbstractInputAdapter $InputAdapter
     */
    public function loadInputAdapterData(AbstractInputAdapter $InputAdapter)
    {
        $this->setBatchName($InputAdapter->getFileName())
            ->setBatchFinalName($InputAdapter->getFinalFileName())
            ->setSourceId($InputAdapter->getOwnerId())
            ->setSourceType($InputAdapter->getOwnerType())
            ->setLogId($InputAdapter->getLogId());
    }

    /**
     * Set the batch name
     * @param string $name
     * @return \Import\Transmit\Response
     */
    public function setBatchName($name)
    {
        $this->_summary['batch']['name'] = $name;
        return $this;
    }

    /**
     * Set final batch name
     * @param string $name
     * @return \Import\Transmit\Response
     */
    public function setBatchFinalName($name)
    {
        $this->_summary['batch']['finalName'] = $name;
        return $this;
    }

    /**
     * Set source ID
     * @param int $id
     * @return \Import\Transmit\Response
     */
    public function setSourceId($id)
    {
        $this->_summary['batch']['sourceId'] = $id;
        return $this;
    }

    /**
     * Set source type
     * @param string $type
     * @return \Import\Transmit\Response
     */
    public function setSourceType($type)
    {
        $this->_summary['batch']['sourceType'] = $type;
        return $this;
    }

    /**
     * Set log ID
     * @param int $id
     * @return \Import\Transmit\Response
     */
    public function setLogId($id)
    {
        $this->_summary['batch']['logId'] = $id;
        return $this;
    }

    /**
     * Log error
     * @param \Exception $exception
     * @param int $position
     * @return int
     */
    public function logError(\Exception $exception, $position=0)
    {
        $this->_summary['totals']['errors']++;

        $errorInfo = array(
            'type' => get_class($exception),
            'position' => $position,
            'message' => $exception->getMessage()
        );

        // Add verbose error info, if enabled
        if ($this->verbose) {
            $errorInfo['file']  = $exception->getFile();
            $errorInfo['line']  = $exception->getLine();
            $errorInfo['trace'] = $exception->getTraceAsString();
        }

        $this->_summary['errors'][] = $errorInfo;

        return count($this->_summary['errors']);
    }

    /**
     * Log a item's CRUD action
     * @param Item $Item
     * @param int $crudType
     */
    public function logCrudAction(Item $Item, $crudType)
    {
        $status = 'unknown';
        $this->_summary['totals']['total']++;

        switch($crudType) {
            case Item::CRUD_CREATE:
                $this->_summary['totals']['create']++;
                $status = 'created';
                break;

            case Item::CRUD_UPDATE:
                $this->_summary['totals']['update']++;
                $status = 'updated';
                break;

            case Item::CRUD_NONE:
                $this->_summary['totals']['noAction']++;
                $status = 'unchanged';
                break;

            case Item::CRUD_DIFFERED:
                $this->_summary['totals']['differed']++;
                $status = 'differed';
        }

        // Log individual items (if enabled)
        if ($this->verbose) {
            $this->_summary['items'][] = array(
                'guid' => $Item->getExistingItemGuid(),
                'status' => $status,
                'validationErrors' => $Item->getSourceMappingValidationErrors()
            );
        }
    }

    /**
     * Log delete GUIDs
     * @param array $guids
     */
    public function logDeleteGuids($guids)
    {
        if (!is_array($guids)) {
            $guids = array($guids);
        }

        foreach ($guids as $deletedGuid) {
            $this->_summary['totals']['delete']++;
            $this->_summary['totals']['total']++;

            $this->_summary['items'][] = array(
                'guid' => $deletedGuid,
                'status' => 'deleted',
                'validationErrors' => array()
            );
        }
    }

    /**
     * Get CRUD totals
     * @return array
     */
    public function getCrudTotals()
    {
        return $this->_summary['totals'];
    }

    /**
     * Get the error count
     * @return int
     */
    public function getErrorCount()
    {
        return count($this->_summary['errors']);
    }

    /**
     * Get response as an array
     * @return array
     */
    public function asArray()
    {
        return $this->_summary;
    }
}