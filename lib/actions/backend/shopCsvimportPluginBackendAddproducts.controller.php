<?php

class shopCsvimportPluginBackendAddproductsController extends waLongActionController
{
    protected $product_model;
    /**
     * @var shopIndexSearch
     */

    public function execute()
    {
        try {
            parent::execute();
        } catch (waException $ex) {
            if ($ex->getCode() == '302') {
                echo json_encode(array('warning' => $ex->getMessage()));
            } else {
                echo json_encode(array('error' => $ex->getMessage()));
            }
        }
    }

    protected function finish($filename)
    {
        $this->info();
        if ($this->getRequest()->post('cleanup')) {
            return true;
        }
        return false;
    }
    
    protected function init()
    {
        $this->data['info'] = waRequest::post();
        $this->model = new waModel();
        $this->product_model = new shopProductModel();
        $this->feature_model =  new shopFeatureModel();
        $this->image = new shopProductImagesModel();
        
        $fp = fopen($this->data['info']['path'].$this->data['info']['name_file'],'r');
        
        $this->data['header'] = $this->convert(fgetcsv($fp,0,';'));
        $this->data['total_count'] = 0; 
        
        while( $line = fgetcsv($fp,0,';') )
          {
            $this->data['total_count']++;
          }
        fclose($fp);
        
        foreach($this->data['info'] as $key => $header)
        {
            $data = explode(':', $header);
            if(is_numeric($key))
            {
                if($data[0] == 'skus')
                {
                    if($data[2] == 'stock')
                    {
                        $this->data['skus']['stocks'][$data[3]] = $key;
                        if($this->data['info']['regim'] == 4){
                            $this->model->query("UPDATE shop_product_stocks SET count='0' WHERE stock_id = '".$data[3]."'");
                        }
                    }
                    else
                    {
                        $this->data['skus'][$data[2]] = $key;
                    }
                }
                else if($data[0] == 'features')
                {
                    $feats = $this->feature_model->getByCode($data[1]);
                    $this->data['features'][$header]['name'] = $feats['name'];
                    $this->data['features'][$header]['code'] = $data[1];
                    $this->data['features'][$header]['key'] = $key;
                }
                else if($data[0] == 'images')
                {
                        $this->data['images'][] = $key;
                }
                else
                {
                    $this->data['products'][$data[0]] = $key;
                                    
                }                
            }
        }
        $this->data['offset'] = 0;
        $this->data['ready'] = true;
        $this->data['timestamp'] = time();
        
    }

    protected function restore()
    {
        $this->product_model = new shopProductModel();
        $this->model = new waModel();
        $this->feature_model =  new shopFeatureModel();
        $this->image = new shopProductImagesModel();
    }
    
    protected function convert($str) 
    {
        foreach($str as $key => $s)
        {
            if($this->data['info']['charset'] == 'Windows-1251') {
                $string[$key] = iconv("Windows-1251", "UTF-8", $s);
            } else {
                $string[$key] = $s;
            }
        }
        return $string;
    }
    
    protected function reconvert($str) 
    {
        foreach($str as $key => $s)
        {
            $string[$key] = iconv("UTF-8", "Windows-1251", $s);
        }
        return $string;
    }


    protected function isDone()
    {
        return $this->data['offset'] >= $this->data['total_count'];
    }
    
    protected function getProductId($csvInfo)
    {
        $identifierId = $this->data['info']['id'];
        $explodeData = explode(':', $this->data['info'][$identifierId]);
        if($explodeData[1])
        {
            if($explodeData[0] == 'features')
            {
                $feats = $this->feature_model->getByCode($explodeData[1]);
                    
                $result = explode('.',$feats['type']);
                $and = $result[1] ? "AND type='".$result[1]."' " : "" ;
                $values = $this->model->query("SELECT * FROM shop_feature_values_".$result[0]." WHERE feature_id = '".$feats['id']."' ".$and)->fetchAll();
                $val = array();
                if($values)
                {
                    foreach($values as $v)
                    {
                        if(!empty($csvInfo[$identifierId]))
                        {
                            if($csvInfo[$identifierId] == $v['value'])
                            {
                                return $this->model->query("SELECT product_id FROM shop_product_features WHERE feature_id='".$v['feature_id']."' AND feature_value_id='".$v['id']."'")->fetchField();
                            }
                        }
                    }
                }
                return false;
            } 
            elseif($explodeData[0] == 'skus')
            {
                $where = $explodeData[2].'='.$csvInfo[$identifierId];
                return $this->model->query("SELECT product_id FROM shop_product_skus WHERE ".$where)->fetchField();
            }
            else
            {
                return false;
            }
        }
        else
        {
            return $this->model->query("SELECT id FROM shop_product WHERE ".$explodeData[0]." = '".$csvInfo[$identifierId]."'")->fetchField();
        }
    }
    
