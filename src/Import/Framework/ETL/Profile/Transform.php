<?php

namespace Import\Framework\ETL\Profile;
use Import\Framework\ETL\Profile\Transform\AbstractTransform;
use Import\Framework\ETL\Render;

/**
 * Data transformation collection
 *
 * This class contains a collection of static methods that can be used during
 * data transformations.  This allows us to apply PHP functions to data from
 * the config.  In the config, you would specify which of these methods you want
 * to run, and also what inputs you'd like to send.
 *
 * @package Map\Data
 */
class Transform extends AbstractProfile
{
    /**
     * ETL Profile type
     * @var string
     */
    protected $_profileType = "Transform";

    /**
     * @var string Database config setting
     */
    protected $_dbConfigName = 'etl|transform';

    /**
     * @var Extract
     */
    protected $_extract;

    /**
     * @var array Transformed values
     */
    protected $_transformedValues = array();

    /**
     * @var array Array of transformation utility classes
     */
    protected $_instances = array();

    /**
     * @var string Method delimiter
     */
    protected $_methodDelimiter = '|';

    /**
     * Set extract object
     *
     * @param Extract $Extract
     */
    public function setExtract(Extract $Extract)
    {
        $this->_extract = $Extract;
    }

    /**
     * Get transformed data
     * @return array
     * @throws Exception
     */
    public function getTransformedData()
    {
        if (!$this->_extract instanceof Extract) {
            throw new Exception("Cannot transform data until an Extract object is provided.");
        }

        /*
         * Get the extracted data
         *
         * Note that we set the extracted values to the default transformed
         * values.
         */
        $transformedData = $this->_extract->getExtractedData();

        foreach ($this->getDataTransformations() as $config) {

            $transformVar = $config['var'];
            $method       = $config['method'];
            $arguments    = $config['args'];

            $transformedData[$transformVar] = $this->runTransform(
                $method,
                $arguments,
                $transformedData
            );
        }

        return $transformedData;
    }


    /**
     * Run a transform method
     *
     * @param string $method
     * @param array $args
     * @param array $fields Current array of extracted/transformed values
     * @return mixed|null
     * @throws \Import\Framework\ETL\Exception
     * @throws \Import\Framework\ETL\Profile\Exception
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     * @throws \Import\Framework\ETL\Exception
     * @throws \Import\Framework\ETL\Profile\Exception
     */
    public function runTransform($method, array $args, array $fields)
    {
        $methodInfo = $this->_parseMethodName($method);
        $className  = $methodInfo['class'];
        $methodName = $methodInfo['method'];

        /*
         * Call transformer factory
         */
        $Transformer = $this->_getTransformClassInstance($className);

        /*
         * Check to see if the method requested is supported by the transformer
         */
        if (!$Transformer->isMethodSupported($methodName)) {
            // TODO: Log an error here
            return null;
        }

        /*
         * We need an array of arguments
         */
        if (!is_array($args)) {
            // TODO: Log an error here
            return null;
        }

        /*
         * Run each argument through Twig
         *
         * We do this because someone may have entered "{{name}}" as an argument
         * which really means "replace '{{name}}' with the current value for the
         * extracted "name" field.
         */
        foreach($args as &$arg) {
            if (is_array($arg)) {
                $arg = Render::getInstance()->renderArray($arg, $fields);
            }
            elseif (is_string($arg)) {
                $arg = Render::getInstance()->renderOne($arg, $fields);
            }
        }


        /*
         * Run the transformer
         */
        return $Transformer->callMethod($methodName, $args);
    }

    /**
     * Get the data transformations
     * @return array
     */
    public function getDataTransformations()
    {
        return $this->getMappingConfig('transformations');
    }

    /**
     * Parse method name
     *
     * This method examines a transform method name and looks for embedded class
     * names
     * @param string $name
     * @return array
     */
    protected function _parseMethodName($name)
    {
        if (strpos($name, $this->_methodDelimiter) === false) {
            return array(
                'class' => '\\Import\\Framework\\ETL\\Profile\\Transform\\Standard',
                'method' => $name
            );
        }

        $class  = substr($name, 0, strpos($name, $this->_methodDelimiter));
        $method = substr($name, strpos($name, $this->_methodDelimiter)+1);

        // Be nice, but don't hand-hold
        if (!class_exists($class)) {
            $class = '\\Import\\Framework\\ETL\\Profile\\Transform\\' . $class;
        }

        return array(
            'class'  => $class,
            'method' => $method
        );
    }

    /**
     * @param $className
     * @throws Exception
     * @return AbstractTransform
     */
    protected function _getTransformClassInstance($className)
    {
        if (isset($this->_instances[$className])) {
            return $this->_instances[$className];
        }

        if (!class_exists($className)) {
            throw new Exception("Cannot find transform class named '{$className}.");
        }

        $instance = new $className;

        if (!$instance instanceof AbstractTransform) {
            throw new Exception("Cannot load transform class instance.  Invalid class type.");
        }

        $this->_instances[$className] = $instance;
        return $this->_instances[$className];
    }


    /**
     * Run the profile in a one-off test mode
     * @deprecated
     * @param array $data
     * @return array
     */
    public function runSimulation($data)
    {
        return array("error" => "simulation not supported.");
    }
}