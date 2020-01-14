<?php

namespace Import\Request\Adapter;

use Import\Request\Exception;

abstract class AbstractRequest
{
    /**
     * @var array
     */
    protected $_requestData;

    /**
     * @var int
     */
    protected $_initTimestamp;

    /**
     * Logger
     * DO NOT ACCESS DIRECTLY
     * @var \Monolog\Logger;
     */
    protected $_logger;


    /**
     * Input adapter
     * DO NOT ACCESS DIRECTLY
     * @var \Import\Input\Adapter\AbstractAdapter
     */
    protected $_inputAdapter;

    /**
     * Run the request
     *
     * This method is to be implemented by the specific adapters.  This should
     * encapsulate the entire process of running a file.  We expect the file
     * to be parse, loaded, and downstream systems notified.
     *
     * @return array
     */
    abstract protected function _run();


    /**
     * Get the logger appropriate for this request
     * @return \Monolog\Logger
     */
    abstract protected function _getLogger();

    /**
     * Class constructor
     * @param mixed $requestData
     * @param array $config  Optional config for DI
     */
    public function __construct($requestData, $config = array())
    {
        $this->_initTimestamp = time();
        $this->_requestData   = $requestData;

        // Allow for explicit definition of the input adapter
        if (isset($config['inputAdapter'])) {
            $this->_inputAdapter = $config['inputAdapter'];
        }

        $this->_init($requestData);
    }

    protected function _init($requestData)
    {}

    /**
     * Run the request
     * @return array
     * @throws \Import\Request\Exception
     */
    public function run()
    {
        $results = $this->_run();

        // Add the received timestamp
        $results['batch']['received'] = $this->_initTimestamp;

        return $results;
    }

    /**
     * Get notify recipients
     *
     * This method looks up which Gearman workers should be notified about
     * actions taken on a given item.
     *
     * @param int $sourceId Source ID (input_source.input_source_id)
     * @return array
     */
    protected function _getNotifyRecipients($sourceId)
    {
        $result = $this->_lookupNotifyRecipientConfig($sourceId);

        if (!is_array($result) || count($result) == 0) {
            return array();
        }

        $workers = array();
        foreach($result as $row) {
            $workers[] = $row['recipient_name'];
        }

        return $workers;
    }


    protected function _lookupNotifyRecipientConfig($sourceId)
    {
        // Get DB connection
        $db = \Import\Framework\Database\Db::getSqlConnection();

        /*
         * Query the DB for recipients
         */
        $sql = '
            SELECT recipient_name
            FROM input_source AS source
            JOIN input_source_notify AS notify ON
              notify.input_source_id = source.input_source_id
            WHERE
              source.input_source_id = ?';

        return $db->fetchAll($sql, $sourceId);
    }

    protected function _getFilenameBySourceName()
    {
        // Get DB connection
        $db = \Import\Framework\Database\Db::getSqlConnection();

        /*
         * Query the DB for recipients
         */
        $sql = '
            SELECT jsf.input_source_filename
            FROM input_source_file as jsf
            JOIN input_source AS js ON
              jsf.input_source_id = js.input_source_id
            JOIN spider_profile AS js ON
              spider_profile.spider_profile_id = js.spider_profile_id
            WHERE
              spider_profile.spider_name = ?';

        return $db->fetchOne($sql, $this->_getRequestData());
    }

    /**
     * Get the original request data
     * @return mixed
     */
    protected function _getRequestData()
    {
        return $this->_requestData;
    }

    public function isValidRequest(\Import\SourceFile $SourceFile)
    {
        return $SourceFile->isConfigured();
    }
}
