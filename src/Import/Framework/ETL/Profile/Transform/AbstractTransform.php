<?php

namespace Import\Framework\ETL\Profile\Transform;

abstract class AbstractTransform
{
    /**
     * Get transformation methods
     *
     * Returns an array of supported transformation methods
     *
     * @return array
     */
    abstract public function getMethods();

    /**
     * Is a method name supported by this class?
     * @param string $method
     * @return bool
     */
    public function isMethodSupported($method)
    {
        return in_array($method, $this->getMethods());
    }

    /**
     * Get transform methods meta data
     *
     * This method returns a structured array containing the method signatures
     * for each enabled transformation method.  The primary consumer of this
     * method would be a front end UI that needs to display information about
     * what can be passed in.  Note: use the custom @Param tag to document
     * transform arguments.
     *
     * @deprecated
     * @return array
     */
    public function getMetaData()
    {
        $docComments = array();
        foreach($this->getMethods() as $methodName) {
            $parsed = array(
                'name'        => $methodName,
                'value'       => $methodName,
                'args'        => array()
            );

            $docComments[] = $parsed;
        }

        return $docComments;
    }


    /**
     * @deprecated
     * @param $tag
     * @return array
     */
    protected function _extractMetaDataArgs($tag)
    {
        return array();
    }

    public function callMethod($methodName, $args)
    {
        return call_user_func_array(array($this, $methodName), $args);
    }

    /**
     * Convert Mongo-safe name/value array
     *
     * For many transformations we support the following structure for name
     * value pairs:
     * ```
     * {
     *      "name1" : "value1",
     *      "name2" : "value2"
     * }
     * ```
     * The above format provides a clean and concise data format, but Mongo does
     * not support some characters like ".", "$", and NULL (\0).  To allow these
     * values to be used, we support an alternate syntax of:
     * ```
     * [
     *      ["name1", "value1"],
     *      ["name2", "value2"]
     * ]
     * ```
     * @param $values
     * @return array
     * @throws \Import\Framework\ETL\Profile\Exception
     */
    protected function _convertMongoSafeNameValueArray($values)
    {
        if (!is_array($values)) {
            throw new \Import\Framework\ETL\Profile\Exception(
                "Cannot use transform array.  No array provided."
            );
        }

        // Simplistic detection of alternate syntax
        if (isset($values[0]) && is_array($values[0])) {
            $converted = array();  // Array of converted values

            // Loop over the array of ["name","value] arrays
            foreach ($values as $nameValue) {

                // Note that invalid entries are skipped
                if (is_array($nameValue) && count($nameValue) === 2) {

                    // First value is name
                    $name = array_shift($nameValue);

                    // Second value is the value
                    $converted[$name] = array_shift($nameValue);
                }
            }

            // Return the converted array
            return $converted;
        }

        // Just return the original array
        return $values;
    }
}