<?php

class shopCsvimportPlugin extends shopPlugin
{
  const ADDED_SKUS_CSV = 'addedSkus.csv';

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
    $html = $view->fetch($this->path . '/templates/backendProduct.html');
    return array('edit_basics' => $html);
  }

  public static function path($file = self::ADDED_SKUS_CSV, $fullpath = true)
  {
    $path = $fullpath ? wa()->getDataPath('plugins/csvimport/' . $file, true,'shop' ,true) : wa()->getDataPath('plugins/csvimport/', true,'shop' ,true);
    return $path;
  }

  public static function pathUrl($file = self::ADDED_SKUS_CSV)
  {
    $url = wa()->getDataUrl('plugins/csvimport/' . $file, true,'shop' ,true);
    return $url;
  }
}
