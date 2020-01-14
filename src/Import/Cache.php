<?php

namespace Import;

/**
 * Cache
 *
 * This class provides a couple different caching mechanisms to implementing
 * classes.  First, it has a "simple" cache that just caches values into a local
 * array which lives in memory and is wiped out when the class is destructed.
 * Second, it offers a "durable" cache that uses Redis to save values between
 * sessions.
 *
 * @package Import
 */
class Cache
{
    /**
     * @var array
     */
    protected $_simpleCache = array();

    /**
     * @var \Predis\Client
     */
    static protected $_redisCache;

    /**
     * @var int Seconds
     */
    protected $_durableCacheExpiration = 10800; // 3 hours in seconds


    protected $_durableCachePrefix = 'Import:';

    /**
     * Set or get a value from the cache
     *
     * If you use this method to set a value, it's helpful to know that it will
     * return that value back.  This allows you to set the value right in a
     * return statement for brevity.
     *
     * @param string $name
     * @param mixed $value Optional. If not provided, cached value is returned
     * @return mixed
     */
    protected function _simpleCache($name, $value=null)
    {
        // Get cache value if hit found
        if ($this->_simpleCacheCheck($name) && $value === null) {
            return $this->_simpleCache[$name];
        }

        // Set cache value
        $this->_simpleCache[$name] = $value;

        // Return set value
        return $this->_simpleCache[$name];
    }

    /**
     * Check to see if a value is cached
     * @param string $name
     * @return bool
     */
    protected function _simpleCacheCheck($name)
    {
        return array_key_exists($name, $this->_simpleCache);
    }

    /**
     * Empty the cache
     */
    protected function _simpleCacheReset()
    {
        $this->_simpleCache = array();
    }

    /**
     * Get the Redis client
     *
     * @static
     * @return \Predis\Client
     */
    public static function getCacheClient()
    {
        // Pseudo singleton check
        if (self::$_redisCache instanceof \Predis\Client) {
            return self::$_redisCache;
        }

        $configCacheMaster = \Import\App\Config::get('CACHE_MASTER');
        $configCacheSlaves = [
            \Import\App\Config::get('CACHE_SLAVE_1'),
            \Import\App\Config::get('CACHE_SLAVE_2')
        ];


        $cacheParameters = array(
            $configCacheMaster
        );

        $slaves = $configCacheSlaves;
        foreach ($slaves as $slave) {
            $cacheParameters[] = $slave;
        }

        $cacheOptions = array('replication' => true);

        self::$_redisCache = new \Predis\Client(
            $cacheParameters,
            $cacheOptions
        );

        return self::$_redisCache;
    }

    /**
     * Get durable cache
     *
     * This method returns a Predis client
     *
     * @return \Predis\Client
     */
    protected function _getDurableCacheClient()
    {
        return self::getCacheClient();
    }

    /**
     * Get or set an item to/from durable cache
     *
     * This method also uses the "simple cache" to limit round trips to Redis.
     * "Durable cache" keys are prefixed with "d:" as a simple namespace.
     *
     * @param $name
     * @param null $value
     * @return null
     */
    protected function _durableCache($name, $value=null)
    {
        // Simple-cache check
        if ($this->_simpleCacheCheck('d:' . $name)) {
            return $this->_simpleCache('d:' . $name);
        }

        $client = $this->_getDurableCacheClient();

        // Get cache value if hit found
        if ($this->_durableCacheCheck($name) && $value === null) {
            return json_decode($client->get($name), true);
        }

        // Set cache value
        $client->set($name, json_encode($value));

        // Set expiration, if one is specified (default == 3 hours)
        if ($this->_durableCacheExpiration) {
            $client->expire($name, $this->_durableCacheExpiration);
        }

        $this->_simpleCache('d:' . $name, $value);

        // Return set value
        return $value;
    }

    /**
     * Check to see if an item is cached in the durable cache
     *
     * This method also uses "Simple Cache" to limit round-trips to Redis.
     *
     * @param string $name
     * @return bool
     */
    protected function _durableCacheCheck($name)
    {
        if ($this->_simpleCacheCheck('d:' . $name)) {
            return true;
        }

        return (bool) $this->_getDurableCacheClient()->exists($name);
    }

    /**
     * Make a durable cache key
     *
     * Uses Redis naming conventions for generating cache keys.
     *
     * @link http://redis.io/topics/data-types-intro
     * @param string $object Object generating data
     * @param int|string $id ID of the generated data
     * @param string $field  Type of data
     * @return string
     */
    protected function _durableCacheMakeKey($object, $id, $field)
    {
        return str_replace(
            ' ',
            '-',
            strtolower(
                'import-prepare-' . $object. ':' . $id . ':' . $field
            )
        );
    }
}