<?php

namespace Henrique\Salsimport\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Henrique\Salsimport\Model\Log;

/**
 * Class Hook
 * @package Henrique\Salsimport\Observer
 */
class Hook implements ObserverInterface
{
    /**
     * @var Log
     */
    private $log;

    public function __construct(Log $log)
    {
        $this->log = $log;
    }

    /**
     * Hook useful to handle data after import finished
     *
     * @param $observer \Magento\Framework\Event\Observer
     * @return Hook
     * @see \Henrique\Salsimport\Helper\Import
     */
    public function execute(Observer $observer)
    {
        if ($log = $observer->getLog()) {
            $this->log->log($log);
        }

        return $this;
    }
}
