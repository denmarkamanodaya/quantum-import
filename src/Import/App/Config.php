<?php
namespace Import\App;

use Dotenv\Dotenv;

/**
 * Configuration
 *
 * This is hopefully the last iteration of a configuration object for this
 * application. It is intended to read environment files named `.env` in the
 * root of the application if set.  If the runtime environment already has the
 * needed environment variables set, we con't bot
 * @package Import\App
 */
class Config
{
    protected $requiredEnv = array(
        // ETL Module
        'INPUT_MONGO_SERVER',
        'IMPORT_SQL_DSN',
        'IMPORT_SQL_USER',
        'IMPORT_SQL_PASS',
        'SPIDER_SHARE_PATH',
        'INPUT_LOGGING',
        'INPUT_LOGGING_VERBOSE',
        'CACHE_MASTER',
        'GEARMAN_SERVER'
    );

    public function __construct()
    {
        $this->loadEnvFile();
    }

    protected function loadEnvFile()
    {
        // Note: The Dotenv will not override any existing variables by default
        if (is_readable(__DIR__ . '/../../../.env')) {
            $dotEnv = new Dotenv(__DIR__ . '/../../../');
            $dotEnv->load();
            $dotEnv->required($this->requiredEnv);
        }
    }

    public static function get($name, $default = null)
    {
        $value = getenv($name);
        return ($value === false) ? $default : $value;
    }
}