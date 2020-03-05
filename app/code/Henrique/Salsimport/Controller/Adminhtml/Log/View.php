<?php

namespace Henrique\Salsimport\Controller\Adminhtml\Log;

use Magento\Framework\Controller\ResultFactory;

/**
 * Class View
 * @package Henrique\Salsimport\Controller\Adminhtml\Log
 */
class View extends AbstractAction
{
    const VIEW_URL = 'Henrique_salsimport/log/view';

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $result = $this->resultRedirectFactory->create()->setPath(Index::INDEX_URL);

        if (!$logId = $this->request->getParam('log_id')) {
            $this->messageManager->addErrorMessage(__('We can\'t found log to delete'));
            return $result;
        }

        $log = $this->logCollectionFactory->create()
            ->addFieldToFilter('log_id', $logId)
            ->getFirstItem();

        if (!$log->getId()) {
            $this->messageManager->addErrorMessage(sprintf(__('We can\'t found log_id %s'), $logId));
            return $result;
        }

        $result = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $result->getConfig()->getTitle()->prepend(__('Salsify log view'));
        $result->setActiveMenu('Henrique_Salsimport::report_log');
        return $result;
    }
}
