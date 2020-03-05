<?php

namespace Henrique\Salsimport\Model;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Class Log
 * @package Henrique\Salsimport\Model
 */
class Log extends AbstractModel
{
    /**
     * @var \Henrique\Salsimport\Model\ConfigProvider
     */
    private $configProvider;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    public function __construct(
        SerializerInterface $serializer,
        \Henrique\Salsimport\Model\ConfigProvider $configProvider,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Henrique\Salsimport\Model\ResourceModel\Log $resource,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
        $this->configProvider = $configProvider;
        $this->serializer = $serializer;
    }

    /**
     * Log import output
     *
     * @param $log
     * @return $this|bool
     */
    public function log($log)
    {
        if (!$this->configProvider->isLogEnabled()) {
            return false;
        }

        try {
            if (count($log['files'])) {
                $data = [
                    'log_id' => null,
                    'file_name' => $this->serializer->serialize(array_values($log['files'])),
                    'serialized_data' => $this->serializer->serialize($log['output']),
                    'created_at' => time()
                ];
            } else {
                $data = [
                    'log_id' => null,
                    'file_name' => $this->serializer->serialize([__('No files found to process')]),
                    'serialized_data' => $this->serializer->serialize([__('No files found to process')]),
                    'created_at' => time()
                ];
            }

            $this->setData($data);
            $this->_resource->save($this);
        } catch (\Exception $exception) {
            $this->_logger->error(sprintf('Salsify import error saving log: %s', $exception->getMessage()));
        }

        return $this;
    }

    /**
     * Remove log
     *
     * @param $logId
     * @return bool
     * @throws \Exception
     */
    public function removeLog($logId)
    {
        if ($logId) {
            try {
                $this->setId($logId);
                $this->_resource->delete($this);
                return true;
            } catch (\Exception $exception) {
                throw new \Exception(__('Can\'t delete log:' . $exception->getMessage()));
            }
        }

        return false;
    }
}
