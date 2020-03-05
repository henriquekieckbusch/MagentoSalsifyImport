<?php

namespace Henrique\Salsimport\Cron;

use Henrique\Salsimport\Model\ConfigProvider;
use Henrique\Salsimport\Model\Log;
use Henrique\Salsimport\Model\ResourceModel\Collection\LogFactory;

/**
 * Class CleanLogs
 * @package Henrique\Salsimport\Cron
 */
class CleanLogs
{
    /**
     * @var LogFactory
     */
    private $logCollectionFactory;

    /**
     * @var \Henrique\Salsimport\Model\ResourceModel\Log
     */
    private $logResource;

    /**
     * @var ConfigProvider
     */
    private $configProvider;

    public function __construct(
        LogFactory $logCollectionFactory,
        \Henrique\Salsimport\Model\ResourceModel\Log $logResource,
        ConfigProvider $configProvider
    ) {
        $this->logCollectionFactory = $logCollectionFactory;
        $this->configProvider = $configProvider;
        $this->logResource = $logResource;
    }

    /**
     * Execute the cron
     *
     * @return CleanLogs|void
     */
    public function execute()
    {
        if (!$maxDaysToKeep = $this->configProvider->getLogMaxDays()) {
            return $this;
        }

        $logs = $this->logCollectionFactory->create();

        foreach ($logs->getItems() as $log) {
            /**
             * @var Log $log
             */
            if ($this->canClean($log, $maxDaysToKeep)) {
                $this->logResource->delete($log);
            }
        }

        return $this;
    }

    /**
     * Retrieve if log is ready to clean
     *
     * @param $log
     * @param $maxDays
     * @return bool
     */
    private function canClean($log, $maxDays)
    {
        if ($createdAt = $log->getCreatedAt()) {
            $maxDayDate = $this->getMaxDate($maxDays);
            $logDayDate = $this->getDayDate($createdAt);
            $maxDayNumber = date('d', $maxDayDate->getTimestamp());
            $logDayNumber = date('d', $logDayDate->getTimestamp());

            return ($logDayDate->getTimestamp() >= $maxDayDate->getTimestamp()) && ($logDayNumber > $maxDayNumber);
        }

        return false;
    }

    /**
     * Get max day number
     *
     * @param $maxDays
     * @return \DateTime
     * @throws \Exception
     */
    public function getMaxDate($maxDays)
    {
        $today = new \DateTime('today');
        return $today->modify(sprintf('+%s day', $maxDays));
    }

    /**
     * Get day date
     *
     * @param $date
     * @return \DateTime
     * @throws \Exception
     */
    public function getDayDate($date)
    {
        $date = new \DateTime($date);
        return $date;
    }
}
