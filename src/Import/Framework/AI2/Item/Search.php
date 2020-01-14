<?php


namespace Import\Framework\AI2\Item;


class Search
{
    /**
     * @var \MongoCollection
     */
    protected $db;

    public function __construct(\MongoDB\Collection $Db)
    {
        $this->db = $Db;
    }


    /**
     * Search a file for a particular GUID
     *
     * @param string $uniqueId
     * @param int $fileId
     * @return string|null
     */
    public function findGuidInFileByUniqueId($uniqueId, $fileId)
    {
        $record = $this->db->findOne(
            array(
                "source.fileId" => (int) $fileId,
                "item.uniqueId" => $uniqueId
            ),
            array(
                "internal.guid" => true
            )
        );

        if (is_array($record) && isset($record['internal']['guid'])) {
            return $record['internal']['guid'];
        }

        return null;
    }


    /**
     * Get a single item by it's GUID
     *
     * @param string $guid
     * @return array|bool $data
     * @throws \MongoConnectionException
     * @throws \MongoCursorException
     * @throws \MongoCursorTimeoutException
     */
    public function find($guid)
    {
        $options = array(
            "sort"  => array("_id" => -1),
            "limit" => 1
        );
        $query = $this->db->find(
            $this->getGuidQuery($guid),
            $options
        );

        $data = $query->toArray()[0];

        if (is_array($data)) {
            return $data;
        }

        return false;
    }


    /**
     * Get GUID query
     *
     * This accepts a GUID string and then returns an array than can be used as
     * a MongoDB criteria.
     *
     * @todo Add support for versions when needed
     * @param string$guid
     * @return array
     */
    protected function getGuidQuery($guid)
    {
        // Allow for bulk queries
        if (is_array($guid)) {
            $guid = array('$in' => array_values($guid));
        }

        return array(
            "internal.guid" => $guid
        );
    }



    /**
     * GUID exists in database?
     *
     * @param string $guid
     * @return bool
     */
    public function guidExists($guid)
    {
        return $this->db->count(
            $this->getGuidQuery($guid)
        );
    }


    /**
     * Get GUID meta-data
     *
     * This method returns a cursor to the results so that you can run count
     * operations.
     *
     * @param string $guid
     * @return \MongoCursor
     */
    public function getMetaDataByGuid($guid)
    {
        return $this->db->find(
            $this->getGuidQuery($guid),
            array(
                'source.id'            => 1,
                'source.file'          => 1,
                'internal.rawDataHash' => 1,
                'item.uniqueId'         => 1
            )
        );
    }


    /**
     * Find a item by GUID
     *
     * This method returns a Mongo cursor for all items that match the provided
     * GUID.
     *
     * @param string $guid
     * @return \MongoCursor
     */
    protected function findByGuid($guid)
    {
        return $this->db->find(
            $this->getGuidQuery($guid)
        );
    }


    /**
     * Count the items matching a GUID
     * @param string $guid
     * @return int
     */
    public function numItemsWithGuid($guid)
    {
        $cursor = $this->findByGuid($guid);
        return count($cursor->toArray());
    }


    /**
     * Get a file's items
     *
     * @param int $sourceId
     * @param string $fileName
     * @param string $type
     * @return \MongoCursor
     */
    public function getFileItems($sourceId, $fileName, $type)
    {
        return $this->db->find(array(
            'source.id'     => (int) $sourceId,
            'source.file'   => $fileName,
            'source.type'   => $type
        ));
    }


    /**
     * Delete all items in a file
     *
     * @param integer $sourceId
     * @param string $fileName
     * @param string $type
     * @return int
     * @throws Exception
     */
    public function deleteAllFileItems($sourceId, $fileName, $type)
    {
        try {
            $result = $this->db->deleteMany(
                array(
                    'source.id'   => (int) $sourceId,
                    'source.file' => $fileName,
                    'source.type' => $type
                ),
                array(
                    'w'       => 1
                )
            );

            $numAffected = $result->getDeletedCount();

            return $numAffected;
        }
        catch(\MongoCursorException $e) {
            throw new Exception($e->getMessage());
        }
    }
}