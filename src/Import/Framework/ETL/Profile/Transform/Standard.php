<?php

namespace Import\Framework\ETL\Profile\Transform;

/**
 * Standard Transformations
 *
 * This class represents a common set of data transformations that have no
 * external dependencies.
 *
 * @package ETL\Profile\Transform
 */
class Standard extends AbstractTransform
{
    /**
     * @var array List of transformer methods
     */
    protected $_transformers = array(
        'date',
        'leftUntil',
        'value',
        'defaultValue',
        'stripHtmlTags',
        'cleanHtmlEntities',
        'crossReference',
        'replaceIn',
        'highAsciiHtmlEncode',
        'findWithRegex',
        'findFirst',
        'findLast',
        'stripNonPrintable',
        'regexReplace'
    );

    /**
     * Get methods
     *
     * @todo Use reflection and move this to the parent class
     * @return array
     */
    public function getMethods()
    {
        return $this->_transformers;
    }


    /**
     * Create a date
     *
     * Create a date string.  You can use any PHP date() format or use some
     * presets like "MM/DD/YYYY".
     *
     * @Param string Date The raw date to format
     * @Param string Format The format to use when formatting
     * @param string $format
     * @param string $value
     * @return string
     */
    public function date($value, $format="MM/DD/YYYY")
    {
        // Allow for some "nicer" date formats by converting them to PHP format
        $format = str_replace(
            array('MM', 'DD', 'YYYY'),
            array('m', 'd', 'Y'),
            $format
        );

        return date($format, strtotime($value));
    }

    /**
     * Left until
     *
     * Get the left side of a string until a given character is encountered.
     *
     * @Param string Haystack String to look at
     * @Param string Needle String to look for in the Value string
     * @param string $find
     * @param string $value
     * @return string
     */
    public function leftUntil($find, $value)
    {
        $position = strpos($find, $value);

        if (!$find || $position === false) {
            return $find;
        }

        return substr($find, 0, $position);
    }


    /**
     * Hard-code Value
     *
     * Set a value to whatever you provide.  This method can be used to populate
     * an empty value or even to concatenate multiple values together using the
     * "{{var1}} {{var2}}" syntax.
     *
     * @Param string Value Value to set
     * @param string|int|float $value
     * @return mixed
     */
    public function value($value)
    {
        return $value;
    }


    /**
     * Append text to end
     *
     * Appends the second string to the end of the first.
     *
     * @Param string Value to append to
     * @Param string String to be appended onto the first
     *
     * @param $value
     * @param $appendThis
     * @return string
     */
    public function appendText($value, $appendThis)
    {
        return $value . $appendThis;
    }


    /**
     * Default value
     *
     * Sets a default value if the value provided isn't set.
     *
     * @Param string Value Value to check
     * @Param string DefaultValue Default value to apply if Value isn't set
     *
     * @param string $sourceValue
     * @param string $defaultValue
     * @return string
     */
    public function defaultValue($sourceValue, $defaultValue)
    {
        return ($sourceValue) ? $sourceValue : $defaultValue;
    }


    /**
     * Strip HTML Tags
     *
     * Removes HTML tags from the provided value and also converts block-level
     * HTML elements into carriage returns for easier reading
     *
     * @Param string Value Value to strip
     * @param $sourceValue
     * @return string
     */
    public function stripHtmlTags($sourceValue)
    {
        // Convert closing block elements and <br>s to a new line
        $sourceValue = str_ireplace(
            array('</p>', '</div>', '</li>', '<br>','</br>'),
            "\n",
            $sourceValue
        );
        return trim(strip_tags($sourceValue));
    }

    /**
     * Clean HTML Entities
     *
     * Attempts to clean out HTML-escaped characters that might appear in the
     * content provided.  Helpful in ensuring HTML text is converted to plain
     * text more cleanly.
     *
     * @Param string Value Value to clean
     * @param $sourceValue
     * @return string
     */
    public function cleanHtmlEntities($sourceValue)
    {
        return html_entity_decode($sourceValue);
    }


    /**
     * Cross-Reference
     *
     * Perform a cross-referencing lookup.  Allows you to search for a source
     * value within a look up table and replace it with the corresponding value
     * in the table.
     *
     * @Param string Value Value to find
     * @Param table Table Cross-reference table
     *
     * @param string $sourceValue
     * @param array $lookupTable
     * @return null
     * @throws \Import\Framework\ETL\Profile\Exception
     * @throws \Import\Framework\ETL\Profile\Exception
     */
    public function crossReference($sourceValue, $lookupTable)
    {
        $lookupTable = $this->_convertMongoSafeNameValueArray($lookupTable);

        // Do the standard look up
        $sourceValue = trim($sourceValue);
        if (array_key_exists($sourceValue, $lookupTable)) {
            return $lookupTable[$sourceValue];
        }

        // If there is a "*" entry, treat that as a default value
        if (array_key_exists('*', $lookupTable)) {
            return $lookupTable['*'];
        }

        return $sourceValue;
    }

