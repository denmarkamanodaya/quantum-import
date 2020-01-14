<?php

namespace Import\Framework\ETL\Profile;

use Import\Framework\ETL\Profile\Transform\AbstractTransform;
//use Import\Framework\ETL\Provider\FieldSource\TemplateAPI;
use Import\Framework\ETL\Profile\Load\RenderAdapter\AbstractRenderer;

class Load extends AbstractProfile
{
    const FIELD_SOURCE_MANUAL       = 'manual';
    //const FIELD_SOURCE_TEMPLATE_API = 'item_post_template';

    /**
     * ETL Profile type
     * @var string
     */
    protected $_profileType = "Load";

    /**
     * @var string Database config setting
     */
    protected $_dbConfigName = 'etl|load';

    /**
     * @var AbstractTransform
     */
    protected $_transform;

    /**
     * @var array Array of validation errors during last load
     */
    protected $_validationErrors = array();

    /**
     * @var array Flat array of fields used in the render
     */
    protected $_renderFields = array();

    /**
     * Set the transform object to use
     * @param Transform $Transform
     */
    public function setTransform(Transform $Transform)
    {
        $this->_transform = $Transform;
    }

    /**
     * Get loaded data
     *
     * Run the load and validation
     *
     * @return string
     * @throws Exception
     */
    public function getLoadedData()
    {
        if (!$this->_transform instanceof Transform) {
            throw new Exception("Cannot get loaded data until transformation object is set.");
        }

        $transformedData = $this->_transform->getTransformedData();
        $this->_renderFields = $this->_mergeAndValidateTransformedData($transformedData);

        $template        = $this->getTemplate();

        $Loader = $this->_getLoadClassInstance();
        $Loader->setTemplate($template);
        return $Loader->render($this->_renderFields);
    }

    /**
     * Get the template string to load data into
     * @return array
     */
    public function getTemplate()
    {
        return $this->getMappingConfig('template');
    }

    /**
     * Get load mapping "type"
     *
     * @return string
     */
    public function getType()
    {
        if ($this->getVersion() >= 2) {
            $type =  $this->getMappingConfig('format');
        }
        else {
            $type = $this->getMappingConfig('type');
        }

        return ucfirst(strtolower($type));
    }

    /**
     * Get the loader class
     * @return AbstractRenderer
     * @throws Exception
     */
    protected function _getLoadClassInstance()
    {
        $className =ucfirst( $this->getType());

        if (!class_exists($className)) {
            $className = '\\Import\\Framework\\ETL\\Profile\\Load\\RenderAdapter\\' . $className;

            if (!class_exists($className)) {
                throw new Exception("Cannot find Load type '{$className}'.");
            }
        }

        $Loader = new $className;

        if (!$Loader instanceof AbstractRenderer) {
            throw new Exception("Cannot load ETL Load class. Invalid Loader.");
        }

        return $Loader;
    }

    /**
     * Get array of validation errors from last render
     * @return array
     */
    public function getLastValidationErrors()
    {
        return $this->_validationErrors;
    }

    /**
     * Get a flat array of fields used in the render
     *
     * This gives you access to the raw data used in the final result without
     * having to traverse the output format which could be JSON, XML, array, and
     * so on.
     *
     * @return array
     */
    public function getLastRenderFields()
    {
        return $this->_renderFields;
    }


    /**
     * Merge and validate transformed data
     *
     * Accepts the transformed data and merges it into the output filed list.
     * Any configured validation methods are also ran against the data.  It's
     * important to note that failure to validate does NOT throw exceptions or
     * otherwise halt processing.  Validation errors are collected and
     * retrievable via the getLastValidationErrors() method.
     *
     * @param array $transformData
     * @return array
     * @throws Exception
     */
    protected function _mergeAndValidateTransformedData(array $transformData)
    {
        $config       = $this->getFields();
        $renderFields = array();

        $this->_validationErrors = array();

        if (!is_array($config)) {
            throw new Exception("Error merging transformed data into output.  No output fields defined.");
        }

        foreach ($config as $field) {
            $name = $field['name'];
            $value = (array_key_exists($name, $transformData))
                ? $transformData[$name]
                : null;

            /*
             * Validate field
             */
            if (isset($field['validation']) && is_array($field['validation'])) {
                $fieldValidationErrors = $this->_validateField(
                    $name,
                    $value,
                    $field['validation']
                );

                if (count($fieldValidationErrors)) {
                    $this->_validationErrors = array_merge(
                        $this->_validationErrors,
                        $fieldValidationErrors
                    );
                }
            }

            $renderFields[$name] = $value;
        }

        return $renderFields;
    }

