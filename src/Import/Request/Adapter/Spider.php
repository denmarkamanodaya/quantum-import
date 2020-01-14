<?php

namespace Import\Request\Adapter;

use Import\Request\InputWorkerNotifyTrait;
use Import\SourceFile;

class Spider extends AbstractRequest
{
    use InputWorkerNotifyTrait;

    /**
     * @var SourceFile
     */
    protected $_sourceFile;

    protected function _run()
    {
        /*
         * Kick off the whole process.
         *
         * Result is expected to be in this format, as provided by
         * \Import\Translate\Adapter\AbstractAdapter::transmitAll()
         *
         * array(
         *  'totals' => array(
         *      'noAction' => [int],
         *      'update'   => [int],
         *      'delete'   => [int],
         *      'create'   => [int],
         *      'errors'   => [int],
         *      'total'    => [int]
         *  ),
         *  'batch' => array(
         *      'name'       => [string]
         *      'sourceId'   => [int],
         *      'sourceType' => [string],
         *      'logId'      => [int]
         *  ),
         *  'items' => array(
         *      array(
         *          'guid' => [string],
         *          'status' => [string]
         *      )
         *  )
         * );
         */

        $this->_sourceFile = new SourceFile(
            SourceFile::SOURCE_SPIDER,
            $this->_getRequestData()['file']
        );

        $SpiderFile     = new \Import\Input\Adapter\Spider($this->_sourceFile);

        $Parser           = new \Import\Translate\Parser\Adapter\JsonPacket($SpiderFile);
        $Transmitter      = new \Import\Transmit\Adapter\Ai2($SpiderFile);
        $TranslateAdapter = new \Import\Translate\Adapter\JsonPacket($this->_sourceFile, $Parser, $Transmitter);
        $Logger           = \Import\Log::getLogger('input.spider');

        if (!$SpiderFile->isValid()) {
            $Logger->error("Invalid spider request.", array(
                'file'     => $SpiderFile->getFileName(),
                'userData' => $SpiderFile->getUserData()
            ));
        }

        $t = time();
        $Logger->info("Beginning file processing.", array(
            'file' => $SpiderFile->getFileName()
        ));

        // Initialize file log
        $SpiderFile->initializeFileLog();

        $results = $TranslateAdapter->transmitAll();

        // Add the received timestamp
        $results['batch']['received'] = $this->_initTimestamp;

        $Logger->info("Completed file processing.", array(
            'file' => $SpiderFile->getFileName(),
            'secondsToProcess' => time() - $t,
            'results' => $results
        ));


        $results['workersNotified'] = $this->processGearmanNotifications(
            $results,
            $this->_getNotifyRecipients($this->_sourceFile->getSourceId()),
            $this->_sourceFile->getFilename(),
            $Logger
        );

        return $results;
    }

    /**
     * Get the logger appropriate for this request
     * @return \Monolog\Logger
     */
    protected function _getLogger()
    {
        return \Import\Log::getLogger('input.spider');
    }
}
