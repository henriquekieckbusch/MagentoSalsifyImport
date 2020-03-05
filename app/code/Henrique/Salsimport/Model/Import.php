<?php

namespace Henrique\Salsimport\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Gallery\Processor;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\File\Csv;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class Import
 * @package Henrique\Salsimport\Model
 */
class Import
{
    /**
     * @var string
     */
    const DIRECTORY_PATH = 'salsify';

    /**
     * @var string
     */
    const DIRECTORY_PROCESSED_PATH = 'salsify/processed';

    /**
     * @var \Magento\Framework\Filesystem\Directory\WriteFactory
     */
    private $writeFactory;

    /**
     * @var DirectoryList
     */
    private $dir;

    /**
     * Array with all output collected
     * @var array
     */
    private $allOutput;

    /**
     * @var ConfigProvider
     */
    private $configProvider;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var ProductFactory
     */
    private $productFactory;

    /**
     * @var CollectionFactory
     */
    private $productCollectionFactory;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var AttributeRepositoryInterface
     */
    private $attributeRepository;

    /**
     * @var State
     */
    private $state;

    /**
     * @var Processor
     */
    private $imageProcessor;

    /**
     * @var Csv
     */
    private $csv;

    /**
     * Event manager
     *
     * @var ManagerInterface
     */
    private $eventManager;

    public function __construct(
        \Magento\Framework\Filesystem\Directory\WriteFactory $writeFactory,
        DirectoryList $dir,
        ManagerInterface $eventManager,
        ConfigProvider $configProvider,
        ProductRepositoryInterface $productRepository,
        ProductFactory $productFactory,
        State $state,
        CollectionFactory $productCollectionFactory,
        Processor $imageProcessor,
        AttributeRepositoryInterface $attributeRepository,
        Csv $csv,
        StoreManagerInterface $storeManager

    ) {
        $this->dir = $dir;
        $this->writeFactory = $writeFactory->create($this->dir->getRoot());
        $this->eventManager = $eventManager;
        $this->configProvider = $configProvider;
        $this->allOutput = null;
        $this->productRepository = $productRepository;
        $this->productFactory = $productFactory;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->state = $state;
        $this->imageProcessor = $imageProcessor;
        $this->csv = $csv;
        $this->attributeRepository = $attributeRepository;
        $this->storeManager = $storeManager;
    }

