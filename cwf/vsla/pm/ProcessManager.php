<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\vsla\pm;

/***
 * A class that manages all 
 * document processes
 */
class ProcessManager {
    
    /** @var PmArgs */
    private $pmArgs;
    
    /** @var RunState */
    private $runState;
    
    /** @var app\cwf\vsla\pm\PmParser */
    private $parser;
    
    function __construct(PmArgs $pmArgs) {
        $this->pmArgs = $pmArgs;    
        $this->init();
    } 
    
    private function init() {
        // query sys.process_run to create/run        
        if($this->pmArgs->process_run_id == -1) {
            $this->parser = new PmParser($this->pmArgs->xmlFilePath);            
            $startTask = $this->parser->parseNextTask(''); // send blank as we require start task info;
            $cn = \app\cwf\vsla\data\DataConnect::getCn(\app\cwf\vsla\data\DataConnect::COMPANY_DB);
            $cn->beginTransaction();
            $run_id = \app\cwf\vsla\entity\EntityManager::getMastSeqID(\app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getCompany_ID(), 'sys.process_run', $cn);
            $sql = 'Insert Into sys.process_run(process_run_id, process_id, doc_id, ctask_id, process_meta_path, process_starttime, last_updated)
                    values(:pprocess_run_id, :pprocess_id, :pdoc_id, :pctask_id, :pprocess_meta_path, current_timestamp(0), current_timestamp(0))';
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText($sql);
            $cmm->addParam('pprocess_run_id', $run_id);
            $cmm->addParam('pprocess_id', $this->parser->process_id);
            $cmm->addParam('pprocess_meta_path', $this->pmArgs->xmlFilePath);
            $cmm->addParam('pdoc_id', '');
            $cmm->addParam('pctask_id', $startTask->taskid);
            \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
            $cn->commit();
            $cn = null;   
            $this->pmArgs->process_run_id = $run_id;
        }
        
        if($this->pmArgs->process_run_id != -1) {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('Select * From sys.process_run Where process_run_id=:ppr_id');
            $cmm->addParam('ppr_id', $this->pmArgs->process_run_id);
            $dtpr = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if(count($dtpr->Rows())==1) {
                $this->runState = new RunState();
                $this->runState->process_run_id = $dtpr->Rows()[0]['process_run_id'];
                $this->runState->process_id = $dtpr->Rows()[0]['process_id'];
                $this->runState->doc_id = $dtpr->Rows()[0]['doc_id'];
                $this->runState->ctask_id = $dtpr->Rows()[0]['ctask_id'];
                $this->runState->process_starttime = $dtpr->Rows()[0]['process_starttime'];
                $this->parser = new PmParser($dtpr->Rows()[0]['process_meta_path']);
            } 
        } 
    }
    
    public function getRunState() : RunState {
        return $this->runState;
    }
    
    public function getNextTask() : UserTask {
        return $this->parser->parseNextTask($this->runState->ctask_id);
    }
    
    
}

