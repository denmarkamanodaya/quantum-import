<?php

namespace Import\Framework\ETL\Profile\Load\RenderAdapter;

use Import\Framework\ETL\Profile\Load\Exception\RenderException;

/**
 * Struct/Array Renderer
 *
 * This renderer works just like the JSON renderer except that the final JSON
 * result is converted to an array rather than being returned as a JSON string.
 *
 * To use this rendering type you must provide your template in a valid JSON
 * format.
 *
 * @package ETL\Profile\Load\RenderAdapter
 */
class Struct extends Json
{

    /**
     * Render the template with provided data
     *
     * Note that we are piggy-backing on the validation logic found in the JSON
     * renderer for merging in template values and validating the result.
     *
     * @param array $data
     * @return string
     * @throws RenderException
     * @throws RenderException
     */
    public function render(array $data)
    {
        $validJsonString = parent::render($data);
        return json_decode($validJsonString, true);
    }
}