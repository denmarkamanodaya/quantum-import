<?php

namespace Import\Framework\ETL\Profile;

/**
 * Validation Class
 *
 * Since this class is really just a collection of validation methods without
 * any real context, it has been implemented as a Singleton.
 *
 * VERY IMPORTANT: Please note that every validation method (with the exception
 * of `isRequired` will return TRUE if the value we're checking is NULL.  This
 * is to allow optional values to not trigger validation errors.  You should
 * always use `isRequired` to enforce that a value cannot be missing.
 *
 * @package ETL\Profile
 */
class Validate
{
    /**
     * @var Validate
     */
    static protected $_instance;

    /**
     * Protected constructor
     *
     * Use `getInstance()`
     */
    protected function __construct()
    {}

    /**
     * Singleton constructor
     * @return Validate
     */
    static public function getInstance()
    {
        if (!self::$_instance instanceof self) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }


    /**
     * Enforce required values
     *
     * Returns TRUE for "truthy" values.
     *
     * @param $value
     * @return bool
     */
    public function isRequired($value)
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        return (bool) $value;
    }

    /**
     * Is numeric value?
     * @param mixed $value
     * @return bool
     */
    public function isNumeric($value)
    {
        // Allow for an undefined value to be optional
        if ($value === null) {
            return true;
        }

        return is_numeric($value);
    }

    /**
     * Is not numeric value?
     * @param mixed $value
     * @return bool
     */
    public function isNotNumeric($value)
    {
        // Allow for an undefined value to be optional
        if ($value === null) {
            return true;
        }

        return !is_numeric($value);
    }

    /**
     * Regular expression check
     * @param mixed $value
     * @param string $regex
     * @return bool
     */
    public function matchesRegex($value, $regex)
    {
        // Allow for an undefined value to be optional
        if ($value === null) {
            return true;
        }

        return !!preg_match($regex, (string) $value);
    }

    /**
     * Glob-style pattern check
     *
     * This method takes a "simple" pattern that allows for "*" as a wild card.
     * Internally, we take that simpler pattern and create a regex from it.
     * @param mixed $value
     * @param string $pattern
     * @return bool
     */
    public function matchesPattern($value, $pattern)
    {
        // Allow for an undefined value to be optional
        if ($value === null) {
            return true;
        }

        // @see http://php.net/manual/en/regexp.reference.delimiters.php
        $delimiter = '/';

        // @see http://php.net/manual/en/reference.pcre.pattern.modifiers.php
        $modifier  = 'm';  // Multi-line

        // Replace glob-style "*" wildcard with regex .+
        $regex = str_replace('*', '.+', $pattern);

        // Escape delimiter within pattern itself
        $regex = str_replace($delimiter, '\\' . $delimiter, $regex);

        // Bookmark for start-to-finish matching
        $regex = '^' . $regex . '$';

        // Wrap with delimiter and modifier
        $regex = $delimiter . $regex . $delimiter . $modifier;

        return $this->matchesRegex($value, $regex);
    }

    /**
     * JSON string check
     *
     * Expects a string that is a JSON-decodable value. Right now the surest way
     * to confirm a string is valid is to actually decode it and verify the
     * decoding process does not fail.  There may be a slicker way.
     *
     * @param $value
     * @return bool
     */
    public function isJson($value)
    {
        // Allow for an undefined value to be optional
        if ($value === null) {
            return true;
        }

        // Before we even try to decode, we must have a string
        if (!is_string($value)) {
            return false;
        }
        $json = @json_decode($value, true);  // Decode as associative array
        return (is_array($json) && json_last_error() === JSON_ERROR_NONE);
    }

    /**
     * Is One Of
     *
     * Verifies that a given value is one of an accepted list of values.
     *
     * @param string $value
     * @param array $acceptedValues
     * @return bool
     */
    public function isOneOf($value, $acceptedValues)
    {
        // Allow for an undefined value to be optional
        if ($value === null) {
            return true;
        }

        return in_array($value, $acceptedValues);
    }

    /**
     * Is a Valid URL
     *
     * @param string $value
     * @return bool
     */
    public function isUrl($value)
    {
        // Allow for an undefined value to be optional
        if ($value === null) {
            return true;
        }

        /*
         * Here we use !! to quickly cast the result to a boolean
         */
        return !!filter_var($value, FILTER_VALIDATE_URL);
    }

    /**
     * Minimum character length validation
     *
     * @param string $value
     * @param int $minLength
     * @return bool
     */
    public function minCharLength($value, $minLength)
    {
        // Allow for an undefined value to be optional
        if ($value === null) {
            return true;
        }

        if (!is_scalar($value)) {
            return false;
        }

        return strlen( (string) $value) >= (int) $minLength;
    }

    /**
     * Maximum character length validation
     *
     * @param string $value
     * @param int $maxLength
     * @return bool
     */
    public function maxCharLength($value, $maxLength)
    {
        // Allow for an undefined value to be optional
        if ($value === null) {
            return true;
        }

        if (!is_scalar($value)) {
            return false;
        }

        return strlen( (string) $value) <= (int) $maxLength;
    }

    /**
     * Rest verify
     *
     * For this validation option you can provide a custom URI with `{value}`
     * wildcards placed within it.  This method will replace those wildcards
     * with the actual value we're testing and check to see if that URI returns
     * a 200 response code.  If it does, it passes validation.
     *
     * @param $value
     * @param $uri
     * @return bool
     */
    public function restVerify($value, $uri)
    {
        $uri = str_replace('{value}', urlencode($value), $uri);

        $h = curl_init($uri);
        curl_setopt($h, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($h, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($h, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($h, CURLOPT_HEADER, true);

        $response = curl_exec($h);

        $header_size = curl_getinfo($h, CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $header_size);

        curl_close($h);

        // Do the headers contain a 200 status?
        if (preg_match('/^(HTTP\/\d+(\.\d+)*|Status\:) 200/m', $header)) {
            return true;
        }

        return false;
    }
}