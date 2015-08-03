<?php

/**
 * @author Гречаный Николай <boonkerteam@gmail.com>
 * @author woody <woody@woody.ru>
 */
class shopCsvimportPluginBackendDisplayAction extends waViewAction
{
  public function execute()
  {
    $this->setLayout(new shopBackendLayout());

    $stock_model = new shopStockModel();
    $stocks = $stock_model->getAll();
    $skus_model = new shopProductSkusModel();
    $path = shopCsvimportPlugin::path();

    $model = new waModel();
    $result = $model->query("SELECT * FROM shop_csvimport_config")->fetchAll();
    if ($result) {
      $this->view->assign('config', $result);
    }

    if (file_exists($path)) {
      $fp = fopen($path, 'r');
      fgetcsv($fp, 0, ';');
      $j = 1;
      $data = array();
      while ($line = fgetcsv($fp, 0, ';')) {
        if (isset($line[1])) {
          $result = $model->query("SELECT id FROM shop_product_skus WHERE sku = '" . $line[1] . "'")->fetchField();

          $skuInfo = $skus_model->getSku($result);
          if (isset($line[2]) && $line[2] > 1) {
            for ($i = 0; $i < $line[2]; $i++) {
              $data[] = array(
                0 => $line[0],
                1 => $line[1],
                2 => $i + 1,
                3 => $skuInfo['count'],
                'ordin' => $j,
                'skuId' => $result,
                'stock' => $skuInfo['stock']);
              $j++;
            }
          } else {
            $data[] = array(
              0 => $line[0],
              1 => $line[1],
              2 => 1,
              3 => $skuInfo['count'],
              'ordin' => $j,
              'skuId' => $result,
              'stock' => $skuInfo['stock']);
            $j++;
          }
        }
      }
      fclose($fp);

    } else {
      $upProd = fopen($path, 'w');
      $header = array(0 => 'Наименование', 1 => 'Артикул', 2 => 'Итого',);
      fputcsv($upProd, $header, ';');
      fclose($upProd);
      $data = false;
    }

    $this->view->assign('stocks', $stocks);
    $this->view->assign('data', $data);
  }
}
