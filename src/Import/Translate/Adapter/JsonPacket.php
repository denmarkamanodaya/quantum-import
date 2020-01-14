<?php

namespace Import\Translate\Adapter;
use Import\Log;

/**
 * JSON Packet
 *
 * A "JSON Packet" is the file format used by the new Spider system.  It's a
 * file format that contains multiple lines of JSON.  Each line contains the
 * item data and any associated metadata.
 *
 * @package Import\Translate\Adapter
 */
class JsonPacket extends AbstractAdapter
{
    /**
     * Get logger instance
     * @return \Monolog\Logger
     */
    protected function _getLoggerComponent()
    {
        return 'spider.translate';
    }
}