    /**
     * Import products
     *
     * @return void
     * @throws \Zend_Db_Select_Exception
     */
    public function import()
    {
        $this->initPhp();
        $this->collectOutput('<pre>');

        $start = time();
        $this->allOutput = null;
        $checkedProducts = 0;
        $updatedProducts = 0;
        $createdProducts = 0;
        $updatedIgnoredProducts = 0;
        $createdIgnoredProducts = 0;

        $this->collectOutput('---- Starting ' . date('Y-m-d H:i:s') . ' ---');

        try {
            $this->state->setAreaCode(Area::AREA_FRONTEND);
        } catch (\Exception $e) {
            //Area already set
        }

        //loading mapping
        $mapping = [];
        $fh = $this->configProvider->getRelationAttributes();

        foreach ($fh as $v) {
            if (array_key_exists($v[0], $mapping)) {
                $mapping[$v[0]] .= ',' . $v[1];
            } else {
                $mapping[$v[0]] = $v[1];
            }
        }

        //loading csvs
        $files = $this->writeFactory->read($this->getDirectory());
        $attributes = [];

        $processedFiles = [];

        foreach ($files as $file) {
            //Avoid read directory
            if ($this->writeFactory->isDirectory($file)) {
                continue;
            }

            $_file = pathinfo($file);
            $processedFiles[] = $file;

            if ((array_key_exists('extension',
                    $_file) && strtolower($_file['extension']) === 'csv' && substr($_file['basename'], 0, 1) != '.')
            ) {
                $this->collectOutput('-----');
                $this->collectOutput('File: ' . $file);
                $this->collectOutput('-----');

                $fh = $this->csv->getData($file);
                $n = -1;

                foreach ($fh as $row => $columns) {
                    $n++;
                    if ($n == 0) {

                        foreach ($columns as $_num => $_column) {
                            if (array_key_exists($_column, $mapping) === false) {
                                $attributes[$_num] = null;
                            } else {
                                $attributes[$_num] = $mapping[$_column];
                            }
                        }

                        if (array_search('sku', $attributes) === false) {
                            $this->collectOutput('Error, the csv for mapping needs the sku field ' . serialize($attributes));
                            return;
                        }

                        if (array_search('name', $attributes) === false) {
                            $this->collectOutput('Error, the csv for mapping needs the name field ' . serialize($attributes));
                            return;
                        }

                        continue;
                    }

                    $sku = $columns[array_search('sku', $attributes)];
                    $this->collectOutput('SKU: ' . $sku);

                    $name = $columns[array_search('name', $attributes)];
                    $this->collectOutput('NAME: ' . $name);

                    $storeId = $columns[array_search('store_id', $attributes)];
                    $this->collectOutput('store_id: ' . $storeId);

                    $this->storeManager->setCurrentStore($storeId);
                    $products = [];

                    if (strlen($sku) > 0 && strlen($name) > 0) {
                        /** @var \Magento\Catalog\Model\Product\Interceptor $product */

                        try {
                            $product = $this->productRepository->get($sku, true, $storeId, true);
                            $this->collectOutput('Updating...');
                            $checkedProducts++;

                            if (!$this->configProvider->isModeUpdate()) {
                                $this->collectOutput('aborted [it is OFF in the admin panel]');
                                $updatedIgnoredProducts++;
                                continue;
                            }

                            $updatedProducts++;

                        } catch (NoSuchEntityException $e) {
                            $product = $this->productFactory->create();
                            $this->collectOutput('Creating...');
                            $checkedProducts++;
                            if (!$this->configProvider->isModeInsert()) {
                                $this->collectOutput('aborted [it is OFF in the admin panel]');
                                $createdIgnoredProducts++;
                                continue;
                            }
                            $createdProducts++;

                            //Maybe we need to change the name (in salsify many products have the same name)
                            $collection = $this->productCollectionFactory->create();
                            $collection->addAttributeToFilter('name', array('like' => strtoupper($name)));

                            $v = $collection->getSelect()->getPart('where');
                            $collection->getSelect()->setPart(
                                'where',
                                str_replace('(IF', 'UPPER(IF', $v)
                            );
                            $products = $collection->getData();
                        }

                        if (is_null($product->getPrice())) {
                            $product->setPrice(0);
                        }

                        $product->setAttributeSetId(4); //default attribute set (?)

                        try {
                            $product->setSku($sku);
                            $product->setData('name', $name);

                            //clear Media Gallery
                            $images = $product->getMediaGalleryImages();
                            foreach ($images as $child) {
                                $this->imageProcessor->removeImage($product, $child->getFile());
                            }

                            $imagesInGallery = [];
                            $attributesUsed = [];
                            $thumbnailVideo = '';
                            //update attributes
                            foreach ($attributes as $_attribute) {
                                $attr = explode(',', $_attribute);
                                foreach ($attr as $_v => $attribute/*magento attribute*/) {

                                    $var = '';
                                    if (!is_null($_attribute) && array_search($attribute, ['sku', 'name']) === false) {

                                        if (array_search($attribute,
                                                $this->configProvider->getImageAttributes()) === false) {


                                            //$var = $columns[$this->findInArrayBasedInUse($attribute,$attributes,$attributesUsed)];
                                            $var = $columns[$this->findInArrayBasedInUse($_attribute, $attributes,
                                                $attributesUsed)];

                                            $attributesUsed[] = $_attribute;


                                            switch ($attribute) {
                                                case 'primary_image':
                                                    if (strlen($var) > 3) {
                                                        $pi = pathinfo($var);
                                                        $thumbnailVideo = $var;

                                                        if (array_search(strtolower($pi['extension']),
                                                                ['jpg', 'png', 'gif']) !== false) {
                                                            $url = $pi['basename'];
                                                            if (!file_exists(dirname(__FILE__) . '/../../../../../pub/media/catalog/product/' . $url)) {
                                                                file_put_contents(dirname(__FILE__) . '/../../../../../pub/media/catalog/product/' . $url,
                                                                    file_get_contents($var));
                                                                $this->collectOutput('... Downloading image: ' . $var);
                                                            }
                                                            $imagesInGallery[] = $url;
                                                            $product->addImageToMediaGallery('catalog/product/' . $url,
                                                                ['image', 'small_image', 'thumbnail'], false, false);
                                                            $this->collectOutput($attribute . ': ' . 'catalog/product/' . $url);
                                                        }
                                                    }

                                                    break;
                                                case 'website_ids':
                                                    if (strlen($var) > 0) {
                                                        $product->setWebsiteIds(array_filter(explode(',', $var)));
                                                        $this->collectOutput('website_ids: ' . $var);
                                                    }
                                                    break;
                                                case 'store_id':
                                                    if (strlen($var) > 0) {
                                                        $product->setStoreId(array_filter(explode(',', $var)));
                                                        $this->collectOutput('store_id: ' . $var);
                                                    }

                                                    break;
                                                case 'secondary_image':
                                                case 'gallery1':
                                                case 'gallery2':
                                                case 'gallery3':
                                                case 'gallery4':
                                                case 'gallery5':
                                                case 'gallery6':
                                                case 'gallery7':
                                                    if (strlen($var) > 3) {
                                                        $pi = pathinfo($var);
                                                        $url = $pi['basename'];

                                                        if (array_search(strtolower($pi['extension']),
                                                                ['jpg', 'png', 'gif']) !== false) {

                                                            if ($thumbnailVideo == '') {
                                                                $thumbnailVideo = $var;
                                                            }

                                                            if (!file_exists(dirname(__FILE__) . '/../../../../../pub/media/catalog/product/' . $url)) {
                                                                file_put_contents(dirname(__FILE__) . '/../../../../../pub/media/catalog/product/' . $url,
                                                                    file_get_contents($var));
                                                                $this->collectOutput('... Downloading image: ' . $var);
                                                            }

                                                            if (array_search($url, $imagesInGallery) === false) {
                                                                $product->addImageToMediaGallery('catalog/product/' . $url,
                                                                    [], false, false);
                                                                $imagesInGallery[] = $url;
                                                                $this->collectOutput(
                                                                    $attribute . ': ' . 'catalog/product/' . $url);
                                                            } else {
                                                                $this->collectOutput(
                                                                    'Image already exists: ' . $url);
                                                            }

                                                        }
                                                        if (array_search(strtolower($pi['extension']),
                                                                ['mp4']) !== false) {

                                                            //we need to fix this soon

                                                            if (array_search($url, $imagesInGallery) === false) {

                                                                $this->setVideo(
                                                                    'http://tweezerman/',
                                                                    '99',
                                                                    $thumbnailVideo,
                                                                    $var,
                                                                    '',
                                                                    '',
                                                                    $sku,
                                                                    'l93r0f8dvp689c8gjetd7vblxy0v7nky'
                                                                );

                                                                $imagesInGallery[] = $url;
                                                                $this->collectOutput($attribute . ': ' . $var);
                                                            } else {
                                                                $this->collectOutput(
                                                                    'Video already exists: ' . $var);
                                                            }
                                                        }
                                                    }

                                                    break;
                                                case 'hover_image':

                                                    if (strlen($var) > 3) {
                                                        $pi = pathinfo($var);
                                                        $url = $pi['basename'];
                                                        if (array_search(strtolower($pi['extension']),
                                                                ['jpg', 'png', 'gif']) !== false) {
                                                            if (!file_exists(dirname(__FILE__) . '/../../../../../pub/media/catalog/product/' . $url)) {
                                                                file_put_contents(dirname(__FILE__) . '/../../../../../pub/media/catalog/product/' . $url,
                                                                    file_get_contents($var));
                                                                $this->collectOutput(
                                                                    '... Downloading image: ' . $var);
                                                            }
                                                            if (array_search($url, $imagesInGallery) === false) {
                                                                $product->addImageToMediaGallery('catalog/product/' . $url,
                                                                    ['hover_image'], false, true);
                                                                $imagesInGallery[] = $url;
                                                                $this->collectOutput(
                                                                    $attribute . ': ' . 'catalog/product/' . $url);
                                                            } else {
                                                                $this->collectOutput(
                                                                    'Image already exists: ' . $url);
                                                            }

                                                        }
                                                    }

                                                    break;

                                                case 'color_family':
                                                    $count = array_count_values(array_filter($attributes));
                                                    if (trim($var) == '' && $count[$attribute] > 1) {
                                                        //maybe it will still get an value
                                                    } else {
                                                        /** @var \Magento\Eav\Model\Entity\Attribute\AbstractAttribute $attr */
                                                        $attr = $product->getResource()->getAttribute('color_family');
                                                        if ($attr->usesSource()) {
                                                            $var = $attr->getSource()->getOptionId(trim($var));
                                                            $product->setData($attribute, $var);
                                                            $this->collectOutput($attribute . ': ' . $var);
                                                        }
                                                    }

                                                    break;

                                                default:

                                                    $count = array_count_values(array_filter($attributes));

                                                    if (trim($var) == '' && (array_key_exists($attribute,
                                                                $count) && $count[$attribute] > 1)) {
                                                        //maybe it will still get an value
                                                    } else {

                                                        $error = 0;
                                                        try {
                                                            $_attrib = $this->attributeRepository->get('4', $attribute);

                                                        } catch (NoSuchEntityException $e) {
                                                            $this->collectOutput(PHP_EOL . 'WARNING: ' . PHP_EOL . ' @@@@@ ' . $attribute . ' does not exist! ' . PHP_EOL);
                                                            $error = 1;
                                                        }

                                                        if ($error == 0) {
                                                            $product->setData($attribute, $var);
                                                            $this->collectOutput($attribute . ': ' . $var);
                                                        }

                                                    }
                                                    break;
                                            }

                                        } else {

                                            //image attribute
                                            $url = trim($columns[array_search($attribute, $attributes)]);
                                            if (strlen($url) > 3) {
                                                $pi = pathinfo($url);
                                                $var = $pi['basename'];


                                                if (array_key_exists('extension',
                                                        $pi) && array_search(strtolower($pi['extension']),
                                                        ['jpg', 'png', 'gif']) !== false) {

                                                    $product->setData(
                                                        $attribute,
                                                        $pi['basename']
                                                    );

                                                    $this->collectOutput($attribute . ': ' . $pi['basename']);

                                                    if (!file_exists(dirname(__FILE__) . '/../../../../../pub/media/catalog/product/' . $var)) {
                                                        file_put_contents(dirname(__FILE__) . '/../../../../../pub/media/catalog/product/' . $var,
                                                            file_get_contents($url));
                                                        $this->collectOutput('... Downloading image: ' . $url);
                                                    }
                                                }

                                            }
                                        }


                                    }
                                }
                            }

                            if (count($products) > 0) {
                                $this->collectOutput('- need to change the name');
                                $product->setName($name . '_' . $sku);
                            }

                            try {
                                $this->collectOutput('Trying to save');
                                $this->productRepository->save($product);
                            } catch (\Exception $e) {

                                switch ($e->getMessage()) {
                                    case 'URL key for specified store already exists.':
                                        $this->collectOutput('- need to change the name');
                                        $product->setName($name . '_' . $sku);
                                        $this->productRepository->save($product);
                                        break;
                                    default:
                                        $this->collectOutput('ERROR ' . $e->getMessage() . PHP_EOL);
                                        break;
                                }
                            }

                            $this->collectOutput('Done');
                            $this->collectOutput('----');

                            if ($product->getId()) {
                                $this->eventManager->dispatch(
                                    'salsifyimport_save_after',
                                    ['product' => $product]
                                );
                            }

                        } catch (\Exception $e) {
                            $this->collectOutput('Error: ' . $e->getMessage());
                            return;
                        }
                    } else {
                        $this->collectOutput('Skipped [empty value]');
                    }


                }
            }

            $this->moveFile($file);
        }

        $resultLog = '----
                    Ended ' . date('Y-m-d H:i:s') . ' [' . (time() - $start) . ' seconds]
                    Products checked: ' . $checkedProducts . '
                    Products created: ' . $createdProducts . '
                    Products updated: ' . $updatedProducts . '
                    Products not created[ignored]: ' . $createdIgnoredProducts . '
                    Products not updated[ignored]: ' . $updatedIgnoredProducts . '
                    ---';

        $this->collectOutput($resultLog, true);
        $this->allOutput['resultLog'] = $resultLog;

        $this->eventManager->dispatch(
            'salsifyimport_done',
            [
                'log' => [
                    'output' => $this->allOutput,
                    'files' => $processedFiles
                ]
            ]
        );
    }

