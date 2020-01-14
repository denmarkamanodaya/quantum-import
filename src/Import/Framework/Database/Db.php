<?php

namespace Import\Framework\Database;

class Db
{
    protected function __construct() 
    {}

    public static function getMongoConnection($name)
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

        switch ($name) {
            case "itemStorage":
                $db = $client->storage;
                $collection = $db->items;
                break;

            case "itemPurge":
                $db = $client->storage;
                $collection = $db->itemPurge;
                break;

            default:
                throw new \InvalidArgumentException(
                    "The mongo connection type '{$name}' is not recognized."
                );
        }

        return $collection;
    }

    public static function getSqlConnection()
    {
        return \Import\Framework\Database\Factory::getConnection(
            getenv('IMPORT_SQL_DSN'),
            getenv('IMPORT_SQL_USER'),
            getenv('IMPORT_SQL_PASS')
        );
    }
}