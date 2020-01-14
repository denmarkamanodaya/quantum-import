<?php

namespace Import\Framework\AI2\Model;


use Import\Framework\AI2\Db;
use Import\Framework\Database\Db\Adapter\Pdo;

abstract class AbstractModel implements ModelInterface
{
    /**
     * @var \Import\Framework\Db\Adapter\Pdo
     */
    protected $_db;

    protected $_id;

    protected $_dataCache;

    abstract public function update($values);

    abstract protected function _getData();

    /**
     * AbstractModel constructor.
     * @param $id
     * @throws Exception
     */
    public function __construct($id)
    {
        if (!is_numeric($id)) {
            throw new Exception("Cannot create model.  Invalid ID.");
        }

        $this->_id = (int) $id;

        $this->_db = self::_getDb();
    }

    static protected function _getDb()
    {
        return Db::getSqlConnection();
    }

    /**
     * @param array $columnAliasMap
     * @param array $aliasedValues
     * @param bool $requireAll
     * @return array
     * @throws Exception
     */
    static protected function _mapAliases($columnAliasMap, $aliasedValues, $requireAll=false)
    {
        /*
         * Loop over the mapping and build an insert array
         */
        $final = array();
        foreach ($columnAliasMap as $col => $alias) {
            if (!isset($aliasedValues[$alias]) && $requireAll) {
                throw new Exception("Cannot map data. Missing required value '{$alias}'.");
            }

            if (isset($aliasedValues[$alias])) {
                $final[$col] = $aliasedValues[$alias];
            }
        }

        return $final;
    }

    /**
     * Get source data
     * @return array
     */
    public function getData()
    {
        if (is_array($this->_dataCache)) {
            return $this->_dataCache;
        }

        $data = $this->_getData();

        if (is_array($data)) {

            $data = $this->_convertResultIdsToInt($data);

            $this->_dataCache = $data;
        }

        return $data;
    }


    protected function _convertResultIdsToInt($results)
    {
        if (!is_array($results)) {
            return $results;
        }

        foreach($results as $name => $value) {
            if (is_array($value)) {
                $results[$name] = $this->_convertResultIdsToInt($value);
            }
            else {
                if (preg_match('/^(enabled|id|[a-z]+Id)$/', $name)
                    && is_numeric($value)) {
                    $results[$name] = (int)$value;
                }
            }
        }

        return $results;
    }

    /**
     * Get a data value by name
     * @param string $name
     * @throws Exception
     * @return string
     */
    public function getDataValue($name)
    {
        $data = $this->getData();

        if (isset($data[$name])) {
            return $data[$name];
        }

        throw new Exception("Invalid data value name '{$name}'.");
    }

    public function exists()
    {
        $data = $this->getData();
        return (is_array($data) && count($data) > 0);
    }

    /**
     * Get the ID
     * @return int
     */
    public function getId()
    {
        return $this->_id;
    }

    /**
     * Convert column/value mappings
     * @param array $mapping
     * @return array
     */
    protected static function convertColumnMapping(array $mapping)
    {
        $updateSql = array();
        $updateBind = array();
        $insertColumns = array();
        $insertColumnParams = array();
        foreach ($mapping as $key => $value) {
            $bindKey = ":" . $key;
            $updateSql[] = "{$key} = {$bindKey}";
            $insertColumns[] = $key;
            $insertColumnParams[] = $bindKey;
            $updateBind[$bindKey] = $value;
        }

        return array(
            'columnMap' => implode(",\n", $updateSql),
            'bindArray' => $updateBind,
            'columnNames' => $insertColumns,
            'columnNameParams' => $insertColumnParams
        );
    }

    /**
     * Update a table similar to the old Zend Framework signature
     * @param string $table
     * @param array $mapping
     * @param string $whereColumn
     * @param string|int|float $whereValue
     * @param Pdo $db
     * @return int Number of rows affected
     */
    protected static function updateTable($table, array $mapping, $whereColumn, $whereValue, Pdo $db)
    {
        $sqlMapping = self::convertColumnMapping($mapping);

        $sql = <<<SQL
UPDATE {$table}
SET {$sqlMapping['columnMap']}
WHERE $whereColumn = :W_{$whereColumn}
SQL;

        $pdoStatement = $db->query(
            $sql,
            array_merge(
                $sqlMapping['bindArray'],
                array(":W_".$whereColumn => $whereValue)
            )
        );

        return $pdoStatement->rowCount();
    }

    protected static function insertIntoTable($table, array $mapping, Pdo $db)
    {
        $sqlMapping = self::convertColumnMapping($mapping);

        $columnNames = implode(", ", $sqlMapping['columnNames']);
        $columnParams = implode(", ", $sqlMapping['columnNameParams']);

        $sql = <<<SQL
INSERT INTO {$table} ({$columnNames}) VALUES ({$columnParams})
SQL;
        $pdoStatement = $db->query($sql, $sqlMapping['bindArray']);

        if (1 != $pdoStatement->rowCount()) {
            return false;
        }

        return $db->getConnection()->lastInsertId();
    }
}