<?php


namespace Henrique\Salsimport\Helper;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\File\Csv;
use Magento\Setup\Exception;
use function PHPSTORM_META\type;
use Symfony\Component\Console\Output\OutputInterface;

class Import extends AbstractHelper
{
    /**
     * Array with all output collected
     * @var array
     */
    var $allOutput;

    var $scopeConfig;
    var $productRepository;
    var $productFactory;
    var $productCollectionFactory;
    var $storeManager;
    var $attributeRepository;
    var $state;
    var $imageProcessor;
    var $csv;


    /**
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $_scopeConfig
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\App\Config\ScopeConfigInterface $_scopeConfig,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        // \Magento\Catalog\Api\Data\ProductInterfaceFactory $productFactory,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Framework\App\State $state,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Magento\Catalog\Model\Product\Gallery\Processor $imageProcessor,
        \Magento\Eav\Api\AttributeRepositoryInterface $attributeRepository,
        Csv $csv,
        \Magento\Store\Model\StoreManagerInterface $storeManager

    ) {
        $this->allOutput = null;
        $this->scopeConfig = $_scopeConfig;
        $this->productRepository = $productRepository;
        $this->productFactory = $productFactory;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->state = $state;
        $this->imageProcessor = $imageProcessor;
        $this->csv = $csv;
        $this->attributeRepository = $attributeRepository;
        $this->storeManager = $storeManager;

        parent::__construct($context);
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return (bool)$this->scopeConfig->getValue('salsify/salsify_import/salsify_import_enabled');
    }

    public function isModeInsert(){
        return (bool)$this->scopeConfig->getValue('salsify/salsify_import/salsify_mode_insert');
    }

    public function isModeUpdate(){
        return (bool)$this->scopeConfig->getValue('salsify/salsify_import/salsify_mode_update');
    }

    public function getCronjobTime(){
        return $this->scopeConfig->getValue('salsify/salsify_import/salsify_import_cronjob');
    }

    public function getDirectory(){
        return $this->scopeConfig->getValue('salsify/salsify_import/salsify_import_path');
    }

    public function getImageAttributes(){
        return array_map('trim', explode(PHP_EOL,$this->scopeConfig->getValue('salsify/salsify_import/salsify_import_image_attributes')));
    }

    public function getRelationAttributes(){

        $lines = explode(PHP_EOL, $this->scopeConfig->getValue('salsify/salsify_import/salsify_import_image_model'));
        $return = [];
        foreach($lines as $line){
            $parts = explode(',',$line);
            $parts[0] = trim($parts[0]);
            $parts[1] = trim($parts[1]);
            if( strlen($parts[0]) > 0 && strlen($parts[1]) > 0 )
                $return[] = [ $parts[0], $parts[1] ];
        }
        return $return;
    }

    public function findInArrayBasedInUse( $findElement, $arrayElement, $usedElements ){

        if( array_search($findElement,$usedElements) === false || strpos($findElement,',')!==false ){
            return array_search($findElement,$arrayElement);
        }else{

            $counting = array_count_values( array_filter($usedElements) );

            $found = 0;
            foreach( $arrayElement as $ind => $ae ){

                if($ae == $findElement){
                    $found++;
                    if($counting[$findElement] == $found-1){
                        return $ind;
                    }

                }
            }
        }
        return null;

    }

    public function output($output, $text, $finalResult = false)
    {
        $this->allOutput[$finalResult ? 'resultLog' : 'steps'][] = $text;

        if (get_class($output) == 'Symfony\Component\Console\Output\ConsoleOutput') {
            $output->writeLn($text);
        } else {
            echo $text . PHP_EOL;
        }
    }

    public function setVideo(
        $domain,
        $IntToRepresentWhereInGalleryToPlaceIt,
        $thumbnailimageUrl,
        $videoURL,
        $videoTitle,
        $videoDescription,
        $YourProductSku,
        $MagentoIntegrationsAccessToken
    ) {

        $thumbnailimageUrl = dirname(__FILE__) . '/../../../../../pub/media/catalog/product/'.basename($thumbnailimageUrl);

        $ext  = pathinfo($thumbnailimageUrl, PATHINFO_EXTENSION);

        if( array_search($ext,['png','jpg']) === false ) return;

        $data = [
            'entry' => [
                'id' => null,
                'media_type' => 'external-video',
                'label' => null,
                'position' => $IntToRepresentWhereInGalleryToPlaceIt,
                'types' => [],
                'disabled' => false,
                'content' => [
                    'base64_encoded_data' => base64_encode(file_get_contents($thumbnailimageUrl)),
                    'type' => 'image/'.$ext,
                    'name' => 'videothumbnail_'.basename($thumbnailimageUrl)
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
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json", "Authorization: Bearer " . $MagentoIntegrationsAccessToken));
        $response = curl_exec($ch);

    }

    public function importStart(
         $output
    ){

        @set_time_limit(0);
        @ini_set('memory_limit', '-1');
        ignore_user_abort(true);

        if( is_null($output) ) $this->output($output,'<pre>');

        $start = time();
        $this->allOutput = null;
        $products_checked = 0;
        $products_updated = 0;
        $products_created = 0;
        $products_update_ignored = 0;
        $products_created_ignored = 0;

        $this->output($output,'
----
Starting '.date('Y-m-d H:i:s').'
---
');
        try{
            $this->state->setAreaCode('frontend');
        }catch (\Exception $e){

            switch($e->getMessage()){
                case 'Area code is already set':
                break;
            }
        }



        //loading mapping
        $mapping = [];
        $fh   = $this->getRelationAttributes();
        foreach($fh as $v){
            if(array_key_exists($v[0],$mapping)){
                $mapping[$v[0]] .= ','.$v[1];
            }else{
                $mapping[$v[0]] = $v[1];
            }

        }

        //loading csvs
        $attributes = [];

        $csvURL = $this->scopeConfig->getValue('salsify/salsify_import/salsify_import_csv_url');

        if(is_null($csvURL) || strlen($csvURL) < 3){
            $files = array_diff(scandir($this->getDirectory()),['.','..']);
        }else{
            $files = [$csvURL];
        }

        $processedFiles = [];

        foreach($files as $file){
            $_file = pathinfo($file);
            if(
                strlen($csvURL) > 3 ||
                (array_key_exists('extension',$_file) && strtolower($_file['extension']) === 'csv' && substr($_file['basename'],0,1) != '.')
            ){

                $processedFiles[] = $file;
                $this->output($output,'-----');
                $this->output($output,'File: '.$file);
                $this->output($output,'-----');

                file_put_contents(dirname(__FILE__) . '/../../../../../var/salsify/csv/current.csv',
                    file_get_contents((is_null($csvURL) || strlen($csvURL) < 3) ? $this->getDirectory().$file : $csvURL )
                );

                $fh = $this->csv->getData(
                    dirname(__FILE__) . '/../../../../../var/salsify/csv/current.csv'
                );

                $n = -1;
                foreach($fh as $row=>$columns){

                    $n++;
                    if($n == 0){

                        foreach($columns as $_num=>$_column){
                            if( array_key_exists($_column,$mapping) === false ){
                                $attributes[$_num] = null;
                            }else{
                                $attributes[$_num] = $mapping[$_column];
                            }
                        }

                        if( array_search('sku',$attributes) === false ){
                            $this->output($output,'Error, the csv for mapping needs the sku field '.serialize($attributes));
                            return;
                        }

                        if( array_search('name',$attributes) === false ){
                            $this->output($output,'Error, the csv for mapping needs the name field '.serialize($attributes));
                            return;
                        }

                        continue;
                    }

                    $sku = $columns[array_search('sku',$attributes)];
                    $this->output($output,'SKU: '.$sku);

                    $name = $columns[array_search('name',$attributes)];
                    $this->output($output,'NAME: '.$name);

                    $storeId = $columns[array_search('store_id',$attributes)];
                    $this->output($output,'store_id: '.$storeId);

                    $this->storeManager->setCurrentStore($storeId);


                    $products = [];

                    if( strlen($sku) > 0 && strlen($name) > 0 ){

                        /** @var \Magento\Catalog\Model\Product\Interceptor $product */
                        try {
                            $product = $this->productRepository->get($sku, true, $storeId, true);

                            $this->output($output,'Updating...');
                            $products_checked++;
                            if(!$this->isModeUpdate()){
                                $this->output($output,'aborted [it is OFF in the admin panel]');
                                $products_update_ignored++;
                                continue;
                            }
                            $products_updated++;

                        } catch (\Magento\Framework\Exception\NoSuchEntityException $e){
                            $product = $this->productFactory->create();
                            $this->output($output,'Creating...');
                            $products_checked++;
                            if(!$this->isModeInsert()){
                                $this->output($output,'aborted [it is OFF in the admin panel]');
                                $products_created_ignored++;
                                continue;
                            }
                            $products_created++;

                            //Maybe we need to change the name (in salsify many products have the same name)
                            $collection = $this->productCollectionFactory->create();
                            $collection->addAttributeToFilter('name', array('like' =>strtoupper($name)));

                            $v = $collection->getSelect()->getPart('where');
                            $collection->getSelect()->setPart(
                                'where',
                                str_replace('(IF','UPPER(IF',$v)
                            );
                            $products = $collection->getData();
                        }

