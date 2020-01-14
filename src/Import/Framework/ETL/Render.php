<?php

namespace Import\Framework\ETL;

class Render
{
    static protected $_instance;

    /**
     * @var \Twig_Environment  Twig templating object
     */
    protected $_twig;


    /**
     * Protected constructor
     *
     * Use getInstance() instead
     */
    protected function __construct()
    {
        $this->_twig = new \Twig_Environment(
            new \Twig_Loader_String()
        );

        /*
         * JSON decode custom filter
         */
        $this->_twig->addFilter(new \Twig_SimpleFilter('json_decode', function ($value) {
            return json_decode($value, true);
        }));

        $this->_twig->addExtension(
            new \Twig_Extension_Escaper(false)
        );
    }

    /**
     * Singleton constructor
     * @return Render
     */
    static public function getInstance()
    {
        if (!self::$_instance instanceof self) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Render a value using Twig
     *
     * This method allows us to use Twig template syntax in our mapping
     * transformations.  This essentially cuts through all of the layers of
     * complexity surounding using Twig as a templating engine and allows us to
     * tap into Twig's core templating features on raw values that we pass in
     * here.
     *
     * $value is the template string you want Twig to parse.  This can contain
     * any thing that you would expect to see in a Twig template.
     *
     * $vars is an array of name/value pairs that Twig can use while processing
     * the template you provide.
     *
     * For example: if $value == "{foo} is at the {bar}" and $vars ==
     * array("foo"=>"DJ", "bar"=>"beach"), then the result of this call would be
     * "DJ is at the beach".
     *
     * @param string $value Template value
     * @param array $vars Array of values to be used in substitutions
     * @return string
     * @throws Exception
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     * @throws Exception
     */
    public function renderOne($value, array $vars)
    {
        if (!is_string($value)) {
            throw new Exception("Cannot use array as template.  String expected.");
        }

        return $this->_twig->render($value, $vars);
    }

    /**
     * Render an array using Twig
     *
     * @param array $values An array of strings to run through the templates
     * @param array $vars Array of values that can be used by Twig
     * @return array|null|string
     * @throws Exception
     * @throws Exception
     * @throws Exception
     */
    public function renderArray($values, $vars)
    {
        // If we're not given an array, pass it to renderOne()
        if (!is_array($values)) {
            return $this->renderOne($values, $vars);
        }

        /*
         * This was originally intended to support more complex data structures
         * but, upon further reflection, supporting those structures leads to a
         * greatly increased degree of complexity in the code.
         */
        $templateArray = $this->_replaceArrayPlaceholders($values, $vars);

        foreach ($templateArray as &$value) {
            // Recursive call...
            if (is_array($value)) {
                $value = $this->renderArray($value, $vars);
            }
            // Base case
            elseif (is_string($value)) {
                $value = $this->renderOne($value, $vars);
            }
        }

        return $templateArray;
    }

    /**
     * Replace arrays
     *
     * Replaces arrays referenced in the subject with the appropriate values.
     *
     * @deprecated
     * @param $templateArray
     * @param $vars
     * @return array|null
     */
    protected function _replaceArrayPlaceholders($templateArray, $vars)
    {
        if (!is_array($templateArray)) {
            // TODO: Add logging here
            return null;
        }

        foreach ($templateArray as &$value) {
            if (is_array($value)) {
                $value = $this->_replaceArrayPlaceholders($value, $vars);
            }
            else {
                $trimmedVar = trim($value, '{}');
                if (array_key_exists($trimmedVar, $vars)) {
                    $value = $vars[$trimmedVar];
                }
            }
        }

        return $templateArray;
    }
}