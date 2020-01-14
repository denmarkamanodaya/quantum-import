<?php

namespace Import\Request;

use Monolog\Logger;
use Import\App\Config;

trait InputWorkerNotifyTrait
{
    protected function processGearmanNotifications($results, $recipients, $fileName, Logger $Logger) 
    {
        $workersNotified = false;
        try {
            $workersNotified = $this->notifyWorkers(
                $results,
                $recipients
            );
        }
        catch (\UnexpectedValueException $e) {
            $Logger->critical("Unable to notify workers.  No source ID in result.", array(
                'file' => $fileName,
                'result' => $results
            ));
        }

        $Logger->info("Notified gearman workers.", array(
            'file'       => $fileName,
            'recipients' => $workersNotified
        ));

        return $workersNotified;
    }
    
    
    /**
     * Post transmit action
     *
     * This is a generic placeholder for any actions you may need to perform
     * after the item(s) transmission.
     *
     * @param array $results
     * @param array $recipients Gearman workers to notify
     * @return mixed
     */
    protected function notifyWorkers(
        $results,
        $recipients
    )
    {
        if (!is_array($results) || !isset($results['batch']['sourceId'])) {
            throw new \UnexpectedValueException("Unable to notify workers.  No source ID in result.");
        }

        $this->_sendGearmanMessages(
            $recipients,
            json_encode($results)
        );

        return $recipients;
    }

    /**
     * Send Gearman Messages
     *
     * @param array $recipients
     * @param string $payload
     */
    protected function _sendGearmanMessages($recipients, $payload)
    {
        /*
         * Loop over Gearman workers and send results out
         */
        $server = Config::get("GEARMAN_SERVER");

        foreach($recipients as $workerName) {
            $GmClient = new \Import\Framework\Gearman\Client($workerName, $server);
            $GmClient->doBackground($payload);
        }
    }
}