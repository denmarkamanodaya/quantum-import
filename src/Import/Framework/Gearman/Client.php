<?php

namespace Import\Framework\Gearman;

class Client
{
    /**
     * @var \GearmanClient
     */
    protected $client;

    /**
     * @var string Gearman job name to call
     */
    protected $jobName;

    /**
     * @var string
     */
    protected $backgroundHandle;

    /**
     * Protected constructor (use getInstance)
     *
     * @param string $jobName
     * @param string $servers
     */
    public function __construct($jobName, $servers)
    {
        $this->jobName = $jobName;

        $this->client = new \GearmanClient();
        $this->client->addServers($servers);
    }

    /**
     * Get the underlying Gearman client object
     *
     * Provided just in case you need to access more obscure Gearman
     * functionality.
     *
     * @return \GearmanClient
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Run a Gearman task with "normal" priority
     *
     * Runs a single task and returns a string representation of the result.
     *
     * @param string $workload Serialized data to be processed
     * @return string The result
     */
    public function doNormal($workload)
    {
        return $this->client->doNormal($this->jobName, $workload);
    }


    /**
     * Run a Gearman task with "high" priority
     *
     * Runs a single task and returns a string representation of the result.
     *
     * @param string $workload Serialized data to be processed
     * @return string The result
     */
    public function doHigh($workload)
    {
        return $this->client->doHigh($this->jobName, $workload);
    }

    /**
     * Run a Gearman task with "low" priority
     *
     * Runs a single task and returns a string representation of the result.
     *
     * @param string $workload Serialized data to be processed
     * @return string The result
     */
    public function doLow($workload)
    {
        return $this->client->doLow($this->jobName, $workload);
    }

    /**
     * Run a Gearman task in the background / asynchronously
     *
     * This method should be used when you don't want to wait for Gearman to
     * complete processing the task before being able to continue executing your
     * application logic.  To put it another way, this is "non-blocking".
     *
     * Unless you *really* need the result of the task right *now*, this is the
     * generally recommended way to call a Gearman worker.
     *
     * NOTE: The return value of this method is actually a "job handle" and not
     *  the actual result of the job.
     *
     * @param string $workload Serialized data to be processed
     * @return string Gearman job handle
     */
    public function doBackground($workload)
    {
        $this->backgroundHandle = $this->client->doBackground($this->jobName, $workload);
        return $this->backgroundHandle;
    }


    /**
     * Get the job status

     * This method returns a numerically indexed array containing status
     * information for the job corresponding to the last background job. In this
     * array, here's what the keys represent:
     *  0 => boolean indicating whether the job is known
     *  1 => boolean indicating whether the job is still running
     *  2 => numerator of the fractional completion percentage
     *  3 => denominator of the fractional completion percentage
     *
     * When checking to see if a background job has completed, wait for the
     * first array value to equal FALSE.
     *
     * @return array
     */
    public function jobStatus()
    {
        if (!$this->backgroundHandle) {
            throw new \RuntimeException("No background task found.");
        }

        return $this->client->jobStatus($this->backgroundHandle);
    }

    /**
     * Check to see if a background job has finished
     * @return bool
     */
    public function isFinished()
    {
        $status = $this->jobStatus();
        return (!$status[0]);
    }
}