    /**
     * Find and Replace (Simple)
     *
     * Perform a simple find and replace on the Value.  You can specify a
     * series of replacements in one call, but bear in mind that they will
     * be evaluated in order.
     *
     * @Param string Value Value to perform replacements in
     * @Param table Table of values to replace
     *
     * @param string $sourceValue
     * @param array $replacements
     * @throws \Import\Framework\ETL\Profile\Exception
     * @return string
     */
    public function replaceIn($sourceValue, $replacements)
    {
        $replacements = $this->_convertMongoSafeNameValueArray($replacements);

        foreach ($replacements as $find => $replace) {
            $sourceValue = str_replace($find, $replace, $sourceValue);
        }

        return $sourceValue;
    }

    /**
     * HTML Encode High-ASCII Characters
     *
     * @Param string Value Value to encode
     *
     * @param string $sourceValue
     * @return mixed
     */
    public function highAsciiHtmlEncode($sourceValue)
    {
        $replacements = array(
            "•" => "&bull;",
            "·" => "&bull;",
            "“" => "&ldquo;",
            "”" => "&rdquo;",
            "‘" => "&lsquo;",
            "’" => "&rsquo;",
            "®" => "&reg;",
            "©" => "&copy;",
            "™" => "&trade;",
            "€" => "&euro;",
            "¥" => "&yen;",
            "¢" => "&cent;",
            "–" => "&ndash;",
            "—" => "&mdash;",
            "¶" => "&para;",
            "§" => "&sect;",
            "°" => "&deg;",
            "½" => "&frac12;",
            "¼" => "&frac14;",
            "¾" => "&frac34;",
            "⋮" => "&vellip;",
            "…" => "&hellip;",
            "†" => "&dagger;",
            "‡" => "&Dagger;",
            "¹" => "&sup1;",
            "²" => "&sup2;",
            "³" => "&sup3;"
        );

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $sourceValue
        );
    }

    /**
     * Find a Value Using a Regular Expression
     *
     * @param string $value Value to search in
     * @param string $regEx Regular expression to search with
     * @param bool|int $returnSubPattern Sub-pattern to return (optional)
     * @param string $default Default value if nothing found
     * @return string
     */
    public function findWithRegex($value, $regEx, $returnSubPattern=false, $default="")
    {
        $matches = array();

        $success = preg_match($regEx, $value, $matches);

        // No match
        if ($success !== 1 || count($matches) === 0) {
            return $default;
        }

        // No sub-pattern requested
        if (!is_int($returnSubPattern)) {
            return $matches[0];
        }

        // Is there a sub-pattern to return?
        if (array_key_exists($returnSubPattern, $matches)) {
            return $matches[$returnSubPattern];
        }

        // Return default value, nothing found
        return $default;
    }

    /**
     * Find the first value from lookup
     * @param string $value
     * @param array $lookFor
     * @return string|null
     */
    public function findFirst($value, $lookFor)
    {
        if (!is_array($lookFor)) {
            return null;
        }

        foreach($lookFor as $find) {
            if (strpos($value, $find) !== false) {
                return $find;
            }
        }

        return null;
    }

    /**
     * Find the last value from lookup
     * @param $value
     * @param $lookIn
     * @return string
     */
    public function findLast($value, $lookIn)
    {
        if (!is_array($lookIn)) {
            return null;
        }
        return $this->findFirst($value, array_reverse($lookIn));
    }

    /**
     * Strip non-printable characters
     *
     * @see http://utf8-chartable.de/
     * @param string $value
     * @return string
     */
    public function stripNonPrintable($value)
    {
        if (!is_string($value)) {
            return $value;
        }
        $value = mb_convert_encoding($value, 'UTF-8');
        return preg_replace('/[\x00-\x1F\x7F-\x9F]/um', '', $value);
    }

    /**
     * Regular expression replacement
     * @param string $value
     * @param string $pattern
     * @param string $replacement
     * @return string
     */
    public function regexReplace($value, $pattern, $replacement)
    {
        if (!is_string($value)) {
            return $value;
        }
        return preg_replace($pattern, $replacement, $value);
    }
}