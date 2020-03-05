<?php

namespace Henrique\Salsimport\Model\ResourceModel\Collection;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Class Log
 * @package Henrique\Salsimport\Model\ResourceModel\Collection
 */
class Log extends AbstractCollection
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(\Henrique\Salsimport\Model\Log::class,
            \Henrique\Salsimport\Model\ResourceModel\Log::class);
    }
}
