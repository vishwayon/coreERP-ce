<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
namespace app\cwf\sys\remoteServer;
/**
 * Description of RemoteServerValidator
 *
 * @author girish
 */
class RemoteServerValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    public function validateUserEditForm(){    
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);        
        
        // conduct business rule validations
        $this->validateBusinessRules();
    }
    
    
    protected function validateBusinessRules() {
        $cmm=new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select * from sys.remote_server where remote_server_name ilike :premote_server_name and remote_server_id!=:premote_server_id');
        $cmm->addParam('premote_server_name', $this->bo->remote_server_name);
        $cmm->addParam('premote_server_id', $this->bo->premote_server_id);
        $res= \app\cwf\vsla\data\DataConnect::getData($cmm, \app\cwf\vsla\data\DataConnect::MAIN_DB);
        if(count($res->Rows())>0){
            $this->bo->addBRule('Remote Server already exists. Duplicates not allowed.');
        }        
    }
}
