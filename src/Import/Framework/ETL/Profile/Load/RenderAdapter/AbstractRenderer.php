<?php

namespace Import\Framework\ETL\Profile\Load\RenderAdapter;

use Import\Framework\ETL\Profile\Exception;
use Import\Framework\ETL\Render;

abstract class AbstractRenderer
{
    /**
     * Template to render
     * @var string
     */
    protected $_template;

    /**
     * Set the template
     * @param string $template
     * @throws Exception
     */
    public function setTemplate($template)
    {
        if (!is_string($template)) {
            throw new Exception("Cannot set template.  Provided value is not a string.");
        }

        $this->_template = $template;
    }


    public function getTemplate()
    {
        return $this->_template;
    }

    /**
     * Render the template with provided data
     * @param array $data
     * @return string
     */
    abstract public function render(array $data);


    /**
     * @param array $values
     * @return string
     * @throws \Import\Framework\ETL\Exception
     */
    protected function _renderTemplate(array $values)
    {
        return Render::getInstance()->renderOne($this->getTemplate(), $values);
    }
}