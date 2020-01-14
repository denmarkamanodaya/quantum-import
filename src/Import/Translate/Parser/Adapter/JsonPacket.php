<?php

namespace Import\Translate\Parser\Adapter;

use Import\Translate\Parser\Exception as ItemsException;
use Import\Input\Adapter\AbstractAdapter as InputAdapter;
use Import\Translate\Item;

/**
 * JSON Packet
 *
 * A "JSON Packet" is the file format used by the new Spider system.  It's a
 * file format that contains multiple lines of JSON.  Each line contains the
 * item data and any associated metadata.
 *
 * @package Import\Translate\Parser\Adapter
 */
class JsonPacket implements AdapterInterface
{
    /**
     * @var string Beginning of file delimiter
     */
    protected $_bof = '--==Begin==--';

    /**
     * @var string End of file delimiter
     */
    protected $_eof = '--==End==--';

    /**
     * @var resource The file handle resource returned by fopen()
     */
    protected $_fileHandler;

    /**
     * @var int Max characters to read per line/item (122880 == 120KB)
     */
    protected $_maxLineReadSize = 122880;

    /**
     * @var bool Does this file contain more items?
     */
    protected $_hasMoreItems = true;

    /**
     * @var \Import\Input\Adapter\AbstractAdapter
     */
    protected $_inputAdapter;

    /**
     * Constructor
     * @param InputAdapter $InputFile
     * @param array $itemOptions
     * @throws ItemsException
     */
    public function __construct(InputAdapter $InputFile, $itemOptions=array())
    {
        // Open file handler
        $this->_initFileHandler($InputFile);

        $this->_inputAdapter = $InputFile;

        /*
         * Important step here.  This safety check helps ensure that we don't
         * load an incomplete spider's items and make unnecessary deletes.
         */
        if (!$this->isComplete()) {
            throw new ItemsException("Cannot process items. Incomplete data.");
        }
    }

    /**
     * Initialize the file handler
     *
     * @param InputAdapter $file
     * @throws ItemsException
     */
    protected function _initFileHandler(InputAdapter $file)
    {
        $fileName = $file->getAbsoluteFileName();

        if (!is_file($fileName) || !is_readable($fileName)) {
            throw new ItemsException("Cannot read items.  Invalid or unreadable file: {$fileName}.");
        }

        $this->_fileHandler = @fopen($fileName, "r");

        if (!$this->_fileHandler) {
            throw new ItemsException("Error creating file handle for parsing.");
        }
    }

    /**
     * Get the next item in file
     *
     * Returns FALSE when there are no more items.
     *
     * @throws ItemsException
     * @return \Import\Translate\Item|bool
     */
    public function getNextItem()
    {
        $itemJson = $this->_readNextLine();

        // If we got the begin header, skip it and get the next line
        if ($itemJson == $this->_bof) {
            $itemJson = $this->_readNextLine();
        }

        // Did we get the EOF delimiter or an empty line?
        if ($this->_isEOF($itemJson) || $itemJson == "") {
            /*
             * IMPORTANT: If we reach the end of the file we must mark the file
             * as not having more items.
             */
            $this->_hasMoreItems = false;
            return false;
        }

        $itemArray = json_decode($itemJson, true);

        if (!json_last_error() == JSON_ERROR_NONE) {
            throw new ItemsException("Error Parsing Item JSON: " . $itemJson);
        }
        
        if (!isset($itemArray['data']) || !isset($itemArray['source'])) {
            throw new ItemsException("Missing required item data from spider.");
        }

        /*
         * Return a Item object
         *
         * Note that for spiders we expect to have a structured array with
         * "source" and "data" values.  "data" represents the actual scraped
         * data of the item.  "source" represents some meta-data about the item.
         */

        return new Item(
            $this->_inputAdapter,
            $itemArray['data'],
            array(
                'allData' => $itemArray
            )
        );

    }

    /**
     * Is this a complete file?
     *
     * This method is a little tricky.  What we have to do here is move the
     * file pointer to the end of the file and then read the last X number of
     * characters to see if it contains a closing tag.  The closing tag tells
     * us that the Spider completed running and all items should be accounted for
     * in the file.  When we finish the check, we move the file pointer back to
     * where it was previously so that we don't interrupt any other process that
     * might have been reading the file.
     *
     * @return bool
     */
    public function isComplete()
    {
        // Remember the initial cursor position
        $initialPosition = ftell($this->_fileHandler);

        /*
         * Calculate a location of the closing tag.  We add 5 extra characters
         * here to account for things like extra new line or EOF characters that
         * may have been added accidentally.  THIS IS NOT FOOL PROOF.  If there
         * are a bunch of extraneous characters that shouldn't be there we will
         * have a problem.
         */
        $cursor = (strlen($this->_eof)+5) * -1;

        /*
         * Move the cursor to the end of the file where we would expect the
         * close tag to be
         */
        fseek($this->_fileHandler, $cursor, SEEK_END);

        /*
         * Read the data at the end of the file and trim any potential trailing
         * line returns.
         */
        $data = trim(fread($this->_fileHandler, 100));

        // Move the cursor back to were it was originally
        fseek($this->_fileHandler, $initialPosition);

        // Does the end of the data we found contain the close tag?
        return $this->_isEOF($data);
    }

    /**
     * Has more items?
     *
     * Check to see if there are more items to get
     *
     * @return bool
     */
    public function hasMoreItems()
    {
        return $this->_hasMoreItems;
    }

    /**
     * Read the next line of the file
     * @return string
     */
    protected function _readNextLine()
    {
        return trim(fgets($this->_fileHandler, $this->_maxLineReadSize));
    }


    /**
     * Is end of file?
     *
     * Checks to see if a given string contains the end of file string.  Since
     * the string may contain some additional leading info that we don't care
     * about we verify the string by doing a substring check from the end of the
     * string.
     *
     * @param string $line
     * @return bool
     */
    protected function _isEOF($line)
    {
        return (
            substr(
                trim($line), // The string to check with white-space trimmed
                strlen($this->_eof) * -1 // The length of the EOF delimiter
            ) == $this->_eof // Does it equal the EOF delimiter?
        );
    }
}