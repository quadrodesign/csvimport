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
    
}
