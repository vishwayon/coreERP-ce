<?php

namespace app\cwf\fwShell\controllers;

use app\cwf\vsla\base\WebController;
use app\cwf\vsla\render\DatasetHelper;

/**
 * Description of DatasetController
 *
 * @author dev
 */
class DatasetController extends WebController {

    protected $viewer_id;

    public function behaviors() {
        return [
            'verbs' => [
                'class' => \yii\filters\VerbFilter::className(),
                'actions' => [
                    'Render' => ['post'],
                ],
            ],
        ];
    }

    public function actionViewer($xmlPath, $rptOptions = "") {
        $viewOption = new \app\cwf\vsla\render\FormViewOptions();
        $viewOption->callingModulePath = '';
        $viewOption->xmlViewPath = str_replace('../', '@app/', $xmlPath) . '.xml';
        $design = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($viewOption->callingModulePath, $viewOption->xmlViewPath);
        $viewOption->accessLevel = \app\cwf\vsla\security\AccessManager::verifyAccess($design->id);
        $viewForRender = \app\cwf\vsla\render\ViewManager::getCompiledFormView($viewOption, $design);
        return $this->renderPartial('@app/cwf/fwShell/views/DatasetViewer', ['viewForRender' => $viewForRender, 'xmlPath' => $viewOption->xmlViewPath, 'rptOptions' => $rptOptions]);
    }

    public function actionGetDataset() {
        $req = \Yii::$app->request;
        $model = new \app\cwf\vsla\render\DatasetHelper();
        $res_dataset = $model->getDataset($req->bodyParams);
        $fresult = ['status' => 'Error'];
        if ($res_dataset['status'] == 'OK') {

            $virtualPath = 'reportcache/' . \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSession_ID() . '/';
            $pathName = \yii::getAlias('@webroot') . '/reportcache/' . \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSession_ID() . '/';
            \app\cwf\vsla\render\JReportHelper::createSessionPath();
  
            $source = $res_dataset['dsresult'];
            // prepare columns
            foreach ($source->getColumns() as $col) {
                $cols[] = $col->columnName;
            }

            $filename = $res_dataset['filename'];
            $filename .= '_' . ((new \DateTime())->format('Y_m_d_H_i_s')) . '.csv';
            //open file
            $fhandle = fopen($pathName . $filename, 'w');
            fputcsv($fhandle, $cols, ',', '"');
            foreach ($source->Rows() as $dr) {
                fputcsv($fhandle, $dr, ',', '"');
            }
            fclose($fhandle);

            $fresult = [
                'status' => 'OK',
                'filePath' => $virtualPath . $filename,
                'fileName' => $filename,
            ];
        } else {
            $fresult['error'] = $res_dataset['msg'];
        }
        return json_encode($fresult);
    }

}
