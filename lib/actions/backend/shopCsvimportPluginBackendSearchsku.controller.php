<?php

class shopCsvimportPluginBackendSearchskuController extends waJsonController
{

  public function execute()
  {
    try {
      $data = waRequest::post();
      $where = $data['val'] . "='" . $data['query'] . "'";
      $model = new waModel();
      $result = $model->query("SELECT id, product_id FROM shop_product_skus WHERE " . $where)->fetchAssoc();
      if ($result) {
        $skus_model = new shopProductSkusModel();
        $skuInfo = $skus_model->getSku($result['id']);

        $product_model = new shopProductModel();
        $product = $product_model->getById($result['product_id']);

        $p['name'] = $product['name'];
        $p['sku'] = (array)$skuInfo;

        $path = shopCsvimportPlugin::path();
        $response = false;
        $datas = [];

        if (file_exists($path)) {
          $file_handle = fopen($path, 'r');
          while (!feof($file_handle)) {
            $line_of_text = fgetcsv($file_handle, 0, ';');
            if (isset($line_of_text[1])) {
              if ($p['sku']['sku'] == $line_of_text[1]) {
                $line_of_text[2] = $line_of_text[2] + 1;
                $datas[] = $line_of_text;
                $response = true;
              } else {
                $datas[] = $line_of_text;
              }
            }
          }
          fclose($file_handle);
        }
        if (!$response) {
          $datas[] = array(
            0 => $p['name'],
            1 => $skuInfo['sku'],
            2 => 1,
          );
        }

        $upProd = fopen($path, 'w');
        foreach ($datas as $d) {
          fputcsv($upProd, (array)$d, ';');
        }
        fclose($upProd);

        $stock_model = new shopStockModel();
        $stocks = $stock_model->getAll();

        foreach ($stocks as $s) {
          $p['sku']['stocks'][$s['sort']] = $skuInfo['stock'][$s['id']];
        }

        unset($product);
        unset($skuInfo);

        $this->response['data'] = $datas;
        $this->response['skus'] = $p['sku']['sku'];
        $this->response['result'] = $p;
        $this->response['stock'] = $stocks;
        $this->response['message'] = 'ok';
      } else {
        $this->response['message'] = 'fail';
      }
    } catch (Exception $e) {
      $this->setError($e->getMessage());
    }
  }
}
