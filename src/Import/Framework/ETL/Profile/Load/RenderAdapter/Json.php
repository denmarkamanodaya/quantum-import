<?php

namespace Import\Framework\ETL\Profile\Load\RenderAdapter;

use Import\Framework\ETL\Profile\Load\Exception\RenderException;
use Import\Framework\ETL\Profile\Validate;

/**
 * JSON data loader
 *
 * At present this isn't a very compelling class we do allow you to provide an
 * array instead of a string to setTemplate() but other than that, this is just
 * a text loader.
 *
 * @package ETL\Profile\Load
 */
class Json extends AbstractRenderer
{
    /**
     * Render the template with provided data
     * @param array $data
     * @return string
     * @throws RenderException
     * @throws \Import\Framework\ETL\Exception
     * @throws \Import\Framework\ETL\Exception
     */
    public function render(array $data)
    {
        $renderedData = $this->_renderTemplate($data);

        /*
         * Return the rendered string only if it's a valid JSON document
         */
        if ($this->_isValidJsonDocument($renderedData)) {
            return $renderedData;
        }


        echo $renderedData;

        /*
         * The rendered string is NOT a valid JSON document, so we build a
         * custom exception and attach the rendered value to the exception so we
         * can access it in the error handling code.
         */
        $Exception = new RenderException(
            "Error creating valid JSON document.",
            500
        );
        $Exception->setData($renderedData);

        throw $Exception;
    }


    /**
     * Validate a string as a valid JSON document.
     * @param string $jsonString
     * @return bool
     */
    protected function _isValidJsonDocument($jsonString)
    {
        return Validate::getInstance()->isJson($jsonString);
    }
}