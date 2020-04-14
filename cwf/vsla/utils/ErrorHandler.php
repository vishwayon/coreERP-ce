<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\vsla\utils;

/**
 * Description of ErrorHandler
 *
 * @author girish
 */
class ErrorHandler extends \yii\web\ErrorHandler {
    
    public $traceLevel = 7;
    
    protected function renderException($exception) {
        $responseCreated = FALSE;
        
        if (\yii::$app->has('response')) {
            $response = \yii::$app->getResponse();
            $response->isSent = false;
        } else {
            $response = new Response();
        }
        
        if (YII_ENV_DEV) {
            $message = $exception->getMessage() .'<br/><br/>'. $exception->getFile() . ':' . $exception->getLine().'<br/>';
            $message .= "Stack trace:<br/>" . $exception->getTraceAsString();
            $response->data = $message;
            $responseCreated = true;
        } else {
            $response->data = 'Request failed with errors on server.'  ;
            $responseCreated = true;
        }
        
        if ($exception instanceof HttpException) {
            $response->setStatusCode($exception->statusCode);
        } else {
            $response->setStatusCode(500);
        }

        if($responseCreated) {
            $response->send();
        } else {
            // use base render exception
            parent::renderException($exception);
        }
    }
}
