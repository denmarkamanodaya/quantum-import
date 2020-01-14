<?php

namespace Import\Framework\AI2\Model\Source;

/**
 * Notify
 *
 * In the AI2 system, notify recipients are called after a file (or batch of
 * items) has been processed.  Generally, this data point is just a Gearman
 * worker name.
 *
 * @package Import\Framework\AI2\Model\Source
 */
class Notify extends \Import\Framework\AI2\Model\AbstractModel
{
    /**
     * @var array Mapping of database column names to cleaner aliases
     */
    static protected $_columnAliasMap = array(
        'input_source_id'  => 'sourceId',
        'recipient_name' => 'name'
    );

    /**
     * Update an existing notifier
     *
     * Note: You cannot change the source_id for an existing notifier.  You will
     * need to delete it and create a new one.
     *
     * @param array $values
     * @return bool
     * @throws Exception
     * @throws \Import\Framework\AI2\Model\Exception
     */
    public function update($values)
    {
        if (!is_array($values) || count($values) === 0) {
            throw new Exception("Cannot update notify recipient.  Invalid values.");
        }

        // You cannot change the sourceId
        if (isset($values['sourceId'])) {
            unset($values['sourceId']);
        }

        $update = self::_mapAliases(self::$_columnAliasMap, $values);

        $numAffected = self::updateTable(
            'input_source_notify',
            $update,
            'input_source_notify_id',
            $this->_id,
            $this->_db
        );

        return ($numAffected === 1);
    }

    /**
     * Delete a notify recipient
     * @return bool
     */
    public function delete()
    {
        $numAffected = $this->_db->query(
            'DELETE FROM input_source_notify WHERE input_source_notify_id = :id',
            array(
                ':id' => $this->_id
            )
        );

        return ($numAffected === 1);
    }

    /**
     * Get all data
     * @return array
     */
    protected function _getData()
    {
        $sql = "
            SELECT
              input_source_notify_id    AS 'id',
              input_source_id           AS 'sourceId',
              recipient_name          AS 'name',
              created_by              AS 'createdBy',
              created_date            AS 'createdDate',
              updated_by              AS 'updatedBy',
              updated_date            AS 'updatedDate'
            FROM input_source_notify
            WHERE
              input_source_notify_id = :id";

        $data = $this->_db->fetchRow($sql, array(":id" => $this->_id));

        return $this->_convertResultIdsToInt($data);
    }

    /**
     * Factory - Create new notify recipient
     * @param array $values
     * @return Notify
     * @throws Exception
     * @throws \Import\Framework\AI2\Model\Exception
     */
    static public function factoryNew($values)
    {
        if (!is_array($values)) {
            throw new Exception("Cannot create notify recipient.  Invalid data.");
        }

        $db = self::_getDb();

        $insert = self::_mapAliases(self::$_columnAliasMap, $values);

        $id = self::insertIntoTable(
            'input_source_notify',
            $insert,
            $db
        );

        return new self($id);
    }
}