    /**
     * Validate a field
     *
     * @param string $name
     * @param string|null $value
     * @param array $validationArray
     * @return array
     */
    protected function _validateField($name, $value, array $validationArray)
    {
        $errors = array();
        $ValidationObject = Validate::getInstance();

        foreach($validationArray as $validation) {

            // Ensure the validation config has a method name defined
            if (!isset($validation['method'])) {
                $errors[] = array(
                    'message' => "Fatal Validation Error: Malformed configuration object. Missing validation method.",
                    'name'    => $name,
                    'method'  => 'system.invalidMethod',
                    'value'   => $value
                );
                continue;
            }

            // The PHP understood validation method name (e.g.: "isRequired")
            $validationMethod = $validation['method'];

            /*
             * Create a human-readable version of the validation method by
             * converting the camel case method name to words.
             */
            $validationName   = $this->unCamelCase($validationMethod);

            // Verify that the validation method exists and can be called
            if (!method_exists($ValidationObject, $validationMethod)) {
                $errors[] = array(
                    'message' => "Fatal Error: Invalid validation method specified.  Method '{$validationMethod}' does not exist.",
                    'name'    => $name,
                    'method'  => 'system.methodDoesNotExist',
                    'value'   => $value
                );
                continue;
            }

            /*
             * Compile an array of supplementary arguments to pass to method. If
             * the method is a regular expression check for example, we would
             * expect the pattern to match against to be defined in these
             * arguments.
             *
             * It's also possible that there are no arguments.  For example,
             * "isRequired" wouldn't need an argument.
             */
            $validationArguments = (isset($validation['args']) && is_array($validation['args']))
                ? $validation['args']
                : array();

            /*
             * Run the actual validation
             */
            $validationResult = call_user_func_array(
                array(
                    $ValidationObject,
                    $validationMethod
                ),
                array_merge(
                    array($value),  // The transformed value is always first
                    $validationArguments // Other args. merged after in order
                )
            );

            if (true !== $validationResult) {
                $errors[] = array(
                    'message' => "Field '{$name}' failed validation check for '{$validationName}'",
                    'name'    => $name,
                    'method'  => $validationName,
                    'value'   => $value
                );
            }
        }

        return $errors;
    }

    /**
     * Un-CamelCase a string
     *
     * Converts things like "isRequired" to "Is Required"
     *
     * @param string $camel
     * @param string $splitter
     * @return string
     */
    public function unCamelCase($camel, $splitter = " ") {
        $camel=preg_replace('/(?!^)[[:upper:]][[:lower:]]/', '$0', preg_replace('/(?!^)[[:upper:]]+/', $splitter.'$0', $camel));
        return ucwords(strtolower($camel));
    }


    /**
     * Get version number
     *
     * Returns a floating point number representation of this profile's version
     * number.  If there is not version number defined by the ETL profile, the
     * version defaults to 1.0.
     *
     * @return float
     */
    public function getVersion()
    {
        $version = parent::getVersion();

        if ($version !== 1.0) {
            return $version;
        }

        /*
         * Version 2+ Requires that we have a field source
         */
        if ($this->getMappingConfig('fieldsSource')) {
            return 2.0;
        }

        return $version;
    }

    /**
     * Get fields
     *
     * Retrieve the fields to validate.  This method allows us to toggle between
     * manually configured fields and fields defined by an outside source.
     *
     * @throws Load\Exception
     * @throws Load\Exception
     * @throws Load\Exception
     * @throws Load\Exception
     */
    public function getFields()
    {
        if ($this->getFieldSourceType() == self::FIELD_SOURCE_MANUAL) {
            return $this->getMappingConfig('fields');
        }

        return $this->getMappingConfig('fields');
    }


    public function getFieldSourceType()
    {
        // For old ETL versions just use the 'fields' setting
        if ($this->getVersion() < 2) {
            return self::FIELD_SOURCE_MANUAL;
        }

        /*
         * Version 2+ Requires that we have a field source
         */
        $fieldSource = $this->getMappingConfig('fieldsSource');
        if ($fieldSource === null || !isset($fieldSource['type'])) {
            throw new Load\Exception("Error loading field source.  No field source type defined.");
        }

        switch ($fieldSource['type']) {
            case self::FIELD_SOURCE_MANUAL:
                return self::FIELD_SOURCE_MANUAL;

            default:
                throw new Load\Exception("Invalid field source type: {$fieldSource['type']}.");
        }
    }


    public function getFieldSourceId()
    {
        if ($this->getFieldSourceType() == self::FIELD_SOURCE_MANUAL) {
            return null;
        }

        $fieldSource = $this->getMappingConfig('fieldsSource');

        if (!isset($fieldSource['id'])) {
            return null;
        }

        return $fieldSource['id'];
    }
}