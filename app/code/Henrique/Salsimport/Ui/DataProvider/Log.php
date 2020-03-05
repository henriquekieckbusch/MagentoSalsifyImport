<?php

namespace Henrique\Salsimport\Ui\DataProvider;

use Magento\Framework\Serialize\SerializerInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;

/**
 * Class Log
 * @package Henrique\Salsimport\Ui\DataProvider
 */
class Log extends AbstractDataProvider
{
    /**
     * @var SerializerInterface
     */
    private $serializer;

    public function __construct(
        SerializerInterface $serializer,
        \Henrique\Salsimport\Model\ResourceModel\Collection\LogFactory $collectionFactory,
        $name,
        $primaryFieldName,
        $requestFieldName,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->serializer = $serializer;
        $this->collection = $collectionFactory->create();
    }

    public function getData()
    {
        $this->getCollection()->load();
        $data = $this->getCollection()->toArray();

        foreach ($data['items'] as &$item) {
            if (!empty($item['file_name'])) {
                $item['file_name'] = $this->serializer->unserialize($item['file_name']);
            }
        }

        return $data;
    }
}
