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
        if($_SERVER['HTTP_HOST'] == 'localhost' || YII_ENV_DEV) {
            $isDebug = TRUE;
        } else {
            $isDebug = \yii::$app->request->getHeaders()->get('Referer');
            if(isset($isDebug)) {
                if(strpos($isDebug, 'ALL_ERROR=TRUE')) {
                    $isDebug = TRUE;
                } else {
                    $isDebug = FALSE;
                }
            } else {
                $isDebug = FALSE;
            }
        }
        if (\yii::$app->has('response')) {
            $response = \yii::$app->getResponse();
            $response->isSent = false;
        } else {
            $response = new Response();
        }
        if ($response->format === \yii\web\Response::FORMAT_HTML) {
            if ($isDebug && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                // AJAX request - prepare a brief response
                $response->data = $this->getExceptionMessage($exception, $isDebug);
                $responseCreated = TRUE;
            } elseif (!$isDebug && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                $response->data = $this->getExceptionMessage($exception, $isDebug);
                $responseCreated = TRUE;        
            }
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
    
    private function getExceptionMessage($exception, $isDebug) {
        if(!$isDebug) {
            return 'Request failed with errors on server.'  ;
        } else {
            $message = $exception->getMessage() .'<br/><br/>'. $exception->getFile() . ':' . $exception->getLine().'<br/>';
            $message .= "Stack trace:<br/>" . $exception->getTraceAsString();
            return $message;
        }
    }
}
