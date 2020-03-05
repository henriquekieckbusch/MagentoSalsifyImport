<?php

namespace Henrique\Salsimport\Controller\Adminhtml\Log;

use Magento\Backend\App\Action;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Henrique\Salsimport\Model\Log;
use Henrique\Salsimport\Model\ResourceModel\Collection\LogFactory;

/**
 * Class View
 * @package Henrique\Salsimport\Controller\Adminhtml\Log
 */
class AbstractAction extends Action
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Henrique_Salsimport::logs';

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var LogFactory
     */
    protected $logCollectionFactory;

    /**
     * @var Log
     */
    protected $log;

    public function __construct(
        Log $log,
        LogFactory $logCollectionFactory,
        RequestInterface $request,
        Action\Context $context
    ) {
        parent::__construct($context);
        $this->log = $log;
        $this->logCollectionFactory = $logCollectionFactory;
        $this->request = $request;
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $result->getConfig()->getTitle()->prepend(__('Salsify log view'));
        $result->setActiveMenu('Henrique_Salsimport::report_log');
        return $result;
    }
}
