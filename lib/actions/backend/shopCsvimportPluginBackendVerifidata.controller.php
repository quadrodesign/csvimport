<?php

class shopCsvimportPluginBackendVerifidataController extends waLongActionController
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
        $fp = fopen($this->data['info']['path'].$this->data['info']['name_file'],'r');
        
        $this->data['header'] = $this->convert(fgetcsv($fp,0,';'));
        $this->data['total_count'] = 0; 
        
        while( $line = fgetcsv($fp,0,';') ) {
            $this->data['total_count']++;
        }
        fclose($fp);
        
        foreach($this->data['info'] as $key => $header) {
            $pos = strpos((string)$header, 'features');
            if($pos !== false) {
                $ident = explode(':',(string)$header);
                if ($ident[1] != '') {
                    $this->data['features'][$header]['key'] = $key;
                    $feats = $this->feature_model->getByCode($ident[1]);
                    
                    $result = explode('.',$feats['type']);
                    $and = isset($result[1]) ? "AND type='".$result[1]."' " : "" ;
                    $values = $this->model->query("SELECT value FROM shop_feature_values_".$result[0]." WHERE feature_id = '".$feats['id']."' ".$and)->fetchAll();
                    $val = array();
                    if($values) {
                        foreach($values as $v) {
                            $val[] = $v['value'];
                        }
                    }
                    
                    $this->data['features'][$header]['values'] = $val;//array_values($this->feature_model->getFeatureValues($feat));
                    $this->data['features'][$header]['code'] = $ident[1];
                    $this->data['features'][$header]['count'] = 0;
                    $this->data['features'][$header]['name'] = $feats['name'];
                    $feat = fopen($this->data['info']['path'].'new'.ucfirst($ident[1]).'.csv','w');
                    fputcsv($feat, $this->reconvert($this->data['header']), ';');
                    fclose($feat);
                    unset($val);
                }
                
            }
            
            if($this->data['info']['regim'] == 3) {
                $data = explode(':', $header);
                if(is_numeric($key)) {
                    if($data[0] == 'skus') {
                        if($data[2] == 'stock') {
                            $this->data['skus']['stocks'][$data[3]] = $key;
                        }
                    }              
                }
            }
        }
        
        $config = $this->model->query("SELECT data FROM shop_csvimport_config WHERE name='".$this->data['info']['configName']."'")->fetchField();
        $this->data['info']['config'] = $config ? (array)json_decode($config) : '';
        
        $upProd = fopen($this->data['info']['path'].'updateProd.csv','w');
        fputcsv($upProd, $this->reconvert($this->data['header']), ';');
        fclose($upProd);
        
        $ostat = fopen($this->data['info']['path'].'ostat.csv','w');
        fputcsv($ostat, $this->reconvert($this->data['header']), ';');
        fclose($ostat);
        
        $newProd = fopen($this->data['info']['path'].'newProd.csv','w');
        fputcsv($newProd, $this->reconvert($this->data['header']), ';');
        fclose($newProd);
        
        $upSkus = fopen($this->data['info']['path'].'updateSkus.csv','w');
        fputcsv($upSkus, $this->reconvert($this->data['header']), ';');
        fclose($upSkus);
        
        $newSkus = fopen($this->data['info']['path'].'newSkus.csv','w');
        fputcsv($newSkus, $this->reconvert($this->data['header']), ';');
        fclose($newSkus);
        
        $notFeat = fopen($this->data['info']['path'].'notFeat.csv','w');
        fputcsv($notFeat, $this->reconvert($this->data['header']), ';');
        fclose($notFeat);
        
        $notFoundFeat = fopen($this->data['info']['path'].'notFoundFeat.csv','w');
        fputcsv($notFoundFeat, $this->reconvert($this->data['header']), ';');
        fclose($notFoundFeat);
        
        $this->data['newProduct'] = array(); 
        $this->data['ostat'] = 0;
        $this->data['newSkus'] = 0; 
        $this->data['updateProduct'] = array(); 
        $this->data['updateSkus'] = 0;
        $this->data['notFeat'] = 0;
        $this->data['notFoundFeat'] = 0;
        $this->data['offset'] = 0;
        $this->data['ready'] = true;
        $this->data['timestamp'] = time();
        
    }

    protected function restore()
    {
        $this->product_model = new shopProductModel();
        $this->model = new waModel();
        $this->feature_model =  new shopFeatureModel();
    }
    
    protected function convert($str) 
    {
        foreach($str as $key => $s) {
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
        foreach($str as $key => $s) {
            $string[$key] = iconv("UTF-8", "Windows-1251//IGNORE", $s);
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
        if(isset($explodeData[1])) {
            if($explodeData[0] == 'features') {
                $feats = $this->feature_model->getByCode($explodeData[1]);
                    
                $result = explode('.',$feats['type']);
                $and = isset($result[1]) ? "AND type='".$result[1]."' " : "" ;
                $values = $this->model->query("SELECT * FROM shop_feature_values_".$result[0]." WHERE feature_id = '".$feats['id']."' ".$and)->fetchAll();
                $val = array();
                if($values) {
                    foreach($values as $v) {
                        if(!empty($csvInfo[$identifierId])) {
                            if($csvInfo[$identifierId] == $v['value']) {
                                return $this->model->query("SELECT product_id FROM shop_product_features WHERE feature_id='".$v['feature_id']."' AND feature_value_id='".$v['id']."'")->fetchField();
                            }
                        }
                    }
                }
                return false;
            } elseif($explodeData[0] == 'skus') {
                $where = $explodeData[2].'='.$csvInfo[$identifierId];
                return $this->model->query("SELECT product_id FROM shop_product_skus WHERE ".$where)->fetchField();
            } else {
                return false;
            }
        } else {
            return $this->model->query("SELECT id FROM shop_product WHERE ".$explodeData[0]." = '".mysql_escape_string($csvInfo[$identifierId])."'")->fetchField();
        }
    }
    
    protected function step()
    {
        $limit = 100;
        $start = $this->data['offset'];
        $fp = fopen($this->data['info']['path'].$this->data['info']['name_file'],'r');
        $this->data['header'] = $this->convert(fgetcsv($fp,0,';'));
        $indicator = 0;
        while($line = fgetcsv($fp,0,';')) {
            if($indicator >= $this->data['offset']) {
                if(($this->data['offset'] - $start) <= $limit){
                    $csvInfo = $this->convert($line);
                    $productId = $this->getProductId($csvInfo);
                    
                    if($productId){
                        $this->data['updateProduct'][$productId] = 1;
                        $upProd = fopen($this->data['info']['path'].'updateProd.csv','a');
                        if($this->data['info']['charset'] == 'UTF-8') {
                            fputcsv($upProd, $csvInfo, ';');
                        } else {
                            fputcsv($upProd, $this->reconvert($csvInfo), ';');
                        }
                        fclose($upProd);
                                              
                        $skuName = $csvInfo[$this->data['info']['skuId'][1]];
                        if(isset($this->data['info']['separator'])) {
                            foreach($this->data['info']['separator'] as $key => $sku) {
                                $skuName .= $sku;
                                $skuName .= $csvInfo[$this->data['info']['skuId'][$key]];
                            }
                        }
                        
                        $skuId = $this->model->query("SELECT id FROM shop_product_skus WHERE sku = '".$skuName."' AND product_id = '".$productId."'")->fetchField();
                        if($skuId) {
                            if($this->data['info']['regim'] == 3) {
                                if(is_array($this->data['skus']['stocks'])) {
                                    $skus_model = new shopProductSkusModel();
                                    $skuInfo = $skus_model->getSku($skuId);
                                    unset($this->data['skus']['stocks'][0]);
                                    $i = false;
                                    foreach($this->data['skus']['stocks'] as $stock_id => $stock) {
                                        if($skuInfo){
                                            $count = $skuInfo['stock'][$stock_id] ? $skuInfo['stock'][$stock_id] : 0;}
                                        if($csvInfo[$stock]){
                                            $csvInfo[$stock] = $count - $csvInfo[$stock];
                                        }
                                        
                                        if($csvInfo[$stock] < 0) {
                                            $i = true;
                                        }
                                        unset($count);
                                    }
                                    
                                    if($i) {
                                        $this->data['ostat']++;
                                        $ostat = fopen($this->data['info']['path'].'ostat.csv','a');
                                        if($this->data['info']['charset'] == 'UTF-8') {
                                            fputcsv($ostat, $csvInfo, ';');
                                        } else {
                                            fputcsv($ostat, $this->reconvert($csvInfo), ';');
                                        }
                                        fclose($ostat);
                                    }
                                }
                            }
                                
                            $this->data['updateSkus']++;
                            $upSkus = fopen($this->data['info']['path'].'updateSkus.csv','a');
                            if($this->data['info']['charset'] == 'UTF-8') {
                                fputcsv($upSkus, $csvInfo, ';');
                            } else {
                                fputcsv($upSkus, $this->reconvert($csvInfo), ';');
                            }
                            fclose($upSkus);
                        } else {
                            $this->data['newSkus']++;
                            $newSkus = fopen($this->data['info']['path'].'newSkus.csv','a');
                            if($this->data['info']['charset'] == 'UTF-8') {
                            fputcsv($newSkus, $csvInfo, ';');
                            } else {
                                fputcsv($newSkus, $this->reconvert($csvInfo), ';');
                            }
                            fclose($newSkus);
                        }
                    } else {
                        $this->data['newProduct'][md5($csvInfo[$this->data['info']['id']])] = 1;
                        $this->data['newSkus']++;
                        
                        $newSkus = fopen($this->data['info']['path'].'newSkus.csv','a');
                        if($this->data['info']['charset'] == 'UTF-8') {
                            fputcsv($newSkus, $csvInfo, ';');
                        } else {
                            fputcsv($newSkus, $this->reconvert($csvInfo), ';');
                        }
                        fclose($newSkus);
                        
                        $newProd = fopen($this->data['info']['path'].'newProd.csv','a');
                        if($this->data['info']['charset'] == 'UTF-8') {
                            fputcsv($newProd, $csvInfo, ';');
                        } else {
                            fputcsv($newProd, $this->reconvert($csvInfo), ';');
                        }
                        fclose($newProd);
                    }
                    
                    if(isset($this->data['features'])) {
                        foreach($this->data['features'] as $key => $feature) {
                            if(!empty($csvInfo[$feature['key']])) {
                                if(is_numeric($this->data['info']['id_razmer'])) {
                                    if($feature['key'] == $this->data['info']['id_razmer'] && $productId) {
                                        if($size = shopTablesizePlugin::getSiteSize($productId,$csvInfo[$feature['key']])) {
                                            $csvInfo[$feature['key']] = $size;
                                        } else {
                                            $this->data['notFeat']++;
                                            $notFeat = fopen($this->data['info']['path'].'notFeat.csv','a');
                                            if($this->data['info']['charset'] == 'UTF-8') {
                                                fputcsv($notFeat, $csvInfo, ';');
                                            } else {
                                                fputcsv($notFeat, $this->reconvert($csvInfo), ';');
                                            }
                                            fclose($notFeat);
                                        }
                                    } elseif($feature['key'] == $this->data['info']['id_razmer'] && !$productId) {
                                        $this->data['notFoundFeat']++;
                                        $notFoundFeat = fopen($this->data['info']['path'].'notFoundFeat.csv','a');
                                        if($this->data['info']['charset'] == 'UTF-8') {
                                            fputcsv($notFoundFeat, $csvInfo, ';');
                                        } else {
                                            fputcsv($notFoundFeat, $this->reconvert($csvInfo), ';');
                                        }
                                        fclose($notFoundFeat);
                                    }
                                }
                                if(!in_array($csvInfo[$feature['key']], $feature['values'])) {
                                    $this->data['features'][$key]['count']++;
                                    //$this->data['features'][$key]['info'] = $this->data['features'];
                                    $ident = explode(':',$key);
                                    $feat = fopen($this->data['info']['path'].'new'.ucfirst($ident[1]).'.csv','a');
                                    if($this->data['info']['charset'] == 'UTF-8') {
                                        fputcsv($feat, $csvInfo, ';');
                                    } else {
                                        fputcsv($feat, $this->reconvert($csvInfo), ';');
                                    }
                                    fclose($feat);
                                }
                            }
                        }
                    }
                } else { break;}
                $this->data['offset'] += 1;
            }
            $indicator++;
            if($this->data['offset'] >= $this->data['total_count']) {
                break;
            }
        }
        fclose($fp);
        
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
            'offset' => $this->data['offset'],
        );
        $response['progress'] = ($this->data['offset'] / $this->data['total_count']) * 100;
        $response['progress'] = sprintf('%0.3f%%', $response['progress']);
        
        if ($this->getRequest()->post('cleanup')) {
            $response['report'] = $this->report();
        }
        echo json_encode($response);
    }
    
    protected function report()
    {
        $this->data['info']['config']['checkbox'] = isset($this->data['info']['config']['checkbox']) ? (array)$this->data['info']['config']['checkbox'] : array() ;
        $newProd = isset($this->data['info']['config']['checkbox']['newProd']) ? 'checked="checked"' : '';
        $updateProd = isset($this->data['info']['config']['checkbox']['updateProd']) ? 'checked="checked"' : '';
        $newSkus = isset($this->data['info']['config']['checkbox']['newSkus']) ? 'checked="checked"' : '';
        $updateSkus = isset($this->data['info']['config']['checkbox']['updateSkus']) ? 'checked="checked"' : '';
        
        $report = '<div class="field reportData">';
        $report .= '<div class="value">';
        $report .= '<div class="s-csv-importexport-stats">';
        $report .= '<p>В CSV-файле обнаружена и готова к импорту следующая информация:</p>';
        $report .= '<ul>';
        $report .= '<li><input type="checkbox" '.$newProd.' name="checkbox[newProd]"/><i class="icon16 yes"></i>'.count($this->data['newProduct']).' новых товара <a href="http://'.waRequest::server('HTTP_HOST').'/'.$this->data['info']['path'].'newProd.csv" download>CSV</a><br></li>';
        $report .= '<li><input type="checkbox" '.$updateProd.' name="checkbox[updateProd]"/><i class="icon16 yes"></i>'.count($this->data['updateProduct']).' товара будут обновлены <a href="http://'.waRequest::server('HTTP_HOST').'/'.$this->data['info']['path'].'updateProd.csv" download>CSV</a><br></li>';
        $report .= '<li><input type="checkbox" '.$newSkus.' name="checkbox[newSkus]"/><i class="icon16 yes"></i>'.$this->data['newSkus'].' новых артикула <a href="http://'.waRequest::server('HTTP_HOST').'/'.$this->data['info']['path'].'newSkus.csv" download>CSV</a><br></li>';
        $report .= '<li><input type="checkbox" '.$updateSkus.' name="checkbox[updateSkus]"/><i class="icon16 yes"></i>'.$this->data['updateSkus'].' артикула будут обновлены <a href="http://'.waRequest::server('HTTP_HOST').'/'.$this->data['info']['path'].'updateSkus.csv" download>CSV</a><br></li>';
        
        if($this->data['ostat'] > 0)
        {
            $report .= '<li><i class="icon16 yes"></i>'.$this->data['ostat'].' артикула имеют негативный остаток <a href="http://'.waRequest::server('HTTP_HOST').'/'.$this->data['info']['path'].'ostat.csv" download>CSV</a><br></li>';
        }
        
        if(isset($this->data['features'])) {
            foreach($this->data['features'] as $f_id => $f)
            {
                if($f['count'] > 0)
                {
                    $fid = explode(':', $f_id);
                    $this->data['info']['config']['checkbox']['features'] = isset($this->data['info']['config']['checkbox']['features']) ? (array)$this->data['info']['config']['checkbox']['features'] : array();
                    $checked = isset($this->data['info']['config']['checkbox']['features'][$f['code']]) ? 'checked="checked"' : '';
                    $pole = $f['count'] > 4 ? 'полей' : 'поля' ;
                    $report .= '<li><input type="checkbox" '.$checked.' name="checkbox[features]['.$f['code'].']"/><i class="icon16 yes"></i>'.$f['count'].' '.$pole.' с новой характеристикой типа "'.$f['name'].'" <a href="http://'.waRequest::server('HTTP_HOST').'/'.$this->data['info']['path'].'new'.ucfirst($fid[1]).'.csv" download>CSV</a><br></li>';
                }
            }
        }
        
        if($this->data['notFeat'] > 0) {
            $report .= '<li><i class="icon16 yes"></i>'.$this->data['notFeat'].' размеров не нашли соответствие в размерной тоблице <a href="http://'.waRequest::server('HTTP_HOST').'/'.$this->data['info']['path'].'notFeat.csv" download>CSV</a><br></li>';
        }
        
        if($this->data['notFoundFeat'] > 0) {
            $report .= '<li><i class="icon16 yes"></i>'.$this->data['notFoundFeat'].' размеров не нашли соответствие в размерной тоблице(Новые товары)<a href="http://'.waRequest::server('HTTP_HOST').'/'.$this->data['info']['path'].'notFoundFeat.csv" download>CSV</a><br></li>';
        }
        
        $report .= '</ul>';
        $report .= '</div>';
        $report .= '</div>';
        $report .= '</div>';
        
        $report .= '<div class="field" style="margin-top: 30px;">';
        $report .= '<div class="value" style="margin-left: 100px!important;">';
        $report .= '<input type="button" id="productImport" class="button green" value="Импортировать">';
        $report .= '</div>';
        $report .= '</div>';        
         
        return $report;
    }
    
    private function error($message)
    {
        $path = wa()->getConfig()->getPath('log');
        waFiles::create($path.'/shop/backtop.log');
        waLog::log($message, 'shop/backtop.log');
    }    
}