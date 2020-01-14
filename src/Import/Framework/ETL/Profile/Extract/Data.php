<?php

namespace Import\Framework\ETL\Profile\Extract;


use Import\Framework\ETL\Profile\Extract\Data\Adapter\AbstractAdapter;

/**
 * High-level data extraction factory
 * @package ETL\Profile\Extract
 */
class Data
{
    /**
     * Supported formats
     * @var array
     */
    static protected $_formats = array(
        'json' => 'Omni',
        'array' => 'Omni'
    );

    /**
     * Data extraction factory by format name
     * @param string $format Format to get adapter of
     * @throws \Import\Framework\ETL\Profile\Extract\Exception
     * @return Data\Adapter\AbstractAdapter
     */
    static public function factoryByFormat($format)
    {
        $format = strtolower($format);

        if (array_key_exists($format, self::$_formats)) {
            $className = '\\Import\\Framework\\ETL\\Profile\\Extract\\Data\\Adapter\\' . self::$_formats[$format];
            $adapter = new $className($format);

            if (!$adapter instanceof AbstractAdapter) {
                throw new Exception("Invalid data extraction adapter: $className");
            }

            return $adapter;
        }

        throw new Exception("Cannot create extract adapter for unsupported format: " . $format);
    }
}