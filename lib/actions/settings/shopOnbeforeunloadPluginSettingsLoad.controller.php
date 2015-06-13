<?php

class shopOnbeforeunloadPluginSettingsLoadController extends waJsonController 
{
    public function execute()
    { 
        try {            
            $file = waRequest::file('file');
            if($file->uploaded()) {
                if($file->moveTo('wa-apps/shop/plugins/onbeforeunload/files/', $file->name)) {
                    $this->response['name'] = $file->name;
                } 
            } else {
                $this->response['message'] = 'fail';
            }        
        } catch (Exception $e) {
            $this->setError($e->getMessage());
        }
    }
}