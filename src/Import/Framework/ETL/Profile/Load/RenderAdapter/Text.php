<?php

namespace Import\Framework\ETL\Profile\Load\RenderAdapter;



/**
 * Text loader
 *
 * This is the most basic data loader.  It's essentially a wrapper for the Twig
 * rendering engine and returns the final result of the template to you.
 *
 * @package ETL\Profile\Load
 */
class Text extends AbstractRenderer
{
    /**
     * Render data as plain-text
     * @param array $data
     * @return string
     * @throws \Import\Framework\ETL\Exception
     */
    public function render(array $data)
    {
        return $this->_renderTemplate($data);
    }
}