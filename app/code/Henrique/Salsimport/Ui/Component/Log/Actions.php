<?php

namespace Henrique\Salsimport\Ui\Component\Log;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Henrique\Salsimport\Controller\Adminhtml\Log\View;
use Henrique\Salsimport\Controller\Adminhtml\Log\Remove;

class Actions extends Column
{
    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        $this->urlBuilder = $urlBuilder;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        foreach ($dataSource['data']['items'] as & $item) {
            $name = $this->getData('name');

            $viewUrl = $this->urlBuilder->getUrl(View::VIEW_URL, ['log_id' => $item['log_id']]);
            $removeUrl = $this->urlBuilder->getUrl(Remove::REMOVE_URL, ['log_id' => $item['log_id']]);

            $item[$name]['view'] = [
                'href' => $viewUrl,
                'label' => __('View')
            ];

            $item[$name]['remove'] = [
                'href' => $removeUrl,
                'label' => __('Remove')
            ];
        }

        return $dataSource;
    }
}
