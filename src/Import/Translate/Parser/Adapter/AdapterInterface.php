<?php

namespace Import\Translate\Parser\Adapter;

use Import\Input\Adapter\AbstractAdapter as InputAdapter;

interface AdapterInterface
{
    /**
     * Constructor
     * @param \Import\Input\Adapter\AbstractAdapter $file
     * @param array $itemOptions
     */
    public function __construct(InputAdapter $file, $itemOptions=array());

    /**
     * Get the next item in file
     *
     * Returns FALSE when there are no more items
     *
     * @return \Import\Translate\Item|bool
     */
    public function getNextitem();

    /**
     * Has more items?
     *
     * Check to see if there are more items to get
     *
     * @return bool
     */
    public function hasMoreitems();
}