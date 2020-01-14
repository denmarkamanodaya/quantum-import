<?php

namespace Import\Framework\Database;

use Import\Framework\Database\Db\Adapter\Pdo;
use Import\Framework\Database\Db\Adapter\MongoDB;

class Factory
{
    static protected $_connections = array();

    static public function getConnection($pdoDsn, $userName, $password)
    {
        $dnsKey = md5($pdoDsn);
        if (isset(self::$_connections[$dnsKey])) {
            return self::$_connections[$dnsKey];
        }
        
        $PdoConnection = new \PDO(
            $pdoDsn,
            $userName,
            $password,
            array(
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
            )
        );

        $Db = new Pdo($PdoConnection);

        self::$_connections[$dnsKey] = $Db;
        
        return self::$_connections[$dnsKey];
    }

    static public function getMongoDBConnection(\MongoDB\Driver\Manager $Manager, $databaseCollection)
    {        
        $Db = new MongoDB($Manager, $databaseCollection);

        return $Db;
    }
}