<?php

class shopCsvimportPlugin extends shopPlugin
{
    public function backend_menu() 
    {
        $html = '<li ' . (waRequest::get('plugin') == $this->id ? 'class="selected"' : 'class="no-tab"') . '>
                    <a href="?plugin=csvimport&action=display">CSV Импорт</a>
                </li>';
        return array('core_li' => $html);
    }
    
    public function backend_product($param)
    {
        $view = wa()->getView();
        $view->assign('skus', (array)$param['skus']);
        $html = $view->fetch($this->path.'/templates/backendProduct.html');
        return array('edit_basics' => $html);
    }
    
    // shopProductsCollection.class.php 1162
    public function csvimport_filter($data)
    {
        $get = waRequest::get();
        $model = new waModel();
        $prod = new shopProduct($data['p']['id']);
        foreach($prod['skus'] as $sku)
        {
            foreach($get as $key => $g)
            {
                if(is_array($g))
                {
                    $features[$key] = $g;
                    foreach($g as $feat)
                    {
                        $result = $model->query("SELECT id FROM shop_product_features WHERE product_id='".$data['p']['id']."'
                                                 AND sku_id='".$sku['id']."' AND feature_value_id='".$feat."'")->fetchField();
                        if($result) {
                            $res[$sku['id']]['feat'][$feat] = $result;
                        }
                    }
                }
            }
            $res[$sku['id']]['count'] = $sku['count'];
        }
        
        if(isset($features) && is_array($features)) {
            $countFeat = count($features);
            foreach($res as $key => $r)
            {
                $countRes = count($r['feat']);
                if($countFeat == $countRes) {
                    if($r['count'] <= 0) {
                        $pr[$data['p']['id']][$key] = 0;
                    } else {
                        $pr[$data['p']['id']][$key] = 1;
                    }
                }
            }
            
            if($pr[$data['p']['id']] &&is_array($pr[$data['p']['id']]))
            {
                if(!in_array(1, $pr[$data['p']['id']])) {
                    unset($data['products'][$data['p']['id']]);
                }
            }
            
        }
    }
}
