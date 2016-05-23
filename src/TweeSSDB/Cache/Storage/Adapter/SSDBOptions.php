<?php
namespace TweeSSDB\Cache\Storage\Adapter;

use SSDB as SsdbResource;
use Zend\Cache\Exception;
use Zend\Cache\Storage\Adapter\AdapterOptions;

class SSDBOptions extends AdapterOptions
{
    const TYPE_MASTER = 'master';
    const TYPE_SLAVE  = 'slave';

    /**
     * A SSDB master resource to share
     *
     * @var null|SsdbResource
     */
    protected $SSDBMasterResource;

    /**
     * A SSDB slave resource to share
     *
     * @var null|SsdbResource
     */
    protected $SSDBSlaveResource;

    /**
     * List of SSDB servers to add on initialize
     *
     * @var string
     */
    protected $servers = array(
        array(
            'host'   => '127.0.0.1',
            'port'   => 8888,
            'weight' => 0,
            'type'   => self::TYPE_MASTER,
        ),
        array(
            'host'   => '127.0.0.1',
            'port'   => 8888,
            'weight' => 0,
            'type'   => self::TYPE_SLAVE,
        ),
    );

    /**
     * List of LibSSDB options to set on initialize
     *
     * @var array
     */
    protected $libOptions = array();

    /**
     * Set namespace.
     *
     * The option SSDB::OPT_PREFIX_KEY will be used as the namespace.
     * It can't be longer than 128 characters.
     *
     * @see AdapterOptions::setNamespace()
     * @see SSDBOptions::setPrefixKey()
     */
    public function setNamespace($namespace)
    {
        $namespace = (string) $namespace;

        if (128 < strlen($namespace)) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s expects a prefix key of no longer than 128 characters',
                __METHOD__
            ));
        }

        return parent::setNamespace($namespace);
    }

    /**
     * A SSDB master resource to share
     *
     * @param null|SsdbResource $SSDBResource
     * @return SSDBOptions
     */
    public function setSSDBMasterResource(SsdbResource $SSDBResource = null)
    {
        if ($this->SSDBMasterResource !== $SSDBResource) {
            $this->triggerOptionEvent('SSDB_resource', $SSDBResource);
            $this->SSDBMasterResource = $SSDBResource;
        }
        return $this;
    }

    /**
     * A SSDB slave resource to share
     *
     * @param null|SsdbResource $SSDBResource
     * @return SSDBOptions
     */
    public function setSSDBSlaveResource(SsdbResource $SSDBResource = null)
    {
        if ($this->SSDBSlaveResource !== $SSDBResource) {
            $this->triggerOptionEvent('SSDB_resource', $SSDBResource);
            $this->SSDBSlaveResource = $SSDBResource;
        }
        return $this;
    }

    /**
     * Get SSDB master resource to share
     *
     * @return null|SsdbResource
     */
    public function getSSDBMasterResource()
    {
        return $this->SSDBMasterResource;
    }

    /**
     * Get SSDB slave resource to share
     *
     * @return null|SsdbResource
     */
    public function getSSDBSlaveResource()
    {
        return $this->SSDBSlaveResource;
    }

    /**
     * Add a server to the list
     *
     * @param  string $host
     * @param  int $port
     * @param  int $weight
     * @return SSDBOptions
     */
    public function addServer($host, $port = 8888, $weight = 0, $type = self::TYPE_SLAVE)
    {
        $new = array(
            'host'   => $host,
            'port'   => $port,
            'weight' => $weight,
            'type'   => $type,
        );

        foreach ($this->servers as $server) {
            $diff = array_diff($new, $server);
            if (empty($diff)) {
                // Done -- server is already present
                return $this;
            }
        }

        $this->servers[] = $new;
        return $this;
    }

    /**
     * Set a list of SSDB servers to add on initialize
     *
     * @param string|array $servers list of servers
     * @return SSDBOptions
     * @throws Exception\InvalidArgumentException
     */
    public function setServers($servers)
    {
        if (!is_array($servers)) {
            return $this->setServers(explode(',', $servers));
        }

        $this->servers = array();
        foreach ($servers as $server) {
            // default values
            $host   = null;
            $port   = 8888;
            $weight = 1;
            $type   = self::TYPE_SLAVE;

            if (!is_array($server) && !is_string($server)) {
                throw new Exception\InvalidArgumentException('Invalid server specification provided; must be an array or string');
            }

            // parse a single server from an array
            if (is_array($server)) {
                if (!isset($server[0]) && !isset($server['host'])) {
                    throw new Exception\InvalidArgumentException("Invalid list of servers given");
                }

                // array(array(<host>[, <port>[, <weight>]])[, ...])
                if (isset($server[0])) {
                    $host   = (string) $server[0];
                    $port   = isset($server[1]) ? (int) $server[1] : $port;
                    $weight = isset($server[2]) ? (int) $server[2] : $weight;
                    $type   = isset($server[3]) ? $server[3] : $type;
                }

                // array(array('host' => <host>[, 'port' => <port>[, 'weight' => <weight>]])[, ...])
                if (!isset($server[0]) && isset($server['host'])) {
                    $host   = (string)$server['host'];
                    $port   = isset($server['port'])   ? (int) $server['port']   : $port;
                    $weight = isset($server['weight']) ? (int) $server['weight'] : $weight;
                    $type   = isset($server['type']) ? $server['type'] : $type;
                }
            }

            // parse a single server from a string
            if (!is_array($server)) {
                $server = trim($server);
                if (strpos($server, '://') === false) {
                    $server = 'tcp://' . $server;
                }

                $server = parse_url($server);
                if (!$server) {
                    throw new Exception\InvalidArgumentException("Invalid list of servers given");
                }

                $host = $server['host'];
                $port = isset($server['port']) ? (int)$server['port'] : $port;

                if (isset($server['query'])) {
                    $query = null;
                    parse_str($server['query'], $query);
                    if (isset($query['weight'])) {
                        $weight = (int)$query['weight'];
                    }
                    if (isset($query['type'])) {
                        $type = (string)$query['type'];
                    }
                }
            }

            if (!$host) {
                throw new Exception\InvalidArgumentException('The list of servers must contain a host value.');
            }

            $this->addServer($host, $port, $weight, $type);
        }

        if (!count($this->getMasterServers())) {
            throw new Exception\InvalidArgumentException('No master found in provided definition');
        }
        return $this;
    }

    /**
     * Get Servers
     *
     * @return array
     */
    public function getServers()
    {
        return $this->servers;
    }

    /**
     * Get Master Servers
     *
     * @return array
     */
    public function getMasterServers()
    {
        $type = self::TYPE_MASTER; // php 5.3 hack
        return array_values(array_filter($this->servers, function($server) use ($type) {
            return $server['type'] == $type;
        }));
    }

    /**
     * Get Slave Servers
     *
     * @return array
     */
    public function getSlaveServers()
    {
        return $this->getServers();
    }

    /**
     * Set libSSDB options
     *
     * @param array $libOptions
     * @return SSDBOptions
     * @link http://php.net/manual/SSDB.constants.php
     */
    public function setLibOptions(array $libOptions)
    {
        $normalizedOptions = array();
        foreach ($libOptions as $key => $value) {
            $this->normalizeLibOptionKey($key);
            $normalizedOptions[$key] = $value;
        }

        $this->triggerOptionEvent('lib_options', $normalizedOptions);
        $this->libOptions = array_diff_key($this->libOptions, $normalizedOptions) + $normalizedOptions;

        return $this;
    }

    /**
     * Set libSSDB option
     *
     * @param string|int $key
     * @param mixed      $value
     * @return SSDBOptions
     * @link http://php.net/manual/SSDB.constants.php
     */
    public function setLibOption($key, $value)
    {
        $this->normalizeLibOptionKey($key);
        $this->triggerOptionEvent('lib_options', array($key, $value));
        $this->libOptions[$key] = $value;

        return $this;
    }

    /**
     * Get libSSDB options
     *
     * @return array
     * @link http://php.net/manual/SSDB.constants.php
     */
    public function getLibOptions()
    {
        return $this->libOptions;
    }

    /**
     * Get libSSDB option
     *
     * @param string|int $key
     * @return mixed
     * @link http://php.net/manual/SSDB.constants.php
     */
    public function getLibOption($key)
    {
        $this->normalizeLibOptionKey($key);
        if (isset($this->libOptions[$key])) {
            return $this->libOptions[$key];
        }
        return null;
    }

    /**
     * Normalize libSSDB option name into it's constant value
     *
     * @param string|int $key
     * @throws Exception\InvalidArgumentException
     */
    protected function normalizeLibOptionKey(& $key)
    {
        if (is_string($key)) {
            $const = 'SSDB::OPT_' . str_replace(array(' ', '-'), '_', strtoupper($key));
            if (!defined($const)) {
                throw new Exception\InvalidArgumentException("Unknown libSSDB option '{$key}' ({$const})");
            }
            $key = constant($const);
        } else {
            $key = (int) $key;
        }
    }
}
