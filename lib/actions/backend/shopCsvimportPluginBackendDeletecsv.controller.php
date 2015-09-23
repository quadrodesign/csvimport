<?php

class shopCsvimportPluginBackendDeletecsvController extends waJsonController
{

  public function execute()
  {
    try {

      $id = waRequest::post('id');

      $skus_model = new shopProductSkusModel();
      $skuInfo = $skus_model->getSku($id);

      $path = shopCsvimportPlugin::path();
      $datas = array();
      $file_handle = fopen($path, 'r');
      while (!feof($file_handle)) {
        $line_of_text = fgetcsv($file_handle, 0, ';');
        if ($skuInfo['sku'] == $line_of_text[1]) {
          $line_of_text[2] = $line_of_text[2] - 1;
          if ($line_of_text[2] > 0) {
            $datas[] = $line_of_text;
          }
        } else {
          $datas[] = $line_of_text;
        }
      }
      fclose($file_handle);

      $upProd = fopen($path, 'w');
      foreach ($datas as $d) {
        fputcsv($upProd, (array)$d, ';');
      }
      fclose($upProd);

      $this->response['message'] = 'ok';

    } catch (Exception $e) {
      $this->setError($e->getMessage());
    }
  }
}