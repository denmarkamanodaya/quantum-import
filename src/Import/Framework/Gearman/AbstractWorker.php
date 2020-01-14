<?php


namespace Import\Framework\Gearman;

use Import\Framework\Gearman\Exception\MaxMemoryException;
use Monolog\Logger;

/**
 * Gearman worker
 *
 * This class provides a light-weight wrapper around some basic Gearman
 * functionality.  You must extend this class and implement the `doWork` and
 * `getName` methods.
 *
 * @package Import
 */
abstract class AbstractWorker
{
    /**
     * @var \GearmanWorker
     */
    protected $worker;

    /**
     * @var int Max memory to use killing process
     */
    protected $maxMemory;

    /**
     * @var int Worker timeout (0 to disable)
     */
    protected $timeout = 0;

    /**
     * @var Logger Monolog logger
     */
    protected $logger;

    /**
     * Do work
     *
     * Do the actual work needed to process a request.
     *
     * @param \GearmanJob $Job
     * @param mixed $context
     */
    abstract public function doWork(\GearmanJob $Job, &$context = null);

    /**
     * Get worker name
     * @return string
     */
    abstract public function getName();

    /**
     * Protected constructor
     *
     * Use getInstance() instead.
     * @param string $servers Servers to connect
     * @param int $maxMemoryMB Maximum amount of memory to use running
     */
    public function __construct($servers, $maxMemoryMB = 250)
    {
        $this->maxMemory = ((int) $maxMemoryMB) * 1048576; // Convert from MB to B

        $this->worker = new \GearmanWorker();
        $this->worker->addServers($servers);
    }

    /**
     * Set worker timeout (in seconds)
     * @param int $timeoutSeconds
     */
    public function setTimeout($timeoutSeconds)
    {
        $this->timeout = (int) $timeoutSeconds;
    }

    /**
     * Get timeout
     * @return int
     */
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * Get logger
     * @return Logger
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Set the logger
     * @param Logger $Logger
     */
    public function setLogger(Logger $Logger)
    {
        $this->logger = $Logger;
    }

    /**
     * Run the worker
     *
     * @param mixed $context Context value to be passed to worker
     */
    public function run($context = null)
    {
        $this->prepareWorker($context);

        // Keep worker running...
        while ($this->worker->work()) {
            // If maximum memory is violated, throw exception
            if (memory_get_usage() >= $this->maxMemory) {
                throw new MaxMemoryException(
                    "Maximum memory usage exceeded.",
                    $this->maxMemory,
                    memory_get_usage()
                );
            }
        }
    }

    /**
     * Run a worker once and quit
     * @param string $context
     */
    public function runOnce($context = null)
    {
        $this->prepareWorker($context);
        $this->worker->work();
    }

    /**
     * Prepare worker to do work
     * @param string $context
     */
    protected function prepareWorker($context = null)
    {
        $this->worker->addFunction(
            $this->getName(),
            [$this, 'doWork'], // Callable to this class's public `doWork` method
            $context,
            $this->getTimeout()
        );
    }

    /**
     * Run as CLI
     *
     * This run method assumes we are running the worker as a daemonized script.
     * Therefore we will return status of 1 on failure or conclusion of work to
     * try and trigger the continuation of the daemon.
     *
     * @param mixed $context Context variable to be passed to the worker
     */
    public function runCli($context = null)
    {
        $startTime = time();

        try {
            // Run this worker
            $this->run($context);

            // Log when the worker finished, if a logger was provided
            if ($this->logger) {
                $this->logger->info("Worker finished", [
                    'worker'    => $this->getName(),
                    'runtime'   => time() - $startTime
                ]);
            }

            // Exit with status 1 so Upstart will restart the script for us
            exit(1);

        } catch (MaxMemoryException $e) {
            // Log that script was killed for memory overrun using logger, if provided.
            if ($this->logger) {
                $this->logger->error("Maximum memory exceeded.  Worker stopped.", [
                    'worker'     => $this->getName(),
                    'runtime'    => time() - $startTime,
                    'maxMemory'  => $e->maxAllowed,
                    'memoryUsed' => $e->current
                ]);
            }

            // Exit with status 1 so Upstart will restart the script for us
            exit(1);
        }
    }
}
