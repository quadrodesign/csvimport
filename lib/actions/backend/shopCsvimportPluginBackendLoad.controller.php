<?php

class shopCsvimportPluginBackendLoadController extends waJsonController
{

  public function execute()
  {
    try {

      function convert($str)
      {
        $charset = waRequest::post('charset');
        if ($charset == 'Windows-1251') {
          return iconv("Windows-1251", "UTF-8", $str);
        } else {
          return $str;
        }
      }

      $i = waRequest::post('name');

      if (!$i) {
        $file = waRequest::file('file');
        $fileName = $file->name;
      } else {
        $fileName = $i;
      }
      $name = explode('.', $fileName);
//      $path = shopCsvimportPlugin::path($fileName, false);
      $filePath = shopCsvimportPlugin::path($fileName);
      $count = count($name);
      if ($name[$count - 1] == 'csv') {
        if (waRequest::post('name')) {
//          $this->response['path'] = $path;
          $this->response['name'] = $fileName;
          $this->response['charset'] = 'UTF-8';


          $file = fopen($filePath, "r");
          $this->response['header'] = fgetcsv($file, 0, ';');
          $i = 0;
          $limit = 50;
          while ($line = fgetcsv($file, 0, ';')) {
            if ($i < $limit) {
              $info[$i] = $line;
            } else {
              break;
            }
            $i++;
          }
          fclose($file);
        } elseif ($file->uploaded()) {
          if ($file->moveTo($filePath)) {
//            $this->response['path'] = $path;
            $this->response['name'] = $file->name;
            $this->response['charset'] = waRequest::post('charset');

            $file = fopen($filePath, "r");
            $this->response['header'] = array_map("convert", fgetcsv($file, 0, ';'));
            $i = 0;
            $limit = 50;
            while ($line = fgetcsv($file, 0, ';')) {
              if ($i < $limit) {
                $info[$i] = array_map("convert", $line);
              } else {
                break;
              }
              $i++;
            }
            fclose($file);
          }
        }

        $this->response[''] = "Сохранено";
        $this->response['info'] = $info;


        $nameConfig = waRequest::post('config');
        if ($nameConfig != '') {
          $model = new waModel();
          $config = $model->query("SELECT data FROM shop_csvimport_config WHERE name='" . $nameConfig . "'")->fetchField();
          $config = json_decode($config);
          $this->response['config'] = $config;
          $this->response['configName'] = $nameConfig;
        }


        $options = array();
        $opt = (array)self::options();
        foreach ($opt as $key => $option) {
          if (is_array($option['group'])) {
            switch ($option['group']['class']) {
              case 'product' :
                $i = 0;
                break;
              case 'sku' :
                $i = 1;
                break;
              default :
                $i = 2;
                break;
            }

            $options[$i]['name'] = $option['group']['title'];
            $options[$i]['fields'][$key]['title'] = $option['title'];
            $options[$i]['fields'][$key]['value'] = $option['value'];
          }
        }

        $options[1]['fields'][] = array(
          'title' => 'Штрихкод',
          'value' => 'skus:-1:barcode',
        );

        $this->response['options'] = $options;
      } else {
        $this->response['message'] = 'fail';
      }
    } catch (Exception $e) {
      $this->setError($e->getMessage());
    }
  }

  public static function getMapFields($flat = false, $extra_fields = false)
  {
    $fields = array(
      'product' => array(
        'name' => _w('Product name'), //1
        'currency' => _w('Currency'), //4
        'summary' => _w('Summary'),
        'description' => _w('Description'),

        'badge' => _w('Badge'),
        'status' => _w('Status'),
        //'sort'             => _w('Product sort order'),
        'type_name' => _w('Product type'),
        'tags' => _w('Tags'),
        'tax_name' => _w('Taxable'),
        'meta_title' => _w('Title'),
        'meta_keywords' => _w('META Keyword'),
        'meta_description' => _w('META Description'),
        'url' => _w('Storefront link'),
        'images' => _w('Product images'),
        //   'rating'                 => _w('Rating'),
      ),
      'sku' => array(
        'skus:-1:name' => _w('SKU name'), //2
        'skus:-1:sku' => _w('SKU code'), //3
        'skus:-1:price' => _w('Price'),
        'skus:-1:available' => _w('Available for purchase'),
        'skus:-1:compare_price' => _w('Compare at price'),
        'skus:-1:purchase_price' => _w('Purchase price'),
        'skus:-1:stock:0' => _w('In stock'),
      ),
    );

    if ($extra_fields) {
      $product_model = new shopProductModel();
      $sku_model = new shopProductSkusModel();
      $meta_fields = array(
        'product' => $product_model->getMetadata(),
        'sku' => $sku_model->getMetadata(),
      );
      $black_list = array(
        'id',
        "contact_id",
        "create_datetime",
        "edit_datetime",
        "type_id",
        "image_id",
        "tax_id",
        "cross_selling",
        "upselling",
        "total_sales",
        "sku_type",
        "sku_count",
        'sku_id',
        'ext',
        'price',
        'compare_price',
        'min_price',
        'max_price',
        'count',
        'rating_count',
        'category_id',
        'base_price_selectable',
        'rating',
      );

      $white_list = array(
        'id_1c' => '1C',
      );
      foreach ($meta_fields['product'] as $field => $info) {
        if (!in_array($field, $black_list)) {
          $name = ifset($white_list[$field], $field);
          if (!empty($meta_fields['sku'][$field])) {
            if (!isset($fields['sku']['skus:-1:' . $field])) {
              $fields['sku']['skus:-1:' . $field] = $name;
            }
          } else {
            if (!isset($fields['product'][$field])) {
              $fields['product'][$field] = $name;
            }
          }
        }
      }
    }

    $stock_model = new shopStockModel();
    if ($stocks = $stock_model->getAll('id')) {
      foreach ($stocks as $stock_id => $stock) {
        $fields['sku']['skus:-1:stock:' . $stock_id] = _w('In stock') . ' @' . $stock['name'];
      }
    }

    if ($flat) {
      $fields_ = $fields;
      $fields = array();
      $flat_order = array(
        'product:name',
        'sku:skus:-1:name',
        'sku:skus:-1:sku',
        'product:currency'
      );

      foreach ($flat_order as $field) {
        list($type, $field) = explode(':', $field, 2);
        $fields[$field] = $fields_[$type][$field];
        unset($fields_[$type][$field]);
      }
      $fields += $fields_['sku'];
      $fields += $fields_['product'];
    }

    return $fields;
  }

