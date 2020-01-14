<?php

namespace Import\Input\Adapter;

use Import\App\Config;

/**
 *
 *
 * @package Import\Input\Adapter
 */
class Spider extends AbstractAdapter
{

    protected $_sourceFile;
    /**
     * @var array Cached user data
     */
    protected $_userInfo = array();

    /**
     * @var string File name
     */
    protected $_fileName;

    /**
     * @var string
     */
    protected $_batch;

    /**
     * @var string
     */
    protected $_sharePath;

    /**
     * @var array
     */
    protected $_originalRequest;

    protected function _setDefaultDependencies(array $configOptions = [])
    {
        if (!isset($configOptions['db'])) {
            $configOptions['db'] = \Import\Framework\Database\Db::getSqlConnection();
        }

        if (!isset($configOptions['sharePath'])) {
            $configOptions['sharePath'] = Config::get("SPIDER_SHARE_PATH");
        }

        return $configOptions;
    }

    /**
     * Set the spider share path
     * @param string $sharePath
     * @throws Exception
     */
    protected function _setOptionSharePath($sharePath)
    {
        if (!is_string($sharePath) || !is_dir($sharePath)) {
            throw new Exception("Invalid spider share path: '" . $sharePath . "'");
        }

        $this->_sharePath = $sharePath;
    }

    public function isValid()
    {
        // Make sure we have received a valid request
        if ( ! $this->isValidRequestData()) {
            throw new Exception("Cannot process spider data.  Invalid request.");
        }

        if ( ! $this->isValidSpiderFile()) {
            throw new Exception("Cannot process spider file. File not found.");
        }

        return true;
    }

    public function isValidSpiderFile()
    {
        // Does the file actually exist?
        return ( is_file($this->getAbsoluteFileName())
            && is_readable($this->getAbsoluteFileName())
        );
    }

    /**
     * Validate the data provided by the external user
     *
     * Note: This does NOT validate the item data, only that there isn't any
     *  obvious problem with the provided data.
     *
     * @return bool
     */
    public function isValidRequestData()
    {
        // Were we given a file to look for?
        return (bool) $this->_sourceFile->getFilename();
    }

    /**
     * Get absolute file name of uploaded file
     *
     * @return string
     * @throws Exception
     */
    public function getAbsoluteFileName()
    {
        return $this->_sharePath
            . DIRECTORY_SEPARATOR
            . $this->_sourceFile->getFilename();
    }

    /**
     * Get the original request data
     *
     * This is used to help facilitate object serialization.
     *
     * @return array;
     */
    public function getOriginalRequestData()
    {
        return [
            'file' => $this->_sourceFile->getFilename()
        ];
    }

    /**
     * Get logger instance
     * @return \Monolog\Logger
     */
    protected function _getLoggerComponent()
    {
        return "spider.input";
    }
}
