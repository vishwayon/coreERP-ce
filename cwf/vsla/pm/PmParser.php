<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of PmParser
 *
 * @author girish
 */

namespace app\cwf\vsla\pm;

include_once '../cwf/vsla/pm/ParserResult.php';

class PmParser {
    //put your code here
    
    private $xmlFilePath;
    private $xBpmn;
    private $ns;
    private $xProcess;
    
    public $process_id;
    
    public function __construct(string $xmlFilePath) {
        $this->xmlFilePath = $xmlFilePath;
        $this->xBpmn = simplexml_load_file($this->xmlFilePath);
        $this->ns = $this->xBpmn->getDocNamespaces();
        $this->xProcess = $this->xBpmn->children($this->ns['bpmn'])->process;
        $this->process_id = (string)$this->xProcess->attributes()['id'];
    }
    
    public function parseNextTask(string $taskid) : ParserResult {
        if($taskid == '') {
            // find start task and return immediately succeeding task
            $xstart = $this->xProcess->startEvent;
            $xoutgoing = $xstart->outgoing;
        } else {
            $xtask = $this->getTaskInfo($taskid);
            $xoutgoing = $xtask->outgoing;
        }
        
        $targetTask = $this->getTargetTask((string)$xoutgoing);
        $xroles = $targetTask->children($this->ns['cwf'])->roles;
        $presult = new UserTask();
        $presult->taskid = (string)$targetTask->attributes()['id'];
        $presult->docAction = (string)$xroles->attributes()['docAction'];  //  ->xpath('child::cwf:roles')[0]['docAction'];
        $presult->roles = [];
        foreach($xroles->role as $role) {
            $presult->roles[] = (string)$role->attributes()['id'];
        }
        return $presult;
    }
    
     private function getTargetTask(string $incomingid) {
        $matches = $this->xProcess->xpath('//bpmn:incoming');
        foreach($matches as $child) {
            if($incomingid == (string)$child) {
                $parent = $child->xpath('..')[0];
                return $parent;
            }
        }
    }
    
    private function getTaskInfo(string $taskid) {
        $matches = $this->xProcess->xpath('//bpmn:userTask');
        foreach($matches as $child) {
            if($taskid == (string)$child->attributes()['id']) {
                return $child;
            }
        }
    }
}