                        if( is_null($product->getPrice()) ){
                            $product->setPrice(0);
                        }

                        $product->setAttributeSetId(4); //default attribute set (?)



                        try {
                            $product->setSku($sku);

                            $product->setData(
                                'name',
                                $name
                            );

                            //clear Media Gallery
                            $images = $product->getMediaGalleryImages();
                            foreach($images as $child){
                                $this->imageProcessor->removeImage($product, $child->getFile());
                            }

                            $imagesInGallery = [];
                            $attributesUsed = [];
                            $thumbnailVideo = '';
                            //update attributes
                            foreach($attributes as $_attribute){
                                $attr = explode(',',$_attribute);
                                foreach($attr as $_v=>$attribute/*magento attribute*/){

                                    $var = '';
                                    if(!is_null($_attribute) && array_search($attribute,['sku','name']) === false){

                                        if(array_search($attribute, $this->getImageAttributes() ) === false) {


                                            //$var = $columns[$this->findInArrayBasedInUse($attribute,$attributes,$attributesUsed)];
                                            $var = $columns[$this->findInArrayBasedInUse($_attribute,$attributes,$attributesUsed)];

                                            $attributesUsed[] = $_attribute;



                                            switch($attribute){
                                                case 'primary_image':
                                                    if(strlen($var)>3){
                                                        $pi = pathinfo($var);
                                                        $thumbnailVideo = $var;

                                                        if(array_search(strtolower($pi['extension']),['jpg','png','gif']) !== false) {
                                                            $url = $pi['basename'];
                                                            if (!file_exists(dirname(__FILE__) . '/../../../../../pub/media/catalog/product/' . $url)) {
                                                                file_put_contents(dirname(__FILE__) . '/../../../../../pub/media/catalog/product/' . $url, file_get_contents($var));
                                                                $this->output($output,'... Downloading image: ' . $var);
                                                            }
                                                            $imagesInGallery[] = $url;
                                                            $product->addImageToMediaGallery('catalog/product/' . $url, ['image', 'small_image', 'thumbnail'], false, false);
                                                            $this->output($output, $attribute .': '.'catalog/product/' . $url);
                                                        }
                                                    }

                                                    break;
                                                case 'website_ids':
                                                    if(strlen($var)>0){
                                                        $product->setWebsiteIds(
                                                            array_filter(explode(',',$var))
                                                        );

                                                        $this->output($output, 'website_ids: '.$var);
                                                    }
                                                break;
                                                case 'store_id':
                                                    if(strlen($var)>0){
                                                        $product->setStoreId(
                                                            array_filter(explode(',',$var))
                                                        );
                                                        $this->output($output, 'store_id: '.$var);
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


                                                    if(strlen($var)>3) {
                                                        $pi = pathinfo($var);
                                                        $url = $pi['basename'];
                                                        if(array_search(strtolower($pi['extension']),['jpg','png','gif']) !== false) {

                                                            if($thumbnailVideo == ''){
                                                                $thumbnailVideo = $var;
                                                            }

                                                            if (!file_exists(dirname(__FILE__) . '/../../../../../pub/media/catalog/product/' . $url)) {
                                                                file_put_contents(dirname(__FILE__) . '/../../../../../pub/media/catalog/product/' . $url, file_get_contents($var));
                                                                $this->output($output,'... Downloading image: ' . $var);
                                                            }
                                                            if(array_search($url,$imagesInGallery)===false){
                                                                $product->addImageToMediaGallery('catalog/product/' . $url, [], false, false);
                                                                $imagesInGallery[] = $url;
                                                                $this->output($output, $attribute .': '.'catalog/product/' . $url);
                                                            }else{
                                                                $this->output($output,'Image already exists: ' . $url);
                                                            }

                                                        }
                                                        if(array_search(strtolower($pi['extension']),['mp4']) !== false) {

                                                            //we need to fix this soon

                                                            if(array_search($url,$imagesInGallery)===false){

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
                                                                $this->output($output, $attribute .': ' . $var);
                                                            }else{
                                                                $this->output($output,'Video already exists: ' . $var);
                                                            }
                                                        }
                                                    }

                                                    break;
                                                case 'hover_image':

                                                    if(strlen($var)>3) {
                                                        $pi = pathinfo($var);
                                                        $url = $pi['basename'];
                                                        if(array_search(strtolower($pi['extension']),['jpg','png','gif']) !== false) {
                                                            if (!file_exists(dirname(__FILE__) . '/../../../../../pub/media/catalog/product/' . $url)) {
                                                                file_put_contents(dirname(__FILE__) . '/../../../../../pub/media/catalog/product/' . $url, file_get_contents($var));
                                                                $this->output($output,'... Downloading image: ' . $var);
                                                            }
                                                            if(array_search($url,$imagesInGallery)===false){
                                                                $product->addImageToMediaGallery('catalog/product/' . $url, ['hover_image'], false, true);
                                                                $imagesInGallery[] = $url;
                                                                $this->output($output, $attribute .': '.'catalog/product/' . $url);
                                                            }else{
                                                                $this->output($output,'Image already exists: ' . $url);
                                                            }

                                                        }
                                                    }

                                                    break;

                                                case 'color_family':
                                                    $count = array_count_values( array_filter($attributes) );
                                                    if(trim($var) == '' && $count[$attribute]>1) {
                                                        //maybe it will still get an value
                                                    }else{
                                                        $attr = $product->getResource()->getAttribute('color_family');
                                                        if ($attr->usesSource()) {
                                                            $var = $attr->getSource()->getOptionId(trim($var));
                                                            $product->setData(
                                                                $attribute,
                                                                $var
                                                            );
                                                            $this->output($output, $attribute .': '.$var);
                                                        }
                                                    }

                                                    break;

                                                default:

                                                    $count = array_count_values( array_filter($attributes) );

                                                    if(trim($var) == '' && (array_key_exists($attribute,$count) && $count[$attribute]>1) ) {
                                                        //maybe it will still get an value
                                                    }else{

                                                        $error = 0;
                                                        try {
                                                            $_attrib = $this->attributeRepository->get('4',$attribute);

                                                        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                                                            $this->output($output, PHP_EOL . 'WARNING: '. PHP_EOL.' @@@@@ ' . $attribute .' does not exist! ' . PHP_EOL);
                                                            $error = 1;
                                                        }

                                                        if($error == 0){
                                                            $product->setData(
                                                                $attribute,
                                                                $var
                                                            );

                                                            $this->output($output, $attribute .': '.$var);
                                                        }

                                                    }
                                                    break;
                                            }

                                        }else{

                                            //image attribute
                                            $url = trim($columns[array_search($attribute, $attributes)]);
                                            if(strlen($url) > 3){
                                                $pi = pathinfo($url);
                                                $var = $pi['basename'];


                                                if( array_key_exists('extension',$pi) && array_search(strtolower($pi['extension']),['jpg','png','gif']) !== false) {

                                                    $product->setData(
                                                        $attribute,
                                                        $pi['basename']
                                                    );

                                                    $this->output($output, $attribute .': '.$pi['basename']);

                                                    if (!file_exists(dirname(__FILE__) . '/../../../../../pub/media/catalog/product/' . $var)) {
                                                        file_put_contents(dirname(__FILE__) . '/../../../../../pub/media/catalog/product/' . $var, file_get_contents($url));
                                                        $this->output($output,'... Downloading image: ' . $url);
                                                    }
                                                }

                                            }
                                        }



                                    }
                                }
                            }

                            if( count($products) > 0 ){
                                $this->output($output,'- need to change the name');
                                $product->setName( $name . '_' . $sku );
                            }
                            try{
                                $this->output($output,'Trying to save');


                                $this->productRepository->save(
                                    $product
                                );



                            } catch (\Exception $e){

                                switch($e->getMessage()){
                                    case 'URL key for specified store already exists.':
                                        $this->output($output,'- need to change the name');
                                        $product->setName( $name . '_' . $sku );
                                        $this->productRepository->save($product);
                                    break;
                                    default:
                                        $this->output($output,'ERROR '.$e->getMessage().PHP_EOL);
                                    break;
                                }
                            }
                            $this->output($output,'Done');
                            $this->output($output,'----');

                            if ($product->getId()) {
                                $this->_eventManager->dispatch(
                                    'salsifyimport_save_after',
                                    ['product' => $product]
                                );
                            }

                        } catch (\Exception $e){
                            $this->output($output,'Error: '.$e->getMessage());
                            return;
                        }
                    }else{
                        $this->output($output, 'Skipped [empty value]');
                    }


                }

                //move to backup
            }
        }

        $resultLog = '----
                    Ended ' . date('Y-m-d H:i:s') . ' [' . (time() - $start) . ' seconds]
                    Products checked: ' . $products_checked . '
                    Products created: ' . $products_created . '
                    Products updated: ' . $products_updated . '
                    Products not created[ignored]: ' . $products_created_ignored . '
                    Products not updated[ignored]: ' . $products_update_ignored . '
                    ---';

        $this->output($output, $resultLog, true);
        $this->allOutput['resultLog'] = $resultLog;

        if (!empty($product) && $product->getId()) {
            $this->_eventManager->dispatch(
                'salsifyimport_done',
                [
                    'output' => $this->allOutput,
                    'files' => $processedFiles
                ]
            );
        }

    }



}
