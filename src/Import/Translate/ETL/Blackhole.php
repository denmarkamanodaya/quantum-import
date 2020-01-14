<?php

namespace Import\Translate\ETL;

use Import\Translate\Item;

/**
 * Black hole ETL Profile Mapping
 *
 * This is a stub class that really doesn't do anything.  When an input adapter
 * does not want to support a source mapping, it should return this ETL Mapping.
 * All requests to the `run` method will return NULL rather than an ETL Result.
 *
 * @todo Create a complimentary black hole ETL result class in that codebase.
 * @package Import\Translate\ETL
 */
class Blackhole extends AbstractMapping
{

    /**
     * Load ETL mapping
     * @param string $key
     */
    public function loadMapping($key)
    {
        // This method is intentionally left blank.
        // This _is_ a back hole after all
    }

    /**
     * Run ETL mapping
     * @param Item $Item
     * @return null
     */
    public function run(Item $Item)
    {
        return null;
    }
}