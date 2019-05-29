<?php

use yii\widgets\ActiveForm;

?>
<h3>Generating Document ID</h3>
<div class="row">
    <span>The following variables are available for generating the document id. <br/>
          Proceed with caution as these changes are not reversible and would be applied for all documents generated in the future.
          <br/>
          The logic is executed in PostgreSQL server and is written in SQL. 
    </span>
    <table>
        <tr>
            <th>Variable</th>
            <th>Data Type</th>
            <th>Description</th>
        </tr>
        <tr>
            <td>pdoc_type</td>
            <td>Varchar(4)</td>
            <td>Document type prefix e.g: BPV, CPV, etc.</td>
        </tr>
        <tr>
            <td>pcompany_code</td>
            <td>Varchar(2)</td>
            <td>The company code [<?= $model->company_code ?>] mentioned while creating the company</td>
        </tr>
        <tr>
            <td>pbranch_code</td>
            <td>Varchar(2)</td>
            <td>The branch code that the user is connected to, while creating a document.</td>
        </tr>
        <tr>
            <td>pfinyear</td>
            <td>Varchar(4)</td>
            <td>The Fiscal/Financial Year user is connected to, while creating a document.</td>
        </tr>
        <tr>
            <td>pv_id</td>
            <td>BigInt</td>
            <td>The serial number of the document within the connected branch and Financial Year.</td>
        </tr>
    </table>
</div>
<div class="row col-md-9">
    <div id="divbrule" style="margin-top:15px;color:maroon;display: none;">
        <ul id="brokenrules"></ul>
    </div>
    <div>
        <?php $form = ActiveForm::begin([
                'id' => 'build-docid-form'
            ]);
        ?>
        <?= $form->field($model, 'doc_build_sql')->textInput(); ?>
        <?= \yii\helpers\Html::button('Update', ['class' => 'btn btn-primary col-2', 'name' => 'btn-update', 'onclick'=>'core_sys.build_docid.submitChange()']); ?>

        <?php ActiveForm::end(); ?>
    </div>    
    <script src="<?= \app\cwf\vsla\utils\ScriptHelper::registerScript('@app/cwf/sys/buildDocID/buildDocId_clientcode.js') ?>"></script>
</div>
