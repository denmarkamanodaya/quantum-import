<?php

namespace Import\Framework\ETL\Profile\Transform;


class Condition extends AbstractTransform
{
    /**
     * Supported/exposed methods
     * @var array
     */
    protected $_supportedMethods = array(
        'ifMatchRegex',
        'ifEqual'
    );

    /**
     * Get transformation methods
     *
     * Returns an array of supported transformation methods
     *
     * @return array
     */
    public function getMethods()
    {
        return $this->_supportedMethods;
    }

    /**
     * Check if value matches a regular expression
     * @param string $value
     * @param string $pattern
     * @param mixed $true
     * @param mixed $false
     * @return mixed
     */
    public function ifMatchRegex($value, $pattern, $true=true, $false=false)
    {
        $result = preg_match($pattern, $value);

        if ($result) {
            return $true;
        }

        return $false;
    }

    /**
     * Check if a value is equal to another
     * @param string $value
     * @param string $check
     * @param mixed $true
     * @param mixed $false
     * @return bool
     */
    public function ifEqual($value, $check, $true = true, $false = false)
    {
        return ($value == $check)
            ? $true
            : $false;
    }
}