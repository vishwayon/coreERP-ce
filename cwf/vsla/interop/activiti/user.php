<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of user
 *
 * @author girish
 */

namespace vsla\interop\activiti;

require 'HTTP/Request2.php';
/**
 * Activiti User Interop Class
 */
class user {
    
    /**
     * Creates a new user or updates an existing user
     * @param \vsla\xmlbo\mastBo $boUser
     * @return boolean
     * @throws Exception
     */
    public function addUpdate(\vsla\xmlbo\mastBo $boUser) {
        // Make Rest call to get user info
        $req = new \HTTP_Request2();
        $req->setAuth(activitiConfig::USER_NAME, activitiConfig::USER_PASS, \HTTP_Request2::AUTH_BASIC);
        $req->setHeader("Accept", "application/json");
        $req->setHeader("Content-Type", "application/json");
        $req->setUrl(activitiConfig::REST_PATH."identity/users/".$boUser->user_name);
        $req->setMethod(\HTTP_Request2::METHOD_GET);
        
        $resp = $req->send();
        // If the user is not found, then create the user
        if ($resp->getStatus()==404 && $resp->getReasonPhrase()=="Not Found") {
            $req->setUrl(activitiConfig::REST_PATH."identity/users");
            $req->setMethod(\HTTP_Request2::METHOD_POST);
            
            $data = ["id"=>$boUser->user_name, 
                     "firstName"=>$boUser->full_user_name,
                     "lastName"=>"",
                     "email"=>$boUser->email,
                     "password"=>$boUser->user_pass];
            
            $jsonData = json_encode($data);
            $req->setBody($jsonData);
            $addResp = $req->send();
            if($addResp->getStatus()==201) {
                return true;
            } else {
                throw new \Exception("Interop-Activiti User Create Failed", $resp->getStatus());
            }
        } 
        // User was found, hence update his information
        elseif($resp->getStatus()==200 && $resp->getReasonPhrase()=="OK") {
            $req->setUrl(activitiConfig::REST_PATH."identity/users/".$boUser->user_name);
            $req->setMethod(\HTTP_Request2::METHOD_PUT);
            
            $data = ["firstName"=>$boUser->full_user_name,
                     "lastName"=>"",
                     "email"=>$boUser->email,
                     "password"=>$boUser->user_pass];
            
            $jsonData = json_encode($data);
            $req->setBody($jsonData);
            $addResp = $req->send();
            if($addResp->getStatus()==200) {
                return true;
            } else {
                throw new \Exception("Interop-Activiti User Update Failed", $resp->getStatus());
            }
        }        
    }
}
