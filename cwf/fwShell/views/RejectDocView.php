<div id="reject-doc-view" title="Reject for review">
    <div class="row col-md-12">
        <input type="hidden" id="user_id_from" name="user_id_from" value="<?php echo $model['user_id_from'] ?>" />
        <input type="hidden" id="regress_stage_id" name="regress_stage_id" value="<?php echo $model['doc_stage_id_from'] ?>" />
        <div class="form-group col-md-4" disabled>
            <label class="control-label" for="Sender">Reject To</label>
            <input type="text" id="reject-to" class="textarea form-control" name="reject-to" maxlength="50" readonly="readonly"
                   data-validation-length="1-50" value="<?php echo $model['user_id_from_name'] ?>" />
        </div>
        <div class="form-group col-md-8">
            <label class="control-label" for="reject-to-email">e-Mail</label>
            <input type="text" id="reject-to-email" class="textarea form-control" name="reject-to-email" maxlength="150" readonly="readonly"
                      data-validation-length="1-150" value="<?php echo $model['user_id_from_email'] ?>" />
        </div>
    </div>
    <div class="row col-md-12">
        <div class="form-group col-md-12">
            <label class="control-label" for="sender-comment">Comments</label>
            <textarea type="text" id="sender-comment" class="textarea form-control" name="sender-comment" maxlength="500" rows="3"
                      data-validation-length="1-500" data-validation-error-msg="Rejection comments required" ></textarea>
        </div>
    </div>
   
</div>
