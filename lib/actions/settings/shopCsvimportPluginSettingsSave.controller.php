<?php

class shopCsvimportPluginSettingsSaveController extends waJsonController {
    
    public function execute()
    {
        try {
            $id = waRequest::post('id');
            $model = new waModel();
            $model->query("DELETE FROM shop_csvimport_config WHERE id={$id}");
        } catch (Exception $e) {
            $this->setError($e->getMessage());
        }
    }
}