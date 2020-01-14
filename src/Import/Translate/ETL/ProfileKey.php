<?php

namespace Import\Translate\ETL;

class ProfileKey extends AbstractMapping
{

    /**
     * Load ETL mapping
     * @param string $key
     */
    public function loadMapping($key)
    {
        $found = $this->_mapping->loadMappingById($key);

        /*
         * Check to see if we actually found a mapping
         */
        if (false === $found) {
            throw new \UnexpectedValueException(
                "Cannot find matching Source Mapping ETL Profile."
            );
        }
    }
}