    /**
     * Add video to product
     *
     * @param $domain
     * @param $IntToRepresentWhereInGalleryToPlaceIt
     * @param $thumbnailImageUrl
     * @param $videoURL
     * @param $videoTitle
     * @param $videoDescription
     * @param $YourProductSku
     * @param $MagentoIntegrationsAccessToken
     */
    public function setVideo(
        $domain,
        $IntToRepresentWhereInGalleryToPlaceIt,
        $thumbnailImageUrl,
        $videoURL,
        $videoTitle,
        $videoDescription,
        $YourProductSku,
        $MagentoIntegrationsAccessToken
    ) {

        $thumbnailImageUrl = dirname(__FILE__) . '/../../../../../pub/media/catalog/product/' . basename($thumbnailImageUrl);

        $ext = pathinfo($thumbnailImageUrl, PATHINFO_EXTENSION);

        if (array_search($ext, ['png', 'jpg']) === false) {
            return;
        }

        $data = [
            'entry' => [
                'id' => null,
                'media_type' => 'external-video',
                'label' => null,
                'position' => $IntToRepresentWhereInGalleryToPlaceIt,
                'types' => [],
                'disabled' => false,
                'content' => [
                    'base64_encoded_data' => base64_encode(file_get_contents($thumbnailImageUrl)),
                    'type' => 'image/' . $ext,
                    'name' => 'videothumbnail_' . basename($thumbnailImageUrl)
                ],
                'extension_attributes' => [
                    'video_content' => [
                        'media_type' => 'external-video',
                        'video_provider' => 'youtube',
                        'video_url' => $videoURL,
                        'video_title' => $videoTitle,
                        'video_description' => $videoDescription,
                        'video_metadata' => null
                    ]
                ]
            ]
        ];


        $ch = curl_init($domain . "/index.php/rest/V1/products/" . $YourProductSku . "/media");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER,
            array("Content-Type: application/json", "Authorization: Bearer " . $MagentoIntegrationsAccessToken));
        curl_exec($ch);
    }

    /**
     * @param $findElement
     * @param $arrayElement
     * @param $usedElements
     * @return false|int|string|null
     * @todo: add description
     *
     */
    public function findInArrayBasedInUse($findElement, $arrayElement, $usedElements)
    {
        if (array_search($findElement, $usedElements) === false || strpos($findElement, ',') !== false) {
            return array_search($findElement, $arrayElement);
        } else {
            $counting = array_count_values(array_filter($usedElements));

            $found = 0;
            foreach ($arrayElement as $ind => $ae) {

                if ($ae == $findElement) {
                    $found++;
                    if ($counting[$findElement] == $found - 1) {
                        return $ind;
                    }

                }
            }
        }

        return null;
    }

    /**
     * Really necessary?
     */
    private function initPhp()
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '-1');
        ignore_user_abort(true);
    }

    /**
     * Collect output by step and global
     *
     * @param $text
     * @param bool $finalResult
     */
    private function collectOutput($text, $finalResult = false)
    {
        $this->allOutput[$finalResult ? 'resultLog' : 'steps'][] = $text;
    }

    /**
     * Move processed file
     *
     * @return string|bool
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    private function moveFile($file)
    {
        try {
            $processedDir = $this->getProcessedDirectory();
            $finalPath = $processedDir . str_replace('var/salsify', '', $file);

            if (!$this->writeFactory->isExist($processedDir)) {
                $this->writeFactory->create($processedDir);
            }

            //Move to processed folder and then delete from root folder
            $this->writeFactory->copyFile($file, $finalPath);
            $this->writeFactory->delete(str_replace('processed/', '', $finalPath));
        } catch (\Exception $exception) {
            $this->collectOutput(sprintf('Error moving file %s to processed folder', $file));
            return false;
        }

        return false;
    }

    /**
     * Get salsify directory
     *
     * @return string
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    private function getDirectory()
    {
        return $this->dir->getPath('var') . DIRECTORY_SEPARATOR . self::DIRECTORY_PATH;
    }

    /**
     * Get processed salsify directory
     *
     * @return string
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    private function getProcessedDirectory()
    {
        return $this->dir->getPath('var') . DIRECTORY_SEPARATOR . self::DIRECTORY_PROCESSED_PATH;
    }
}
