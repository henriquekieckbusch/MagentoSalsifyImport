<?php

namespace Henrique\Salsimport\Controller\Adminhtml\Log;

use Magento\Framework\Controller\ResultFactory;

/**
 * Class Index
 * @package Henrique\Salsimport\Controller\Adminhtml\Log
 */
class Index extends AbstractAction
{
    const INDEX_URL = 'Henrique_salsimport/log/index';

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $result->getConfig()->getTitle()->prepend(__('Salsify import log'));
        $result->setActiveMenu('Henrique_Salsimport::report_log');
        return $result;
    }
}