    protected function step()
    {
        $limit = 100;
        $start = $this->data['offset'];
        $fp = fopen($this->data['info']['path'].$this->data['info']['name_file'],'r');
        $this->data['header'] = $this->convert(fgetcsv($fp,0,';'));
        $indicator = 0;
        while($line = fgetcsv($fp,0,';'))
        {
            
            if($indicator >= $this->data['offset'])
            {
                if(($this->data['offset'] - $start) <= $limit){
                    $csvInfo = $this->convert($line);
                    $productId = $this->getProductId($csvInfo);
                    
                    $data = array();
                    foreach($this->data['products'] as $key => $value)
                    {
                        $data[$key] = $csvInfo[$value];
                    }
                                       
                        $data['category_id'] = 17;
                        
                        if($productId && $this->data['info']['checkbox']['updateProd']){                        
                            $product_model = new shopProductModel();
                            $product = $product_model->getById($productId);
                            
                            $p = new shopProduct($product);
                            $p->save($data, true, $errors);
                            
                            $category_products_model = new shopCategoryProductsModel();
                            $category_products_model->add($productId, 17);
                            
                            $skuName = $csvInfo[$this->data['info']['skuId'][1]];
                        
                            foreach($this->data['info']['separator'] as $key => $sku)
                            {
                                $skuName .= $sku;
                                $skuName .= $csvInfo[$this->data['info']['skuId'][$key]];
                            }
                            
                            
                            if(is_array($this->data['skus']))
                            {
                                $skuData = array();    
                                
                                $skus_model = new shopProductSkusModel();
                                
                                $skuId = $this->model->query("SELECT id FROM shop_product_skus WHERE sku = '".$skuName."' AND product_id = '".$productId."'")->fetchField();
                                
                                foreach($this->data['skus'] as $key => $value)
                                {
                                    if($key == 'stocks'){
                                        if($this->data['info']['regim'] == 1 || $this->data['info']['regim'] == 4){
                                            $skuData['count'] = $csvInfo[$value[0]] ? $csvInfo[$value[0]] : '';
                                            unset($this->data['skus']['stocks'][0]);
                                            if(count($this->data['skus']['stocks']))
                                            {
                                                foreach($this->data['skus']['stocks'] as $stock_id => $stock)
                                                {
                                                    if($csvInfo[$stock] || $csvInfo[$stock] === 0)
                                                    {
                                                        $skuData['stock'][$stock_id] = $csvInfo[$stock];
                                                    }
                                                }
                                            }
                                        } elseif ($this->data['info']['regim'] == 2) {
                                            $skuInfo = $skus_model->getSku($skuId);
                                            if($skuInfo){
                                                $count = $skuInfo['count'];}
                                            
                                            if($csvInfo[$value[0]] || $csvInfo[$value[0]] === 0)
                                            {
                                                $skuData['count'] = $csvInfo[$value[0]] + $count;
                                            }   
                                            unset($this->data['skus']['stocks'][0]); unset($count);
                                            if(count($this->data['skus']['stocks']))
                                            {
                                                foreach($this->data['skus']['stocks'] as $stock_id => $stock)
                                                {
                                                    if($skuInfo){
                                                        $count = $skuInfo['stock'][$stock_id] ? $skuInfo['stock'][$stock_id] : 0;}
                                                    if($csvInfo[$stock] || $csvInfo[$stock] === 0){
                                                        $skuData['stock'][$stock_id] = $csvInfo[$stock] + $count;
                                                    }  
                                                    unset($count);
                                                }
                                            }
                                        } elseif ($this->data['info']['regim'] == 3) {
                                            $skuInfo = $skus_model->getSku($skuId);
                                            if($skuInfo){
                                                $count = $skuInfo['count'] ? $skuInfo['count'] : 0;}
                                                
                                            $skuData['count'] = $csvInfo[$value[0]] - $count;
                                            unset($this->data['skus']['stocks'][0]); unset($count);
                                            if(count($this->data['skus']['stocks']))
                                            {
                                                foreach($this->data['skus']['stocks'] as $stock_id => $stock)
                                                {
                                                    if($skuInfo){
                                                        $count = $skuInfo['stock'][$stock_id] ? $skuInfo['stock'][$stock_id] : 0;}
                                                    if($csvInfo[$stock] || $csvInfo[$stock] === 0){
                                                        $skuData['stock'][$stock_id] = $count - $csvInfo[$stock];
                                                    }
                                                    unset($count);
                                                }
                                            }
                                        }
                                    } else {
                                        $skuData[$key] = $csvInfo[$value];
                                    }  
                                }
                                $skuData['sku'] = $skuName;
                                
                                
                                
                                if($skuId && $this->data['info']['checkbox']['updateSkus'])
                                {
                                    $skus_model->update($skuId, $skuData);
                                }
                                else
                                {
                                    if($this->data['info']['checkbox']['newSkus'] && !$skuId)
                                    {
                                        $skuId = $this->model->query("SELECT id FROM shop_product_skus WHERE sku = '' AND product_id = '".$productId."'")->fetchField();
                                        if($skuId) {
                                            $skus_model->update($skuId, $skuData);
                                        } else {
                                            $skuData['product_id'] = $productId;
                                            if ($sku = $skus_model->add($skuData)) {
                                                $skuId = $sku['id'];
                                            } 
                                        }
                                    }
                                } 
                            }
                            
                            if($this->data['images'])
                            {
                                foreach($this->data['images'] as $value)
                                {
                                    $images[] = $csvInfo[$value];
                                    $this->addImages($images, $productId);
                                }
                            }
                            
                            if($this->data['features'])
                            {
                                $dataFeat = array();
                                $features_model =  new shopFeatureModel();
                                foreach($this->data['features'] as $features)
                                {
                                    $feats = $features_model->getByCode($features['code']);
                    
                                    $result = explode('.',$feats['type']);
                                    $and = $result[1] ? "AND type='".$result[1]."' " : "" ;
                                    $values = $this->model->query("SELECT value FROM shop_feature_values_".$result[0]." WHERE feature_id = '".$feats['id']."' ".$and)->fetchAll();
                                    $val = array();
                                    if($values){
                                        foreach($values as $v){
                                            $val[] = $v['value'];
                                        }
                                    }
                                    
                                    if($csvInfo[$features['key']]){
                                        if(in_array($csvInfo[$features['key']], $val)){
                                            $dataFeat[$features['code']] = $csvInfo[$features['key']];
                                        } else {
                                            if($this->data['info']['checkbox']['features'][$features['code']]){
                                                $dataFeat[$features['code']] = $csvInfo[$features['key']];
                                            }
                                        }
                                    }
                                    unset($val); 
                                }
                                $feature_model = new shopProductFeaturesModel();
                                $feature_model->setData($p,$dataFeat); unset($dataFeat); 
                            }
                        }
                        else
                        {
                            if($this->data['info']['checkbox']['newProd'])
                            {
                                $p = new shopProduct();
                                if ($p->save($data, true, $errors)) {
                                $productId = $p->getId();
                                
                                $category_products_model = new shopCategoryProductsModel();
                                $category_products_model->add($productId, 17);
                                
                                if(is_array($this->data['skus']))
                                {
                                    $skuData = array();
                                    
                                    $skuName = $csvInfo[$this->data['info']['skuId'][1]];
                            
                                    foreach($this->data['info']['separator'] as $key => $sku)
                                    {
                                        $skuName .= $sku;
                                        $skuName .= $csvInfo[$this->data['info']['skuId'][$key]];
                                    }
                                    
                                    $skus_model = new shopProductSkusModel();
                                    
                                    $skuId = $this->model->query("SELECT id FROM shop_product_skus WHERE sku = '".$skuName."' AND product_id = '".$productId."'")->fetchField();
                                
                                    foreach($this->data['skus'] as $key => $value)
                                    {
                                        if($key == 'stocks'){
                                            if($csvInfo[$value[0]]){
                                                $skuData['count'] = $csvInfo[$value[0]];
                                            }
                                            
                                            unset($this->data['skus']['stocks'][0]);
                                            if(count($this->data['skus']['stocks']))
                                            {
                                                foreach($this->data['skus']['stocks'] as $stock_id => $stock)
                                                {
                                                    if($csvInfo[$stock]){
                                                        $skuData['stock'][$stock_id] = $csvInfo[$stock];
                                                    }
                                                }
                                            }
                                        } else {
                                            $skuData[$key] = $csvInfo[$value];
                                        }
                                    }
                                    $skuData['sku'] = $skuName;
                                    
                                    
                                    if($skuId && $this->data['info']['checkbox']['updateSkus'])
                                    {
                                        $skus_model->update($skuId, $skuData);
                                    }
                                    else
                                    {
                                        if($this->data['info']['checkbox']['newSkus'] && !$skuId)
                                        {
                                            $skuId = $this->model->query("SELECT id FROM shop_product_skus WHERE sku = '' AND product_id = '".$productId."'")->fetchField();
                                            if($skuId) {
                                                $skus_model->update($skuId, $skuData);
                                            } else {
                                                $skuData['product_id'] = $productId;
                                                if ($sku = $skus_model->add($skuData)) {
                                                    $skuId = $sku['id'];
                                                }
                                            } 
                                        }
                                    }
                                } 
                            }
                            
                            if($this->data['features'])
                            {
                                $dataFeat = array();
                                $features_model =  new shopFeatureModel();
                                foreach($this->data['features'] as $features)
                                {
                                    $feats = $features_model->getByCode($features['code']);
                    
                                    $result = explode('.',$feats['type']);
                                    $and = $result[1] ? "AND type='".$result[1]."' " : "" ;
                                    $values = $this->model->query("SELECT value FROM shop_feature_values_".$result[0]." WHERE feature_id = '".$feats['id']."' ".$and)->fetchAll();
                                    $val = array();
                                    if($values){
                                        foreach($values as $v){
                                            $val[] = $v['value'];
                                        }
                                    }
                                    
                                    if($csvInfo[$features['key']]){
                                        if(in_array($csvInfo[$features['key']], $val)){
                                            $dataFeat[$features['code']] = $csvInfo[$features['key']];
                                        } else {
                                            if($this->data['info']['checkbox']['features'][$features['code']]){
                                                $dataFeat[$features['code']] = $csvInfo[$features['key']];
                                            }
                                        }
                                    }
                                    unset($val); 
                                }
                                $feature_model = new shopProductFeaturesModel();
                                $feature_model->setData($p,$dataFeat); unset($dataFeat); 
                            }
                            
                            if($this->data['images'])
                            {
                                foreach($this->data['images'] as $value)
                                {
                                    $images[] = $csvInfo[$value];
                                    $this->addImages($images, $productId);
                                    unset($images);
                                }
                            }
                        }
                    } 
                } else {
                    break;}
                $this->data['offset'] += 1;
            }
            $indicator++;
        }
        fclose($fp);
        
        if($this->data['offset'] >= $this->data['total_count']) 
        break;
        sleep(1);
    }


