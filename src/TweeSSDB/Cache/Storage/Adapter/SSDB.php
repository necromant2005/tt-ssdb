<?php
namespace TweeSSDB\Cache\Storage\Adapter;

use SSDB as SsdbResource;
use stdClass;
use Traversable;
use Zend\Cache\Exception;
use Zend\Cache\Storage\AvailableSpaceCapableInterface;
use Zend\Cache\Storage\Capabilities;
use Zend\Cache\Storage\FlushableInterface;
use Zend\Cache\Storage\TotalSpaceCapableInterface;
use Zend\Cache\Storage\Adapter\AbstractAdapter;

class SSDB extends AbstractAdapter implements
    AvailableSpaceCapableInterface,
    FlushableInterface,
    TotalSpaceCapableInterface
{
    /**
     * Major version of ext/SSDB
     *
     * @var null|int
     */
    protected static $extSSDBMajorVersion;

    /**
     * The SSDB master resource
     *
     * @var SsdbResource
     */
    protected $SSDBMasterResource;

    /**
     * The SSDB slave resource
     *
     * @var SsdbResource
     */
    protected $SSDBSlaveResource;

    /**
     * Initialize the internal SSDB master resource
     *
     * @return SsdbResource
     */
    protected function getSSDBMasterResource()
    {
        if ($this->SSDBMasterResource) {
            return $this->SSDBMasterResource;
        }

        $options = $this->getOptions();

        // use a configured resource or a new one
        $SSDB = $options->getSSDBMasterResource() ?: new SsdbResource();

        // init servers
        $servers = $options->getMasterServers();
        shuffle($servers);
        $server = reset($servers);
        $SSDB->connect($server['host'], $server['port']);
        $SSDB->option(SsdbResource::OPT_SERIALIZER, SsdbResource::SERIALIZER_PHP);

        // use the initialized resource
        $this->SSDBMasterResource = $SSDB;

        return $this->SSDBMasterResource;
    }

    /**
     * Initialize the internal SSDB slave resource
     *
     * @return SsdbResource
     */
    protected function getSSDBSlaveResource()
    {
        if ($this->SSDBSlaveResource) {
            return $this->SSDBSlaveResource;
        }

        $options = $this->getOptions();

        // use a configured resource or a new one
        $SSDB = $options->getSSDBSlaveResource() ?: new SsdbResource();

        // init servers
        $servers = $options->getSlaveServers();
        shuffle($servers);
        $server = reset($servers);
        $SSDB->connect($server['host'], $server['port']);
        $SSDB->option(SsdbResource::OPT_SERIALIZER, SsdbResource::SERIALIZER_PHP);

        // use the initialized resource
        $this->SSDBSlaveResource = $SSDB;

        return $this->SSDBSlaveResource;
    }

    /* options */

    /**
     * Set options.
     *
     * @param  array|Traversable|SSDBOptions $options
     * @return SSDB
     * @see    getOptions()
     */
    public function setOptions($options)
    {
        if (!$options instanceof SSDBOptions) {
            $options = new SSDBOptions($options);
        }

        return parent::setOptions($options);
    }

    /**
     * Get options.
     *
     * @return SSDBOptions
     * @see setOptions()
     */
    public function getOptions()
    {
        if (!$this->options) {
            $this->setOptions(new SSDBOptions());
        }
        return $this->options;
    }

    /* FlushableInterface */

    /**
     * Flush the whole storage
     *
     * @return boolean
     */
    public function flush()
    {
        return true;
    }

    /* TotalSpaceCapableInterface */

    /**
     * Get total space in bytes
     *
     * @return int|float
     */
    public function getTotalSpace()
    {
        $memc  = $this->getSSDBMasterResource();
        return $memc->getStats();
        if ($stats === false) {
            throw new Exception\RuntimeException($memc->getResultMessage());
        }

        $mem = array_pop($stats);
        return $mem['limit_maxbytes'];
    }

    /* AvailableSpaceCapableInterface */

    /**
     * Get available space in bytes
     *
     * @return int|float
     */
    public function getAvailableSpace()
    {
        $memc  = $this->getSSDBMasterResource();
        $stats = $memc->getStats();
        if ($stats === false) {
            throw new Exception\RuntimeException($memc->getResultMessage());
        }

        $mem = array_pop($stats);
        return $mem['limit_maxbytes'] - $mem['bytes'];
    }

    /* reading */

    /**
     * Internal method to get an item.
     *
     * @param  string  $normalizedKey
     * @param  boolean $success
     * @param  mixed   $casToken
     * @return mixed Data on success, null on failure
     * @throws Exception\ExceptionInterface
     */
    protected function internalGetItem(& $normalizedKey, & $success = null, & $casToken = null)
    {
        $memc = $this->getSSDBSlaveResource();
        return $memc->get($normalizedKey);
    }

    /**
     * Internal method to get multiple items.
     *
     * @param  array $normalizedKeys
     * @return array Associative array of keys and values
     * @throws Exception\ExceptionInterface
     */
    protected function internalGetItems(array & $normalizedKeys)
    {
        $memc   = $this->getSSDBSlaveResource();
        return $memc->multi_get($normalizedKeys);
    }

    /**
     * Internal method to test if an item exists.
     *
     * @param  string $normalizedKey
     * @return boolean
     * @throws Exception\ExceptionInterface
     */
    protected function internalHasItem(& $normalizedKey)
    {
        $memc  = $this->getSSDBSlaveResource();
        return $memc->exists($normalizedKey);
    }

    /**
     * Internal method to test multiple items.
     *
     * @param  array $normalizedKeys
     * @return array Array of found keys
     * @throws Exception\ExceptionInterface
     */
    protected function internalHasItems(array & $normalizedKeys)
    {
        $memc   = $this->getSSDBSlaveResource();
        $map = array();
        foreach ($normalizedKeys as $key) {
            $map[$key] = $memc->exists($key);
        }
        return $map;
    }

    /**
     * Get metadata of multiple items
     *
     * @param  array $normalizedKeys
     * @return array Associative array of keys and metadata
     * @throws Exception\ExceptionInterface
     */
    protected function internalGetMetadatas(array & $normalizedKeys)
    {
        $memc   = $this->getSSDBSlaveResource();
        return $memc->multi_get($normalizedKeys);
    }

    /* writing */

    /**
     * Internal method to store an item.
     *
     * @param  string $normalizedKey
     * @param  mixed  $value
     * @return boolean
     * @throws Exception\ExceptionInterface
     */
    protected function internalSetItem(& $normalizedKey, & $value)
    {
        $memc = $this->getSSDBMasterResource();
        return $memc->set($normalizedKey, $value);
    }

    /**
     * Internal method to store multiple items.
     *
     * @param  array $normalizedKeyValuePairs
     * @return array Array of not stored keys
     * @throws Exception\ExceptionInterface
     */
    protected function internalSetItems(array & $normalizedKeyValuePairs)
    {
        $memc = $this->getSSDBMasterResource();
        return $memc->multi_set($normalizedKeyValuePairs);
    }

    /**
     * Add an item.
     *
     * @param  string $normalizedKey
     * @param  mixed  $value
     * @return boolean
     * @throws Exception\ExceptionInterface
     */
    protected function internalAddItem(& $normalizedKey, & $value)
    {
        $memc = $this->getSSDBMasterResource();
        return $memc->incr($normalizedKey, $value);
    }

    /**
     * Internal method to replace an existing item.
     *
     * @param  string $normalizedKey
     * @param  mixed  $value
     * @return boolean
     * @throws Exception\ExceptionInterface
     */
    protected function internalReplaceItem(& $normalizedKey, & $value)
    {
        $memc = $this->getSSDBMasterResource();
        return $memc->getset($normalizedKey, $value);
    }

    /**
     * Internal method to set an item only if token matches
     *
     * @param  mixed  $token
     * @param  string $normalizedKey
     * @param  mixed  $value
     * @return boolean
     * @throws Exception\ExceptionInterface
     * @see    getItem()
     * @see    setItem()
     */
    protected function internalCheckAndSetItem(& $token, & $normalizedKey, & $value)
    {
        $memc       = $this->getSSDBMasterResource();
        return $memc->getset($token, $normalizedKey, $value);
    }

    /**
     * Internal method to remove an item.
     *
     * @param  string $normalizedKey
     * @return boolean
     * @throws Exception\ExceptionInterface
     */
    protected function internalRemoveItem(& $normalizedKey)
    {
        $memc = $this->getSSDBMasterResource();
        return $memc->del($normalizedKey);
    }

    /**
     * Internal method to remove multiple items.
     *
     * @param  array $normalizedKeys
     * @return array Array of not removed keys
     * @throws Exception\ExceptionInterface
     */
    protected function internalRemoveItems(array & $normalizedKeys)
    {
        $memc = $this->getSSDBMasterResource();
        return $memc->multi_del($normalizedKeys);
    }

    /**
     * Internal method to increment an item.
     *
     * @param  string $normalizedKey
     * @param  int    $value
     * @return int|boolean The new value on success, false on failure
     * @throws Exception\ExceptionInterface
     */
    protected function internalIncrementItem(& $normalizedKey, & $value)
    {
        $memc     = $this->getSSDBMasterResource();
        $value    = (int) $value;
        return $memc->incr($normalizedKey, $value);
    }

    /**
     * Internal method to decrement an item.
     *
     * @param  string $normalizedKey
     * @param  int    $value
     * @return int|boolean The new value on success, false on failure
     * @throws Exception\ExceptionInterface
     */
    protected function internalDecrementItem(& $normalizedKey, & $value)
    {
        $memc     = $this->getSSDBMasterResource();
        $value    = (int)$value * -1;
        return $memc->incr($normalizedKey, $value);
    }

    /* status */

    /**
     * Internal method to get capabilities of this adapter
     *
     * @return Capabilities
     */
    protected function internalGetCapabilities()
    {
        if ($this->capabilities === null) {
            $this->capabilityMarker = new stdClass();
            $this->capabilities     = new Capabilities(
                $this,
                $this->capabilityMarker,
                array(
                    'supportedDatatypes' => array(
                        'NULL'     => true,
                        'boolean'  => true,
                        'integer'  => true,
                        'double'   => true,
                        'string'   => true,
                        'array'    => true,
                        'object'   => 'object',
                        'resource' => false,
                    ),
                    'supportedMetadata'  => array(),
                    'minTtl'             => 1,
                    'maxTtl'             => 0,
                    'staticTtl'          => true,
                    'ttlPrecision'       => 1,
                    'useRequestTime'     => false,
                    'expiredRead'        => false,
                    'maxKeyLength'       => 255,
                    'namespaceIsPrefix'  => true,
                )
            );
        }

        return $this->capabilities;
    }
}