<?php

class shopCsvimportPluginBackendSaveconfigController extends waJsonController {
    
    public function execute()
    {        
        try {
            $data = waRequest::post();
            $nameConfig = $data['configName'];
            $data = json_encode($data);
            $model = new waModel();
            $name = $model->query("SELECT name FROM shop_csvimport_config WHERE name='".$nameConfig."'")->fetchField();
            if(!$name){
                $model->query("INSERT INTO shop_csvimport_config (name, data) VALUES ('".$nameConfig."','".$data."')");
            } else {
                $model->query("UPDATE shop_csvimport_config SET data='".$data."' WHERE name='".$nameConfig."'");
            }            
        } catch (Exception $e) {
            $this->setError($e->getMessage());
        }
    }
}