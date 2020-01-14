<?php

namespace Import\Framework\ETL\Manage\Validate;

/**
 * Validation Exception
 *
 * This custom exception type can be passed an array of validation errors and
 * those errors can be accessed again by calling `getValidationErrors()`
 * @package Import\Framework\ETL\Manage\Validate
 */
class Exception extends \Import\Framework\ETL\Manage\Exception
{
    /**
     * @var array
     */
    protected $_validationErrors;

    public function __construct($message, $code, $validationErrors)
    {
        $this->_validationErrors = $validationErrors;

        parent::__construct($message, $code);
    }

    /**
     * Get validation errors
     * @return array
     */
    public function getValidationErrors()
    {
        return $this->_validationErrors;
    }
}