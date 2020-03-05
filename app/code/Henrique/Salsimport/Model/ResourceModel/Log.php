<?php

namespace Henrique\Salsimport\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Class Log
 * @package Henrique\Salsimport\Model\ResourceModel
 */
class Log extends AbstractDb
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init('Henrique_salsify_log', 'log_id');
    }
}
