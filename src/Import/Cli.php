<?php
namespace Import;

use Import\App\Config;

/**
 * Command-line bootstrap
 * @package Import
 */
class Cli
{
    /**
     * Initialize the application
     * @throws \Config\Exception
     */
    static public function init()
    {        
        new Config();
    }
}
