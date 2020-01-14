<?php

namespace Import\Framework\ETL\Profile\Load;


class Exception extends \Import\Framework\ETL\Profile\Exception
{
    /**
     * @var mixed Debugging data
     */
    protected $_data;

    /**
     * Set debugging data
     * @param mixed $data
     */
    public function setData($data)
    {
        $this->_data = $data;
    }

    /**
     * Get debugging data
     * @return mixed
     */
    public function getData()
    {
        return $this->_data;
    }
}