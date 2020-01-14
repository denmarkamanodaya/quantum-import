<?php
namespace Import\Framework\ETL\Db;

class Factory
{
    /**
     * @var Factory
     */
    protected static $instance;

    /**
     * Singleton constructor
     * @return Factory
     */
    public static function getInstance()
    {
        if (! self::$instance instanceof self) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    /**
     * Get connection
     * @param string $name
     * @return \MongoCollection
     * @throws \MongoConnectionException
     * @throws \Exception
     */
    public function get($name)
    {
        $server = getenv("INPUT_MONGO_SERVER");
        $client = new \MongoDB\Client(
            $server,
            [],
            [
                'typeMap' => [
                    'array' => 'array',
                    'document' => 'array',
                    'root' => 'array',
                ],
            ]
        );

    
        switch (str_replace("etl|", "", $name)) {
            case "profiles":
                $db = $client->ETL;
                $collection = $db->Mappings;
                break;

            case "extract":
                $db = $client->ETL;
                $collection = $db->Extract;
                break;

            case "transform":   
                $db = $client->ETL;
                $collection = $db->Transform;
                break;

            case "load":
                $db = $client->ETL;
                $collection = $db->Load;
                break;

            case "geospatial":
                $db = $client->geospatial;
                $collection = $db->postal_codes;
                break;

            default:
                throw new \RuntimeException("Invalid config name.");
        }

        
        return $collection;
    }
}