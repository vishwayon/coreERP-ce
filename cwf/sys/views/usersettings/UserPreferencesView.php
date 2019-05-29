<?php
use yii\helpers\Html;

/* @var $this yii\web\View */
$this->title = 'User Preferences';
$this->params['breadcrumbs'][] = $this->title;
?>

<div>
    <?php
        $form = yii\widgets\ActiveForm::begin([
        'id' => 'up-form',
        'options' => ['class' => 'form-horizontal'],
            'fieldConfig' => [
                'template' => "{label}\n<div class=\"col-lg-3\">{input}</div>\n<div class=\"col-lg-8\">{error}</div>",
                'labelOptions' => ['class' => 'col-lg-1 control-label'],
            ],
        ]); 
        ?>
    
    <table>
        <thead>
            <tr>
                <th></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><?=Html::label('Company')?></td>
                <td><?=Html::dropDownList('company_id', $model->company_id, $model->company_detail) ?></td>
            </tr>
            <tr>
                <td><?=Html::label('Branch')?></td>
                <td><?=Html::dropDownList('branch_id', $model->branch_id, $model->branch_detail) ?></td>
            </tr>
            <tr>
                <td><?=Html::label('Financial Year')?></td>
                <td><?=Html::dropDownList('finyear_id', $model->finyear_id, $model->finyear_detail) ?></td>
            </tr>
            <tr>
                <td></td>
                <td><?=Html::submitInput('Save') ?></td>
            </tr>
        </tbody>
    </table>
    <?php yii\widgets\ActiveForm::end() ?>
</div>
