<?php


namespace Henrique\Salsimport\Block\Adminhtml\Log;

use Magento\Backend\Block\Template;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Class View
 * @package Henrique\Salsimport\Block\Adminhtml\Log
 */
class View extends Template
{
    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var \Henrique\Salsimport\Model\ResourceModel\Collection\LogFactory
     */
    private $logCollectionFactory;

    /**
     * @var SerializerInterface
     */
    private $serialize;

    public function __construct(
        SerializerInterface $serializer,
        \Henrique\Salsimport\Model\ResourceModel\Collection\LogFactory $logCollectionFactory,
        RequestInterface $request,
        Template\Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->serialize = $serializer;
        $this->request = $request;
        $this->logCollectionFactory = $logCollectionFactory;
    }

    /**
     * Get log
     *
     * @return array|bool
     */
    public function getLog()
    {
        $logId = $this->request->getParam('log_id');

        if (!$logId) {
            return false;
        }

        $log = $this->logCollectionFactory->create()
            ->addFieldToFilter('log_id', $logId)
            ->getFirstItem();

        return $log->getId() ? [
            'log_id' => $log->getLogId(),
            'data' => $this->serialize->unserialize($log->getSerializedData())
        ] : false;
    }
}
