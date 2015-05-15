<?php

class shopCsvimportPluginSettingsAction extends waViewAction
{
    
    public function execute()
    {
        $model_settings = new waAppSettingsModel();
        $settings = $model_settings->get($key = array('shop', 'csvimport')); 
        $model = new waModel();
        $config = $model->query("SELECT * FROM shop_csvimport_config")->fetchAll();
        
        $this->view->assign('config', $config);
        $this->view->assign('settings', $settings);
    }       
}
