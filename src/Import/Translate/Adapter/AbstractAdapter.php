<?php

namespace Import\Translate\Adapter;

use Import\Log\AbstractLogable;
use Import\Translate\Item;
use Import\Translate\Parser\Adapter\AdapterInterface as ParserInterface;

/**
 * Interface for translation adapters
 */
abstract class AbstractAdapter extends AbstractLogable
{
    /**
     * @var \Import\Input\Adapter\AbstractAdapter
     */
    protected $_file;

    /**
     * @var \Import\Transmit\Adapter\Ai2
     */
    protected $_transmitter;

    /**
     * @var ParserInterface
     */
    protected $_parser;

    /**
     * Class constructor
     * @param \Import\SourceFile $SourceFile
     * @param ParserInterface $Parser
     * @param array $configOptions
     * @internal param \Import\Input\Adapter\AbstractAdapter $importFile
     */
    public function __construct(\Import\SourceFile $SourceFile, ParserInterface $Parser, \Import\Transmit\Adapter\Ai2 $Transmitter, array $configOptions = array())
    {
        // Save an instance of the import file
        $this->_file = $SourceFile;

        $this->_transmitter = $Transmitter;

        $this->_parser = $Parser;

        // Get any amendments to DI config from instance
        $configOptions = $this->_initConfigOptions($configOptions);

        // Run DI init
        $this->_setOptions($configOptions);
    }

    /**
     * Initialize config options
     *
     * Accepts the configuration options passed to the constructor and returns
     * a copy of that config array with any needed additions/subtractions.
     *
     * @param array $configOptions
     * @return array
     */
    protected function _initConfigOptions($configOptions)
    {
        return $configOptions;
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
     * Set transmitter
     * @todo Add support for a more generic transmitter?
     * @param \Import\Transmit\Adapter\AbstractAdapter $transmitter
     */
    protected function _setOptionTransmitter(\Import\Transmit\Adapter\AbstractAdapter $transmitter)
    {
        $this->_transmitter = $transmitter;
    }

    /**
     * Set the item parser
     * @param ParserInterface $parser
     */
    protected function _setOptionParser(ParserInterface $parser)
    {
        $this->_parser = $parser;
    }

    /**
     * Get the input adapter
     * @return \Import\SourceFile
     */
    public function getSourceFile()
    {
        return $this->_file;
    }

    /**
     * Translate next item
     *
     * Using the input file, this method retrieves item data for the next item in the file.
     *
     * @return \Import\Translate\Item
     */
    public function translateNext()
    {
        $Item = $this->_parser->getNextItem();
        if ($Item instanceof \Import\Translate\Item) {
            $Item->logItem();
        }
        return $Item;
    }

    /**
     * Transmit a formatted item
     * @param \Import\Translate\Item|\Import\Translate\Item $Item
     * @param int $position
     * @return mixed
     */
    public function transmitOne(\Import\Translate\Item $Item, $position)
    {
        return $this->_transmitter->send($Item, $position);
    }


    public function hasMoreItems()
    {
        return $this->_parser->hasMoreItems();
    }

    /**
     * Transmit all items
     *
     * Wrapper method for chaining translateNext(), format(), and transmitOne()
     *
     * @todo See if there's a way to batch this method into fewer requests.
     * @return array
     */
    public function transmitAll()
    {
        /*
         * This variable helps us tie errors back to a specific "position" in
         * the source.  It has no other use.
         */
        $position = 0;

        $Logger = $this->_getLogger();
        $defaultLogContext = array(
            'file' => $this->getSourceFile()->getFilename(),
            'fileLogId' => $this->getSourceFile()->getFileLogId()
        );


        $Logger->info("Beginning to translate items.", $defaultLogContext);

        // While we have more items to process...
        while ($this->hasMoreItems()) {
            try {

                // Increment position counter
                $position++;

                // Extract the next item from the file
                $Item = $this->translateNext();


                /*
                 * No item actually found.  *Might* be legit in some SAX
                 * scenarios where the end of the file hasn't been reached yet
                 * but the remaining data doesn't actually contain item data.
                 */
                if ($Item === false) {

                    $Logger->info("No more items to translate.", array_merge(
                        $defaultLogContext,
                        array(
                            'numTranslated' => $position - 1
                        )
                    ));

                    // If we go forward all sorts of things will break.
                    continue;
                }

                // TODO: Move logging of the item here and out of the item itself

                $Logger->info("Translated item.", array_merge(
                    $defaultLogContext,
                    array(
                        'itemLogId'       => $Item->getLogId(),
                        'hash'           => $Item->getHash(),
                        'positionInFile' => $position
                    )
                ));

                $this->transmitOne($Item, $position);
            }
            /*
             * Errors parsing XML are more serious that day-to-day errors
             * processing a item.  Flag these as critical to hopefully have the
             * integration fixed ASAP.
             */
            catch (\JT\Xml\SaxParser\Exception $e) {

                $fileSize = null;
                if (is_file($this->getSourceFile()->getAbsoluteFilename())) {
                    $fileSize = @filesize($this->getSourceFile()->getAbsoluteFilename());
                }

                $Logger->critical("Error SAX parsing file.", array_merge(
                    $defaultLogContext,
                    array(
                        'fileSize'       => $fileSize,
                        'positionInFile' => $position
                    )
                ));

                $this->_transmitter->logError($e, $position);
            }
            /*
             * Generic error.  Flag as a warning only.
             */
            catch (\Exception $e) {

                $Logger->warn("Exception thrown translating item.", array_merge(
                    $defaultLogContext,
                    array(
                        'exception'      => $e->getMessage(),
                        'trace'          => $e->getTrace(),
                        'positionInFile' => $position
                    )
                ));

                $this->_transmitter->logError($e, $position);
            }
        }

        $Logger->info("Finished translating items.", array_merge(
            $defaultLogContext,
            array(
                'numTranslated' => $position - 1
            )
        ));

        /*
         * Now that we know everything that is in the file, we can begin to
         * delete the items that are missing from it...
         */
        $this->_transmitter->finalize();

        return $this->_transmitter->getTransmitSummary();
    }


    protected function _updateTransmitResults($transmitResult, $cumulativeResults, Item $Item)
    {
        $status = 'unknown';

        switch($transmitResult['status']) {
            case Item::CRUD_CREATE:
                $cumulativeResults['totals']['create']++;
                $cumulativeResults['totals']['total']++;
                $status = 'created';
                break;

            case Item::CRUD_UPDATE:
                $cumulativeResults['totals']['update']++;
                $cumulativeResults['totals']['total']++;
                $status = 'updated';
                break;

            case Item::CRUD_NONE:
                $cumulativeResults['totals']['noAction']++;
                $cumulativeResults['totals']['total']++;
                $status = 'unchanged';
                break;
        }


        $results['items'][] = array(
            'guid' => $Item->getExistingItemGuid(),
            'status' => $status,
            'validationErrors' => $Item->getSourceMappingValidationErrors()
        );


        return $cumulativeResults;
    }

    /**
     * Get the transmitter
     * @return \Import\Transmit\Adapter\AbstractAdapter
     */
    public function getTransmitter()
    {
        return $this->_transmitter;
    }
}