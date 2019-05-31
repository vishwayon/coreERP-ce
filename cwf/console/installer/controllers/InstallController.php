<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\console\installer\controllers;

class InstallController extends \yii\console\Controller {

    public function actionStart() {
        $outstream = fopen('php://memory', 'r+');
        try {

            \app\cwf\console\installer\workers\DbCreator::StartInstallation($outstream);
        } catch (\Exception $ex) {
            fwrite($outstream, $ex->getMessage() . "\n");
        }

        rewind($outstream);
        echo stream_get_contents($outstream);
    }

    public function actionUpgradedb($path) {
        // This method is used to update the database with a updateScripts file
        $outstream = fopen('php://memory', 'r+');
        try {

            \app\cwf\console\installer\workers\DbCreator::UpgradeDB($outstream);
        } catch (\Exception $ex) {
            fwrite($outstream, $ex->getMessage() . "\n");
        }

        rewind($outstream);
        echo stream_get_contents($outstream);
    }

    public function actionChangesu() {
        $outstream = fopen('php://memory', 'r+');
        try {
            \app\cwf\console\installer\workers\UserUtils::changesu($outstream);
        } catch (\Exception $ex) {
            fwrite($outstream, $ex->getMessage() . "\n");
        }

        rewind($outstream);
        echo stream_get_contents($outstream);
    }

    public function actionOpenip(string $ip) {
        $outstream = fopen('php://memory', 'r+');
        try {
            \app\cwf\console\installer\workers\UserUtils::openIP($outstream, $ip);
        } catch (\Exception $ex) {
            fwrite($outstream, $ex->getMessage() . "\n");
        }

        rewind($outstream);
        echo stream_get_contents($outstream);
    }
}
