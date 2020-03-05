<?php

namespace Henrique\Salsimport\Controller\Adminhtml\Index;

use Symfony\Component\Console\Output\OutputInterface as Output;

class Index extends \Magento\Backend\App\Action
{

    protected $resultPageFactory;

    /**
     * Constructor
     *
     * @param \Magento\Backend\App\Action\Context  $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Henrique\Salsimport\Helper\Import $_importHelper
    ) {


        $_importHelper->importStart(null);

        $this->resultPageFactory = $resultPageFactory;
        parent::__construct($context);

    }

    /**
     * Execute view action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {

        return $this->resultPageFactory->create();
    }
}
