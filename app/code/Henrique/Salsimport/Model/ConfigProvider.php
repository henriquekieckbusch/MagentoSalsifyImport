<?php

namespace Henrique\Salsimport\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Class ConfigProvider
 * @package Henrique\Salsimport\Model
 */
class ConfigProvider
{
    /**
     * @var string
     */
    const XML_CONFIG_PATH_LOG_ENABLED = 'salsify/salsify_import/log/enabled';

    /**
     * @var string
     */
    const XML_CONFIG_PATH_LOG_MAX_DAYS = 'salsify/salsify_import/log/max_days';

    /**
     * @var string
     */
    const XML_CONFIG_PATH_ENABLED = 'salsify/salsify_import/salsify_import_enabled';

    /**
     * @var string
     */
    const XML_CONFIG_PATH_INSERT_MODE = 'salsify/salsify_import/salsify_mode_insert';

    /**
     * @var string
     */
    const XML_CONFIG_PATH_UPDATE_MODE = 'salsify/salsify_import/salsify_mode_update';

    /**
     * @var string
     */
    const XML_CONFIG_PATH_IMPORT_PATH = 'salsify/salsify_import/salsify_import_path';

    /**
     * @var string
     */
    const XML_CONFIG_PATH_IMAGE_ATTR = 'salsify/salsify_import/salsify_import_image_attributes';

    /**
     * @var string
     */
    const XML_CONFIG_PATH_IMAGE_MODEL = 'salsify/salsify_import/salsify_import_image_model';

    /**
     * @var string
     */
    const XML_CONFIG_PATH_CSV_URL = 'salsify/salsify_import/salsify_import_csv_url';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Returns if logs are enabled
     *
     * @return mixed
     */
    public function isLogEnabled()
    {
        return $this->scopeConfig->isSetFlag(self::XML_CONFIG_PATH_LOG_ENABLED);
    }

    /**
     * Returns max days to save logs
     *
     * @return mixed
     */
    public function getLogMaxDays()
    {
        return $this->scopeConfig->getValue(self::XML_CONFIG_PATH_LOG_MAX_DAYS);
    }

    /**
     * Check if module is enabled
     *
     * @return bool
     */
    public function isEnabled()
    {
        return $this->scopeConfig->isSetFlag(self::XML_CONFIG_PATH_ENABLED);
    }

    /**
     * Check if can create new products
     *
     * @return bool
     */
    public function isModeInsert()
    {
        return $this->scopeConfig->isSetFlag(self::XML_CONFIG_PATH_INSERT_MODE);
    }

    /**
     * Check if need to update products
     *
     * @return bool
     */
    public function isModeUpdate()
    {
        return $this->scopeConfig->isSetFlag(self::XML_CONFIG_PATH_UPDATE_MODE);
    }

    /**
     * Retrieve salsify directory
     *
     * @return mixed
     */
    public function getDirectory()
    {
        return $this->scopeConfig->getValue(self::XML_CONFIG_PATH_IMPORT_PATH);
    }

    /**
     * Retrieve csv url
     *
     * @return mixed
     */
    public function getCsvUrl()
    {
        return $this->scopeConfig->getValue(self::XML_CONFIG_PATH_CSV_URL);
    }

    /**
     * Get image attributes
     *
     * @return array
     */
    public function getImageAttributes()
    {
        $values = $this->scopeConfig->getValue(self::XML_CONFIG_PATH_IMAGE_ATTR);
        return array_map('trim', explode(PHP_EOL, $values));
    }

    /**
     * Retrieve image relation attributes
     *
     * @return array
     */
    public function getRelationAttributes()
    {
        $return = [];
        $lines = explode(PHP_EOL, $this->scopeConfig->getValue(self::XML_CONFIG_PATH_IMAGE_MODEL));

        foreach ($lines as $line) {
            $parts = explode(',', $line);
            $parts[0] = trim($parts[0]);
            $parts[1] = !empty($parts[1]) ? trim($parts[1]) : '';
            if (strlen($parts[0]) > 0 && strlen($parts[1]) > 0) {
                $return[] = [$parts[0], $parts[1]];
            }
        }

        return $return;
    }
}
