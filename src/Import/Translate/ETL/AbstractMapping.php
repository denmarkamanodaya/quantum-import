<?php

namespace Import\Translate\ETL;

use Import\Framework\ETL\Profile as EtlProfile;
use Import\Translate\Item;


abstract class AbstractMapping
{
    /**
     * @var EtlProfile
     */
    protected $_mapping;

    /**
     * Source Mapping constructor
     * @param string $key
     */
    public function __construct($key)
    {
        $this->_mapping = new EtlProfile();

        $this->loadMapping($key);
    }

    /**
     * Load ETL mapping
     * @param string $key
     */
    abstract public function loadMapping($key);

    /**
     * Get the loaded mapping
     * @return EtlProfile
     */
    public function getMapping()
    {
        return $this->_mapping;
    }

    /**
     * Run ETL mapping
     * @param Item $Item
     * @return EtlProfile\Result
     */
    public function run(Item $Item)
    {
        $this->_mapping->setSourceData($Item->getAllData());

        $this->_mapping->run();

        return $this->_mapping->getResult();
    }
}