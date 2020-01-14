<?php

namespace Import\Framework\Database\Db\Adapter;

class MongoDB
{
    protected $_manager;

    protected $_databaseCollection;

    public function __construct(\MongoDB\Driver\Manager $Manager, $databaseCollection)
    {
        $this->_manager = $Manager;
        $this->_databaseCollection = $databaseCollection;
    }

    public function getConnection()
    {
        return $this->_manager;
    }

    public function update($doc=array(), $writeConcernOption=array())
    {
        $bulk = new \MongoDB\Driver\BulkWrite();
        $bulk->update($doc, $writeConcernOption);
        $writeConcern = new \MongoDB\Driver\WriteConcern(\MongoDB\Driver\WriteConcern::MAJORITY, 100);
        $result = $manager->executeBulkWrite($this->_databaseCollection, $bulk, $writeConcern);
        //return $result;
    }

    public function remove($doc=array(), $writeConcernOption=array())
    {
        $bulk = new \MongoDB\Driver\BulkWrite();
        $bulk->delete($doc, $writeConcernOption);
        $writeConcern = new \MongoDB\Driver\WriteConcern(\MongoDB\Driver\WriteConcern::MAJORITY, 100);
        $result = $this->_manager->executeBulkWrite($this->_databaseCollection, $bulk, $writeConcern);
        //return $result;
    }

    public function findOne($doc=array(), $writeConcernOption=array())
    {
        $query = new \MongoDB\Driver\Query($doc, $writeConcernOption);
        $result = $this->_manager->executeQuery($this->_databaseCollection, $query);

        return $result;
    }

    public function find($doc=array(), $writeConcernOption=array())
    {
        $query = new \MongoDB\Driver\Query($doc, $writeConcernOption);
        $result = $this->_manager->executeQuery($this->_databaseCollection, $query);
        return $result;
    }

    public function ensureIndex($doc=array(), $writeConcernOption=array())
    {
        $query = new \MongoDB\Driver\Query($doc, $writeConcernOption);
        $result = $this->_manager->executeQuery($this->_databaseCollection, $query);
        return $result;
    }

    public function count($doc=array(), $writeConcernOption=array())
    {
        $dbname = explode(".", $databaseCollection)[0];
        $collectionname = explode(".", $databaseCollection)[1];
        $cmd = new \MongoDB\Driver\Command( [ 'count' => $collectionname, 'query' => $doc ] );
        $r = $manager->executeCommand( $dbname, $cmd );
        return $r;
    }
}