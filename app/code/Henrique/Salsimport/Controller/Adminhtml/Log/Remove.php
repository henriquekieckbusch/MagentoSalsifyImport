<?php

namespace Henrique\Salsimport\Controller\Adminhtml\Log;

/**
 * Class Remove
 * @package Henrique\Salsimport\Controller\Adminhtml\Log
 */
class Remove extends AbstractAction
{
    const REMOVE_URL = 'Henrique_salsimport/log/remove';

    /**
     * Delete log manually
     *
     * @inheritDoc
     */
    public function execute()
    {
        $result = $this->resultRedirectFactory->create()->setPath(Index::INDEX_URL);

        if (!$logId = $this->request->getParam('log_id')) {
            $this->messageManager->addErrorMessage(__('We can\'t found log to delete'));
            return $result;
        }

        try {
            $this->log->removeLog($logId);
            $this->messageManager->addSuccessMessage(__('Log successfully deleted'));
        } catch (\Exception $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        }

        return $result;
    }
}