    protected function info()
    {
        $interval = 0;
        if (!empty($this->data['timestamp'])) {
            $interval = time() - $this->data['timestamp'];
        }
        $response = array(
            'time'       => sprintf('%d:%02d:%02d', floor($interval / 3600), floor($interval / 60) % 60, $interval % 60),
            'processId'  => $this->processId,
            'progress'   => 0.0,
            'ready'      => $this->isDone(),
            'name'       => $this->data,
            'log_path'   => wa()->getConfig()->getPath('log'),
            'offset' => $this->data['offset'],
        );
        $response['progress'] = ($this->data['offset'] / $this->data['total_count']) * 100;
        $response['progress'] = sprintf('%0.3f%%', $response['progress']);
        
        if ($this->getRequest()->post('cleanup')) {
            if($this->data['info']['name_file'] == 'addedSkus.csv') {
                waFiles::delete($this->data['info']['path'].$this->data['info']['name_file']);
            }
            $response['report'] = $this->report();
        }
        
        echo json_encode($response);
    }
    
    protected function addImages($images, $p_id)
    {
        foreach($images as $imageUrl)
        {
            if($imageUrl)
            {
                $search = array(
                    'product_id' => $p_id,
                    'ext'        => pathinfo(urldecode($imageUrl), PATHINFO_EXTENSION),
                );
                
                $name = preg_replace('@[^a-zA-ZР°-СЏРђ-РЇ0-9\._\-]+@', '', basename(urldecode($imageUrl)));
                if (empty($search['ext']) || !in_array($search['ext'], array('jpeg', 'jpg', 'png', 'gif'))) {
                    $search['ext'] = 'jpeg';
                    $name .= '.'.$search['ext'];
                }
                
                $pattern = sprintf('@/(%d)/images/(\\d+)/\\2\\.(\\d+(x\\d+)?)\\.([^\\.]+)$@', $search['product_id']);
                if (preg_match($pattern, $imageUrl, $matches)) {
                    $image = array(
                        'product_id' => $matches[1],
                        'id'         => $matches[2],
                        'ext'        => $matches[5],
                    );
                    
                    if ((strpos($imageUrl, shopImage::getUrl($image, $matches[3])) !== false) && $model->getByField($image)) {
                        #skip local file
                        $target = 'skip';
                        $imageUrl = null;
                    }
                }
                
                if ($imageUrl) {
                    $upload_file = wa()->getTempPath('csv/upload/images/');
                    $upload_file .= waLocale::transliterate($name, 'en_US');
                    waFiles::upload($imageUrl, $upload_file);
                    $imageUrl = $upload_file;
                }
                
                
                if ($imageUrl && file_exists($imageUrl)) {
                    if ($image = waImage::factory($imageUrl)) {
                        $search['original_filename'] = $name;
                        $data = array(
                            'product_id'        => $p_id,
                            'upload_datetime'   => date('Y-m-d H:i:s'),
                            'width'             => $image->width,
                            'height'            => $image->height,
                            'size'              => filesize($imageUrl),
                            'original_filename' => $name,
                            'ext'               => pathinfo($imageUrl, PATHINFO_EXTENSION),
                        );
                        if ($exists = $this->image->getByField($search)) {
                            $data = array_merge($exists, $data);
                            $thumb_dir = shopImage::getThumbsPath($data);
                            $back_thumb_dir = preg_replace('@(/$|$)@', '.back$1', $thumb_dir, 1);
                            $paths[] = $back_thumb_dir;
                            waFiles::delete($back_thumb_dir); 
                            if (file_exists($thumb_dir)) {
                                if (!(waFiles::move($thumb_dir, $back_thumb_dir) || waFiles::delete($back_thumb_dir)) && !waFiles::delete($thumb_dir)) {
                                    throw new waException(_w("Error while rebuild thumbnails"));
                                }
                            }
    
                        }
    
                        $image_changed = false;            
    
                        if (empty($data['id'])) {
                            $image_id = $data['id'] = $this->image->add($data);
                        } else {
                            $image_id = $data['id'];
                            $target = 'update';
                            $this->image->updateById($image_id, $data);
                        }
    
                        if (!$image_id) {
                            throw new waException("Database error");
                        }
    
                        $image_path = shopImage::getPath($data);
                        if ((file_exists($image_path) && !is_writable($image_path)) || (!file_exists($image_path) && !waFiles::create($image_path))) {
                            $this->image->deleteById($image_id);
                        }
    
                        if ($image_changed) {
                            $image->save($image_path);
                            /**
                             * @var shopConfig $config
                             */
                            $config = wa('shop')->getConfig();
                            if ($config->getOption('image_save_original') && ($original_file = shopImage::getOriginalPath($data))) {
                                waFiles::copy($imageUrl, $original_file);
                            }
                        } else {
                            waFiles::copy($imageUrl, $image_path);
                        }
                    }             
                }
            }
        }
    }
    
    protected function report()
    {
        $report = '<div class="field">';
        $report .= '<div class="value">';
        $report .= '<span id="re_succes" style="color: green; font-weight: bold; font-style: italic;display: none;"> Файл успешно обновлен</span>';
        $report .= '</div>';
        $report .= '</div>';        
        
        
        return $report;
    }
    
    private function error($message)
    {
        $path = wa()->getConfig()->getPath('log');
        waFiles::create($path.'/shop/csvimport.log');
        waLog::log($message, 'shop/csvimport.log');
    }    
}