  public function options()
  {
    $multiple = true;

    $translates = array();
    $translates['product'] = _w('Basic fields');
    $translates['sku'] = _w('SKU fields');
    $translates['feature'] = _w('Add to existing');
    $translates['feature+'] = _w('Add as new feature');


    $options = array();
    $fields = self::getMapFields();
    foreach ($fields as $group => $group_fields) {
      foreach ($group_fields as $id => $name) {
        $options[] = array(
          'group' => array(
            'title' => ifset($translates[$group]),
            'class' => $group,
          ),
          'value' => $id,
          'title' => ifempty($name, $id),
        );
      }
    }

    $limit = 30;//$this->getConfig()->getOption('features_per_page');
    $group = 'feature';
    $auto_complete = false;
    $feature_model = new shopFeatureModel();
    if ($feature_model->countByField(array('parent_id' => null)) < $limit) {
      $features = $feature_model->getFeatures(true); /*, true*/
    } else {
      $auto_complete = true;
      $header = array_unique(array_map('mb_strtolower', $this->reader->header()));
      //XXX optimize it for big tables
      $header = array_slice($header, 0, $limit);
      $features = $feature_model->getFeatures('name', $header);
    }
    foreach ($features as $id => $feature) {
      if (($feature['type'] == shopFeatureModel::TYPE_DIVIDER)) {
        unset($features[$id]);
      }
    }

    foreach ($features as $code => $feature) {
      $code = $feature['code'];
      if (!preg_match('/\.\d$/', $code) && ($feature['type'] != shopFeatureModel::TYPE_DIVIDER)) {
        $options[] = array(
          'group' => array(
            'title' => ifset($translates[$group]),
            'class' => $group,
          ),
          'value' => sprintf('features:%s', $code),
          'title' => $feature['name'],
          'description' => $code,
        );
      }
    }

    if ($auto_complete) {
      $options['autocomplete'] = array(
        'group' => array(
          'title' => ifset($translates[$group]),
          'class' => $group,
        ),
        'value' => 'features:%s',
        'title' => _w('Select feature'),
        'callback' => array(),
      );
    }

    //if ($this->getUser()->getRights('shop', 'settings')) {

    $group = 'feature+';
    foreach (shopFeatureModel::getTypes() as $f) {
      if ($f['available']) {
        if (empty($f['subtype'])) {
          if ($multiple || (empty($f['multiple']) && !preg_match('@^(range|2d|3d)\.@', $f['type']))) {
            $options[] = array(
              'group' => & $translates[$group],
              'value' => sprintf("f+:%s:%d:%d", $f['type'], $f['multiple'], $f['selectable']),
              'title' => empty($f['group']) ? $f['name'] : ($f['group'] . ': ' . $f['name']),
            );
          }
        } else {
          foreach ($f['subtype'] as $sf) {
            if ($sf['available']) {
              $type = str_replace('*', $sf['type'], $f['type']);
              if ($multiple || (empty($f['multiple']) && !preg_match('@^(range|2d|3d)\.@', $type))) {
                $options[] = array(
                  'group' => & $translates[$group],
                  'value' => sprintf("f+:%s:%d:%d", $type, $f['multiple'], $f['selectable']),
                  'title' => (empty($f['group']) ? $f['name'] : ($f['group'] . ': ' . $f['name'])) . " — {$sf['name']}",

                );
              }
            }
          }
        }
      }
    }
    //}

    return $options;
  }
}
